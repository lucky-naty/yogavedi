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
     * РЎРѕР·РґР°РµС‚ РёР»Рё РѕР±РЅРѕРІР»СЏРµС‚ РєРѕРЅС‚Р°РєС‚ Рё СЂРµРіРёСЃС‚СЂРёСЂСѓРµС‚ РµРіРѕ РїРѕРґРїРёСЃРєСѓ РЅР° Р±РѕС‚Р°.
     *
     * @param array $telegramData Р”Р°РЅРЅС‹Рµ РёР· РІРµР±С…СѓРєР° (chat_id, username, etc.)
     * @param int|null $botId ID Р±РѕС‚Р°, Рє РєРѕС‚РѕСЂРѕРјСѓ РїСЂРёРІСЏР·С‹РІР°РµС‚СЃСЏ РєРѕРЅС‚Р°РєС‚
     * @param array $tags РўРµРіРё РґР»СЏ РєРѕРЅС‚Р°РєС‚Р°
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

        // 1. РџРѕРёСЃРє РєРѕРЅС‚Р°РєС‚Р°
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

        // 2. РЎРѕР·РґР°РЅРёРµ РёР»Рё РѕР±РЅРѕРІР»РµРЅРёРµ РїРѕР»РµР№ РєРѕРЅС‚Р°РєС‚Р°
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

        // 3. Р Р•Р“РРЎРўР РђР¦РРЇ РџРћР”РџРРЎРљР РќРђ Р‘РћРўРђ (РќРѕРІР°СЏ Р»РѕРіРёРєР°)
        if ($contact && $botId && $chatId) {
            $this->registerSubscription($contact, (int)$botId, $chatId);
        }

        // 4. РћР±РЅРѕРІР»РµРЅРёРµ С‚РµРіРѕРІ
        if (!empty($tags)) {
            $this->leadModel->modifyTags($contact, $tags, [], false);
            $this->leadModel->saveEntity($contact);
        }

        return $contact->getId();
    }

    /**
     * РЎРѕР·РґР°РµС‚ РёР»Рё РѕР±РЅРѕРІР»СЏРµС‚ СЃРІСЏР·СЊ РєРѕРЅС‚Р°РєС‚Р° СЃ Р±РѕС‚РѕРј РІ С‚Р°Р±Р»РёС†Рµ РїРѕРґРїРёСЃРѕРє
     */
    private function registerSubscription(Lead $contact, int $botId, string $chatId): void
    {
        $repo = $this->em->getRepository(TelegramSubscription::class);

        // РС‰РµРј, РµСЃС‚СЊ Р»Рё СѓР¶Рµ С‚Р°РєР°СЏ РїРѕРґРїРёСЃРєР° (СЌС‚РѕС‚ РєРѕРЅС‚Р°РєС‚ + СЌС‚РѕС‚ Р±РѕС‚)
        $subscription = $repo->findOneBy([
            'bot' => $botId,
            'lead' => $contact->getId()
        ]);

        if (!$subscription) {
            // Р•СЃР»Рё РЅРµС‚ вЂ” СЃРѕР·РґР°РµРј РЅРѕРІСѓСЋ
            $bot = $this->em->getRepository(Bot::class)->find($botId);
            if ($bot) {
                $subscription = new TelegramSubscription($bot, $contact, $chatId);
                $this->em->persist($subscription);
                $this->logger->info("TelegramBots: New subscription created for contact {$contact->getId()} on bot {$botId}");
            }
        } else {
            // Р•СЃР»Рё РµСЃС‚СЊ вЂ” РїСЂРѕСЃС‚Рѕ РѕР±РЅРѕРІР»СЏРµРј chat_id (РЅР° СЃР»СѓС‡Р°Р№ СЃРјРµРЅС‹)
            if ($subscription->getChatId() !== $chatId) {
                $subscription->setChatId($chatId);
            }
        }

        if ($subscription) {
            $this->em->flush();
        }
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
