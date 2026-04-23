<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticMaxBotsBundle\Entity\Bot;
use MauticPlugin\MauticMaxBotsBundle\Entity\BotRepository;
use MauticPlugin\MauticMaxBotsBundle\Entity\MaxIncomingEvent;
use MauticPlugin\MauticMaxBotsBundle\Entity\MaxSubscription;
use MauticPlugin\MauticMaxBotsBundle\Helper\ContactManager;
use MauticPlugin\MauticMaxBotsBundle\Helper\MaxApiHelper;
use MauticPlugin\MauticMaxBotsBundle\Helper\SubscriptionManager;
use MauticPlugin\MauticMaxBotsBundle\Helper\TokenCryptoHelper;
use MauticPlugin\MauticMaxBotsBundle\Helper\WebhookPayloadParser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class WebhookController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WebhookPayloadParser $payloadParser,
        private SubscriptionManager $subscriptionManager,
        private ContactManager $contactManager,
    ) {
    }

    public function handleAction(string $token, Request $request): JsonResponse
    {
        /** @var BotRepository $botRepository */
        $botRepository = $this->entityManager->getRepository(Bot::class);
        $bot = $botRepository->findOneByWebhookSecret($token);
        $payload = $request->getContent();
        $event = null;
        $parsed = null;

        if (!$bot instanceof Bot) {
            return new JsonResponse(['status' => 'error', 'message' => 'Bot not found.'], 404);
        }

        try {
            $parsed = $this->payloadParser->parse($payload);
            $eventId = (string) ($parsed['event_id'] ?? '');
            $eventType = (string) ($parsed['event_type'] ?? 'unknown');

            $eventRepository = $this->entityManager->getRepository(MaxIncomingEvent::class);
            $event = $eventRepository->findOneBy([
                'bot' => $bot,
                'eventId' => $eventId,
            ]);

            if ($event instanceof MaxIncomingEvent && 'processed' === $event->getStatus()) {
                return new JsonResponse(['status' => 'ok']);
            }

            $this->entityManager->getConnection()->beginTransaction();

            $event = $event instanceof MaxIncomingEvent
                ? $event
                : $this->createIncomingEvent($bot, $eventId, $eventType, $payload);

            if (null !== $event->getId()) {
                $event
                    ->setPayload($payload)
                    ->setEventId($eventId)
                    ->setEventType($eventType)
                    ->setStatus('pending')
                    ->setErrorMessage(null)
                    ->setProcessedAt(null);
            }

            $existingSubscription = $this->subscriptionManager->findSubscription($bot, (string) ($parsed['chat_id'] ?? ''));
            $isNewSubscriber = !$existingSubscription instanceof MaxSubscription;
            $hadConfirmedPhone = $existingSubscription?->isPhoneConfirmed() ?? false;

            $subscription = $this->subscriptionManager->upsertSubscription($bot, $parsed);
            $this->bindLeadFromPhone($subscription, $parsed);
            $this->handleWebhookMessages($bot, $subscription, $isNewSubscriber, $hadConfirmedPhone);

            $event
                ->setSubscription($subscription)
                ->setStatus('processed')
                ->setProcessedAt(new \DateTimeImmutable())
                ->setErrorMessage(null);

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            return new JsonResponse([
                'status' => 'ok',
            ]);
        } catch (\Throwable $e) {
            $connection = $this->entityManager->getConnection();
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->storeFailedEvent($bot, $payload, $parsed, $event, $e);

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Webhook processing failed.',
            ], 400);
        }
    }

    private function createIncomingEvent(Bot $bot, string $eventId, string $eventType, string $payload): MaxIncomingEvent
    {
        $event = new MaxIncomingEvent();
        $event
            ->setBot($bot)
            ->setEventId($eventId)
            ->setEventType($eventType)
            ->setPayload($payload)
            ->setStatus('pending')
            ->setDateAdded(new \DateTimeImmutable());

        $this->entityManager->persist($event);

        return $event;
    }

    /**
     * @param array<string, mixed> $parsed
     */
    private function bindLeadFromPhone(MaxSubscription $subscription, array $parsed): void
    {
        $phoneNumber = trim((string) ($parsed['phone'] ?? ''));
        if ('' === $phoneNumber) {
            return;
        }

        $leadId = $this->contactManager->findLeadIdByPhone($phoneNumber);
        if (null === $leadId) {
            $leadId = $this->contactManager->createOrUpdateLeadFromSubscription($subscription);
        }

        $subscription->setLeadId($leadId);
    }

    private function handleWebhookMessages(
        Bot $bot,
        MaxSubscription $subscription,
        bool $isNewSubscriber,
        bool $hadConfirmedPhone,
    ): void {
        $token = $this->getBotApiToken($bot);
        $chatId = trim($subscription->getChatId());
        if ('' === $token || '' === $chatId) {
            return;
        }

        $apiBaseUrl = $bot->getApiBaseUrl();

        if ($isNewSubscriber) {
            $this->sendTextIfConfigured($token, $chatId, $bot->getWelcomeMessage(), $apiBaseUrl);

            if (!$subscription->isPhoneConfirmed()) {
                $this->sendPhoneRequestIfConfigured($token, $chatId, $bot->getPhoneRequestMessage(), $apiBaseUrl);
            }
        }

        if (!$hadConfirmedPhone && $subscription->isPhoneConfirmed()) {
            $this->sendTextIfConfigured($token, $chatId, $bot->getContactSuccessMessage(), $apiBaseUrl);
        }
    }

    private function sendTextIfConfigured(string $token, string $chatId, string $message, string $apiBaseUrl): void
    {
        $message = trim($message);
        if ('' === $message) {
            return;
        }

        $response = MaxApiHelper::sendTextMessage($token, $chatId, $message, $apiBaseUrl);
        $this->assertApiCallSucceeded($response);
    }

    private function sendPhoneRequestIfConfigured(string $token, string $chatId, string $message, string $apiBaseUrl): void
    {
        $message = trim($message);
        if ('' === $message) {
            return;
        }

        $response = MaxApiHelper::sendPhoneRequestMessage($token, $chatId, $message, $apiBaseUrl);
        $this->assertApiCallSucceeded($response);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertApiCallSucceeded(array $response): void
    {
        $ok = $response['ok'] ?? null;
        if (true === $ok) {
            return;
        }

        $message = trim((string) ($response['description'] ?? $response['message'] ?? 'MAX API request failed.'));
        if ('' === $message) {
            $message = 'MAX API request failed.';
        }

        throw new \RuntimeException($message);
    }

    private function getBotApiToken(Bot $bot): string
    {
        return trim($this->getTokenCryptoHelper()->decryptIfNeeded($bot->getToken()));
    }

    private function getTokenCryptoHelper(): TokenCryptoHelper
    {
        return new TokenCryptoHelper($this->getSecretKey());
    }

    private function getSecretKey(): string
    {
        foreach (['MAUTIC_SECRET_KEY', 'APP_SECRET', 'SYMFONY_SECRET'] as $environmentVariable) {
            $secret = trim((string) getenv($environmentVariable));
            if ('' !== $secret) {
                return $secret;
            }
        }

        $bundleRoot = dirname(__DIR__);
        $candidateFiles = [
            dirname($bundleRoot, 1).'/config/local.php',
            dirname($bundleRoot, 2).'/config/local.php',
            dirname($bundleRoot, 3).'/config/local.php',
            dirname($bundleRoot, 1).'/app/config/local.php',
            dirname($bundleRoot, 2).'/app/config/local.php',
            dirname($bundleRoot, 3).'/app/config/local.php',
        ];

        foreach ($candidateFiles as $file) {
            if (!is_file($file)) {
                continue;
            }

            $parameters = [];
            require $file;

            $secret = (string) ($parameters['secret_key'] ?? '');
            if ('' !== $secret) {
                return $secret;
            }
        }

        throw new \RuntimeException('Mautic secret_key is required for MAX token encryption.');
    }

    /**
     * @param array<string, mixed>|null $parsed
     */
    private function storeFailedEvent(
        Bot $bot,
        string $payload,
        ?array $parsed,
        ?MaxIncomingEvent $event,
        \Throwable $exception,
    ): void {
        try {
            $eventId = $this->resolveFailedEventId($payload, $parsed);
            $eventType = (string) ($parsed['event_type'] ?? 'invalid');

            if (!$event instanceof MaxIncomingEvent) {
                $eventRepository = $this->entityManager->getRepository(MaxIncomingEvent::class);
                $existingEvent = $eventRepository->findOneBy([
                    'bot' => $bot,
                    'eventId' => $eventId,
                ]);

                $event = $existingEvent instanceof MaxIncomingEvent
                    ? $existingEvent
                    : $this->createIncomingEvent($bot, $eventId, $eventType, $payload);
            }

            $event
                ->setPayload($payload)
                ->setEventId($eventId)
                ->setEventType($eventType)
                ->setStatus('error')
                ->setErrorMessage($exception->getMessage())
                ->setProcessedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed>|null $parsed
     */
    private function resolveFailedEventId(string $payload, ?array $parsed): string
    {
        $eventId = trim((string) ($parsed['event_id'] ?? ''));
        if ('' !== $eventId) {
            return $eventId;
        }

        return 'invalid:'.sha1($payload);
    }
}
