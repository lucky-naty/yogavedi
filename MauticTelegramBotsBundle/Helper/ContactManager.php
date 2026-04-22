<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Entity\TelegramSubscription;
use Psr\Log\LoggerInterface;

class ContactManager
{
    public function __construct(
        private LeadModel $leadModel,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
    ) {
    }

    public function createOrUpdate(array $telegramData, ?int $botId = null, array $tags = []): ?int
    {
        $chatId   = (string) ($telegramData['chat_id'] ?? '');
        $username = $this->formatTelegramUsername((string) ($telegramData['username'] ?? ''));
        $phone    = $telegramData['phone'] ?? '';

        $fields = array_filter([
            'telegram_chat_id'  => $chatId,
            'telegram_username' => $username,
            'firstname'         => $telegramData['first_name'] ?? '',
            'lastname'          => $telegramData['last_name'] ?? '',
            'phone'             => $phone,
        ], fn ($value) => '' !== $value);

        $contact = $chatId ? $this->findByChatId($chatId) : null;

        if ($phone) {
            $contactByPhone = $this->findByPhone($phone);
            if ($contactByPhone && $contact && $contactByPhone->getId() !== $contact->getId()) {
                $this->logger->info('TelegramBots: phone contact wins over chat contact during merge', [
                    'phone_contact_id' => $contactByPhone->getId(),
                    'chat_contact_id'  => $contact->getId(),
                ]);
                $contact = $contactByPhone;
            } elseif ($contactByPhone && !$contact) {
                $contact = $contactByPhone;
            }
        }

        if ($contact) {
            foreach (['firstname', 'lastname'] as $nameField) {
                if (!empty($contact->getFieldValue($nameField))) {
                    unset($fields[$nameField]);
                }
            }
            $this->leadModel->setFieldValues($contact, $fields, false);
        } else {
            $contact = new Lead();
            $this->leadModel->setFieldValues($contact, $fields, false);
        }

        $this->leadModel->saveEntity($contact);

        if ($contact && $botId && $chatId) {
            $this->registerSubscription($contact, (int) $botId, $chatId);
        }

        if (!empty($tags)) {
            $this->leadModel->modifyTags($contact, $tags, [], false);
            $this->leadModel->saveEntity($contact);
        }

        return $contact->getId();
    }

    private function registerSubscription(Lead $contact, int $botId, string $chatId): void
    {
        $repo = $this->em->getRepository(TelegramSubscription::class);
        $bot = $this->em->getRepository(Bot::class)->find($botId);

        if (!$bot) {
            $this->logger->warning('TelegramBots: bot not found while registering subscription', [
                'bot_id'     => $botId,
                'contact_id' => $contact->getId(),
            ]);
            return;
        }

        $subscription = $repo->findOneBy([
            'bot'    => $bot,
            'chatId' => $chatId,
        ]);

        if (!$subscription) {
            $subscription = new TelegramSubscription($bot, $contact, $chatId);
            $this->em->persist($subscription);
        } else {
            $subscription->reactivate($contact);
        }

        $this->em->flush();
    }

    public function markSubscriptionInactive(int $botId, string $chatId): void
    {
        $repo = $this->em->getRepository(TelegramSubscription::class);
        $bot = $this->em->getRepository(Bot::class)->find($botId);

        if (!$bot) {
            return;
        }

        $subscription = $repo->findOneBy([
            'bot'    => $bot,
            'chatId' => $chatId,
        ]);

        if (!$subscription || !$subscription->isActive()) {
            return;
        }

        $subscription->deactivate();
        $this->em->flush();

        $this->logger->info('TelegramBots: subscription deactivated', [
            'bot_id'  => $botId,
            'chat_id' => $chatId,
        ]);
    }

    private function findByChatId(string $chatId): ?Lead
    {
        $contactId = $this->em->getConnection()->fetchOne(
            'SELECT id FROM leads WHERE telegram_chat_id = :chatId LIMIT 1',
            ['chatId' => $chatId]
        );

        return $contactId ? $this->leadModel->getEntity($contactId) : null;
    }

    private function findByPhone(string $phone): ?Lead
    {
        $contactId = $this->em->getConnection()->fetchOne(
            'SELECT id FROM leads WHERE phone = :phone LIMIT 1',
            ['phone' => $phone]
        );

        return $contactId ? $this->leadModel->getEntity($contactId) : null;
    }

    private function formatTelegramUsername(string $username): string
    {
        $username = trim($username);

        if ('' === $username) {
            return '';
        }

        return '@'.ltrim($username, '@');
    }
}
