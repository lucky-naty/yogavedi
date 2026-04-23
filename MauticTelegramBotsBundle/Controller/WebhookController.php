<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Controller\CommonController;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Entity\BotRepository;
use MauticPlugin\MauticTelegramBotsBundle\Helper\ContactManager;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TokenCryptoHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends CommonController
{
    public function __construct(
        private EntityManagerInterface $em,
        private TelegramBotApiHelper $apiHelper,
        private ContactManager $contactManager,
    ) {
    }

    public function handleAction(Request $request, string $token): Response
    {
        /** @var BotRepository $repository */
        $repository = $this->em->getRepository(Bot::class);
        $bot = $repository->findByWebhookSecret($token);

        if (!$bot) {
            return new Response('Not found', 404);
        }

        $update = json_decode($request->getContent(), true);

        if (!$update || !isset($update['message'])) {
            return new Response('OK');
        }

        $message = $update['message'];
        register_shutdown_function(function () use ($message, $bot): void {
            $this->handleMessage($message, $bot);
        });

        return new Response('OK');
    }

    private function handleMessage(array $message, Bot $bot): void
    {
        $chatId  = $message['chat']['id'];
        $from    = $message['from'] ?? [];
        $text    = $message['text'] ?? '';
        $contact = $message['contact'] ?? null;

        if ($contact) {
            $this->handleContactShared($chatId, $from, $contact, $bot);
            return;
        }

        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $from, $bot);
        }
    }

    private function handleStart(int $chatId, array $from, Bot $bot): void
    {
        $firstName = $from['first_name'] ?? '';

        $this->contactManager->createOrUpdate([
            'chat_id'    => $chatId,
            'username'   => $from['username'] ?? '',
            'first_name' => $firstName,
            'last_name'  => $from['last_name'] ?? '',
        ], $bot->getId(), $bot->getTagsArray());

        $welcomeText = $bot->getWelcomeMessage();
        if (empty($welcomeText)) {
            $welcomeText = "Hello, <b>{$firstName}</b>!\n\nYou have successfully subscribed.";
        } else {
            $welcomeText = str_replace(['{first_name}', '{firstname}'], $firstName, $welcomeText);
        }

        if ($bot->isAskPhone()) {
            $askText = $bot->getAskPhoneMessage() ?: 'Please share your phone number:';
            $this->sendMessage($bot, $chatId, $welcomeText);
            $this->sendMessage($bot, $chatId, $askText, $this->apiHelper->buildContactKeyboard());
        } else {
            $this->sendMessage($bot, $chatId, $welcomeText);
        }
    }

    private function handleContactShared(int $chatId, array $from, array $contact, Bot $bot): void
    {
        $phone = preg_replace('/[^0-9+]/', '', $contact['phone_number'] ?? '');

        $this->contactManager->createOrUpdate([
            'chat_id'    => $chatId,
            'username'   => $from['username'] ?? '',
            'first_name' => $contact['first_name'] ?? $from['first_name'] ?? '',
            'last_name'  => $contact['last_name'] ?? $from['last_name'] ?? '',
            'phone'      => $phone,
        ], $bot->getId(), $bot->getTagsArray());

        $message = $bot->getPhoneReceivedMessage() ?: 'Thank you! Your data has been saved.';

        $this->sendMessage($bot, $chatId, $message, $this->apiHelper->removeKeyboard());
    }

    private function sendMessage(Bot $bot, int|string $chatId, string $text, array $replyMarkup = []): array
    {
        $token = (new TokenCryptoHelper())->decryptIfNeeded($bot->getToken());
        $result = $this->apiHelper->sendMessage($token, $chatId, $text, $replyMarkup, $bot->getApiBaseUrl());

        if ($this->isBlockedByUser($result) && null !== $bot->getId()) {
            $this->contactManager->markSubscriptionInactive($bot->getId(), (string) $chatId);
        }

        return $result;
    }

    private function isBlockedByUser(array $telegramResult): bool
    {
        $description = strtolower((string) ($telegramResult['description'] ?? ''));

        return false === ($telegramResult['ok'] ?? false)
            && 403 === (int) ($telegramResult['error_code'] ?? 0)
            && str_contains($description, 'blocked');
    }
}
