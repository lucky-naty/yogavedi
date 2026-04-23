<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticMaxBotsBundle\Entity\Bot;
use MauticPlugin\MauticMaxBotsBundle\Entity\MaxSubscription;
use Psr\Log\LoggerInterface;

final class SubscriptionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function upsertSubscription(Bot $bot, array $parsed): MaxSubscription
    {
        $chatId = trim((string) ($parsed['chat_id'] ?? ''));
        if ('' === $chatId) {
            throw new \InvalidArgumentException('Webhook payload is missing chat_id.');
        }

        $subscription = $this->findSubscription($bot, $chatId);

        $isNew = false;
        if (!$subscription instanceof MaxSubscription) {
            $subscription = new MaxSubscription();
            $subscription->setBot($bot);
            $subscription->setChatId($chatId);
            $subscription->setDateAdded(new \DateTimeImmutable());
            $isNew = true;
        }

        $now = new \DateTimeImmutable();
        $subscription
            ->setIsActive(true)
            ->setIsBlocked(false)
            ->setUnsubscribedAt(null)
            ->setLastActiveAt($now)
            ->setLastMessageAt($now)
            ->setDateModified($now);

        $this->updateOptionalStringField($subscription, $parsed, 'user_id', 'setMaxUserId');
        $this->updateOptionalStringField($subscription, $parsed, 'username', 'setUsername');
        $this->updateOptionalStringField($subscription, $parsed, 'first_name', 'setFirstName');
        $this->updateOptionalStringField($subscription, $parsed, 'last_name', 'setLastName');
        $this->updateOptionalStringField($subscription, $parsed, 'language_code', 'setLanguageCode');

        if (array_key_exists('phone', $parsed)) {
            $phone = trim((string) $parsed['phone']);
            if ('' !== $phone) {
                $subscription
                    ->setPhoneNumber($phone)
                    ->setIsPhoneConfirmed(true);
            }
        }

        if (null === $subscription->getSubscribedAt()) {
            $subscription->setSubscribedAt($now);
        }

        if ($isNew) {
            $this->entityManager->persist($subscription);
        }

        return $subscription;
    }

    public function findSubscription(Bot $bot, string $chatId): ?MaxSubscription
    {
        $chatId = trim($chatId);
        if ('' === $chatId) {
            return null;
        }

        $repository = $this->entityManager->getRepository(MaxSubscription::class);
        $subscription = $repository->findOneBy([
            'bot' => $bot,
            'chatId' => $chatId,
        ]);

        return $subscription instanceof MaxSubscription ? $subscription : null;
    }

    public function markUnavailable(MaxSubscription $subscription, string $error): void
    {
        $now = new \DateTimeImmutable();
        $subscription
            ->setIsActive(false)
            ->setIsBlocked(true)
            ->setUnsubscribedAt($now)
            ->setDateModified($now);

        $this->entityManager->persist($subscription);

        $this->logger?->warning('MAX subscription marked unavailable', [
            'subscription_id' => $subscription->getId(),
            'bot_id' => $subscription->getBot()->getId(),
            'chat_id' => $subscription->getChatId(),
            'error' => $error,
        ]);
    }

    public function reactivate(MaxSubscription $subscription): void
    {
        $now = new \DateTimeImmutable();
        $subscription
            ->setIsActive(true)
            ->setIsBlocked(false)
            ->setUnsubscribedAt(null)
            ->setDateModified($now);

        if (null === $subscription->getSubscribedAt()) {
            $subscription->setSubscribedAt($now);
        }

        $this->entityManager->persist($subscription);

        $this->logger?->info('MAX subscription reactivated', [
            'subscription_id' => $subscription->getId(),
            'bot_id' => $subscription->getBot()->getId(),
            'chat_id' => $subscription->getChatId(),
        ]);
    }

    private function updateOptionalStringField(MaxSubscription $subscription, array $parsed, string $parsedKey, string $setter): void
    {
        if (!array_key_exists($parsedKey, $parsed)) {
            return;
        }

        $value = trim((string) $parsed[$parsedKey]);
        if ('' === $value) {
            return;
        }

        $subscription->{$setter}($value);
    }
}
