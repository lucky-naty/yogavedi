<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Helper;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class ContactManager
{
    public function __construct(
        private LeadModel $leadModel,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
    ) {
    }

    public function createOrUpdate(array $telegramData, array $tags = []): ?int
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

        // Имя и фамилию обновляем только если у контакта их нет
        $nameFields = ['firstname', 'lastname'];

        // Ищем через прямой SQL
        $contact = null;

        if ($chatId) {
            $contact = $this->findByChatId($chatId);
        }

        // Если пришёл телефон - ищем существующий контакт по телефону
        if ($phone) {
            $contactByPhone = $this->findByPhone($phone);

            if ($contactByPhone && $contact && $contactByPhone->getId() !== $contact->getId()) {
                // Есть два разных контакта - объединяем через прямой SQL (быстро)
                $this->logger->info('TelegramBots: Merging contacts - phone contact ' . $contactByPhone->getId() . ' wins over chat contact ' . $contact->getId());
                $newContactId = $contact->getId();
                $contact = $contactByPhone;
                // Удаляем дубликат напрямую через SQL
                $conn = $this->em->getConnection();
                $conn->executeStatement("DELETE FROM leads WHERE id = :id", ['id' => $newContactId]);
            } elseif ($contactByPhone && !$contact) {
                // Нашли только по телефону
                $contact = $contactByPhone;
            }
        }

        if ($contact) {
            $this->logger->info('TelegramBots: Updating contact ID ' . $contact->getId());
            // Не перезаписываем имя/фамилию если они уже есть
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

        if (!empty($tags)) {
            $this->leadModel->modifyTags($contact, $tags, [], false);
            $this->leadModel->saveEntity($contact);
        }

        return $contact->getId();
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
