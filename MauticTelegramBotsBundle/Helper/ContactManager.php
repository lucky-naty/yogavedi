<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Helper;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Entity\TelegramSubscription;

class ContactManager
{
    public function __construct(
        private LeadModel $leadModel,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Р РЋР С•Р В·Р Т‘Р В°Р ВµРЎвЂљ Р С‘Р В»Р С‘ Р С•Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµРЎвЂљ Р С”Р С•Р Р…РЎвЂљР В°Р С”РЎвЂљ Р С‘ РЎР‚Р ВµР С–Р С‘РЎРѓРЎвЂљРЎР‚Р С‘РЎР‚РЎС“Р ВµРЎвЂљ Р ВµР С–Р С• Р С—Р С•Р Т‘Р С—Р С‘РЎРѓР С”РЎС“ Р Р…Р В° Р В±Р С•РЎвЂљР В°.
     *
     * @param array $telegramData Р вЂќР В°Р Р…Р Р…РЎвЂ№Р Вµ Р С‘Р В· Р Р†Р ВµР В±РЎвЂ¦РЎС“Р С”Р В° (chat_id, username, etc.)
     * @param int|null $botId ID Р В±Р С•РЎвЂљР В°, Р С” Р С”Р С•РЎвЂљР С•РЎР‚Р С•Р СРЎС“ Р С—РЎР‚Р С‘Р Р†РЎРЏР В·РЎвЂ№Р Р†Р В°Р ВµРЎвЂљРЎРѓРЎРЏ Р С”Р С•Р Р…РЎвЂљР В°Р С”РЎвЂљ
     * @param array $tags Р СћР ВµР С–Р С‘ Р Т‘Р В»РЎРЏ Р С”Р С•Р Р…РЎвЂљР В°Р С”РЎвЂљР В°
     */
    public function createOrUpdate(array $telegramData, ?int $botId = null, array $tags = []): ?int
    {
        $chatId   = (string) ($telegramData['chat_id'] ?? '');
        $username = $telegramData['username'] ?? '';
        $phone    = $telegramData['phone'] ?? '';

        $fields = array_filter([
            'telegram_chat_id'  => $chatId,
            'telegram_username' => $username,
            'firstname'         => $telegramData['first_name'] ?? '',
            'lastname'          => $telegramData['last_name'] ?? '',
            'phone'             => $phone,
        ], fn($v) => $v !== '');

        $nameFields = ['firstname', 'lastname'];
        $contact = null;

        // 1. Р СџР С•Р С‘РЎРѓР С” Р С”Р С•Р Р…РЎвЂљР В°Р С”РЎвЂљР В°
        if ($chatId) {
            $contact = $this->findByChatId($chatId);
        }

        if ($phone) {
            $contactByPhone = $this->findByPhone($phone);
            if ($contactByPhone && $contact && $contactByPhone->getId() !== $contact->getId()) {
                $this->logger->info('TelegramBots: Merging contacts - phone contact ' . $contactByPhone->getId() . ' wins over chat contact ' . $contact->getId());
                $newContactId = $contact->getId();
                $contact = $contactByPhone;
                $conn = $this->em->getConnection();
                $conn->executeStatement("DELETE FROM leads WHERE id = :id", ['id' => $newContactId]);
            } elseif ($contactByPhone && !$contact) {
                $contact = $contactByPhone;
            }
        }

        // 2. Р РЋР С•Р В·Р Т‘Р В°Р Р…Р С‘Р Вµ Р С‘Р В»Р С‘ Р С•Р В±Р Р…Р С•Р Р†Р В»Р ВµР Р…Р С‘Р Вµ Р С—Р С•Р В»Р ВµР в„– Р С”Р С•Р Р…РЎвЂљР В°Р С”РЎвЂљР В°
        if ($contact) {
            $this->logger->info('TelegramBots: Updating contact ID ' . $contact->getId());
            foreach ($nameFields as $nameField) {
                if (!empty($contact->getFieldValue($nameField))) {
                    unset($fields[$nameField]);
                }
            }
            $this->leadModel->setFieldValues($contact, $fields, false);
        } else {
            $this->logger->info('TelegramBots: Creating new contact');
            $contact = new Lead();
            $this->leadModel->setFieldValues($contact, $fields, false);
        }

        $this->leadModel->saveEntity($contact);

        // 3. Р В Р вЂўР вЂњР ВР РЋР СћР В Р С’Р В¦Р ВР Р‡ Р СџР С›Р вЂќР СџР ВР РЋР С™Р В Р СњР С’ Р вЂР С›Р СћР С’ (Р СњР С•Р Р†Р В°РЎРЏ Р В»Р С•Р С–Р С‘Р С”Р В°)
        if ($contact && $botId && $chatId) {
            $this->registerSubscription($contact, (int)$botId, $chatId);
        }

        // 4. Р С›Р В±Р Р…Р С•Р Р†Р В»Р ВµР Р…Р С‘Р Вµ РЎвЂљР ВµР С–Р С•Р Р†
        if (!empty($tags)) {
            $this->leadModel->modifyTags($contact, $tags, [], false);
            $this->leadModel->saveEntity($contact);
        }

        return $contact->getId();
    }

    /**
     * Р РЋР С•Р В·Р Т‘Р В°Р ВµРЎвЂљ Р С‘Р В»Р С‘ Р С•Р В±Р Р…Р С•Р Р†Р В»РЎРЏР ВµРЎвЂљ РЎРѓР Р†РЎРЏР В·РЎРЉ Р С”Р С•Р Р…РЎвЂљР В°Р С”РЎвЂљР В° РЎРѓ Р В±Р С•РЎвЂљР С•Р С Р Р† РЎвЂљР В°Р В±Р В»Р С‘РЎвЂ Р Вµ Р С—Р С•Р Т‘Р С—Р С‘РЎРѓР С•Р С”
     */
    private function registerSubscription(Lead $contact, int $botId, string $chatId): void
    {
        $repo = $this->em->getRepository(TelegramSubscription::class);
        $bot = $this->em->getRepository(Bot::class)->find($botId);

        if (!$bot) {
            $this->logger->warning("TelegramBots: Bot {$botId} not found while registering subscription for contact {$contact->getId()}");
            return;
        }

        $subscription = $repo->findOneBy([
            'bot' => $bot,
            'lead' => $contact,
        ]);

        if (!$subscription) {
            $subscription = new TelegramSubscription($bot, $contact, $chatId);
            $this->em->persist($subscription);
            $this->logger->info("TelegramBots: New subscription created for contact {$contact->getId()} on bot {$botId}");
        } elseif ($subscription->getChatId() !== $chatId) {
            $subscription->setChatId($chatId);
        }

        $this->em->flush();
    }

    private function findByChatId(string $chatId): ?Lead
    {
        $conn = $this->em->getConnection();
        $sql  = "SELECT id FROM leads WHERE telegram_chat_id = :chatId LIMIT 1";
        $result = $conn->executeQuery($sql, ['chatId' => $chatId])->fetchAssociative();

        if ($result) {
            return $this->leadModel->getEntity($result['id']);
        }
        return null;
    }

    private function findByPhone(string $phone): ?Lead
    {
        $conn = $this->em->getConnection();
        $sql  = "SELECT id FROM leads WHERE phone = :phone LIMIT 1";
        $result = $conn->executeQuery($sql, ['phone' => $phone])->fetchAssociative();

        if ($result) {
            return $this->leadModel->getEntity($result['id']);
        }
        return null;
    }
}
