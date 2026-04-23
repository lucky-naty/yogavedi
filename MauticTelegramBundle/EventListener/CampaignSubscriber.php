<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticTelegramBundle\Form\Type\TelegramSendMessageType;
use MauticPlugin\MauticTelegramBundle\Helper\TelegramApiHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public const ACTION_SEND_MESSAGE = 'telegram.send_message';
    public const EVENT_NAME = 'mautic.telegram.campaign.send_message';

    public function __construct(
        private TelegramApiHelper $telegramApi,
        private LeadModel $leadModel,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            self::EVENT_NAME                  => ['onSendMessage', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        $event->addAction(
            self::ACTION_SEND_MESSAGE,
            [
                'label'       => 'mautic.telegram.campaign.action.send_message',
                'description' => 'mautic.telegram.campaign.action.send_message.desc',
                'eventName'   => self::EVENT_NAME,
                'formType'    => TelegramSendMessageType::class,
                'channel'     => 'telegram',
            ]
        );
    }

    public function onSendMessage(CampaignExecutionEvent $event): void
    {
        $config  = $event->getConfig();
        $lead    = $event->getLead();
        $fields  = $lead->getProfileFields();

        $messageTemplate = $config['message'] ?? '';
        $mediaUrl        = $config['media_url'] ?? '';
        $mediaType       = $config['media_type'] ?? 'text';
        if ('none' === $mediaType) {
            $mediaType = 'text';
        }
        $buttons         = $this->parseButtons($config);
        $recipients      = $this->resolveRecipients($event, $config, $fields);

        if ([] === $recipients) {
            $this->logSendAttempt($event, null, [
                'chat_id'           => '',
                'message_type'      => $mediaType,
                'message_text'      => $messageTemplate,
                'status'            => 'failed',
                'error_description' => 'No active Telegram subscription for contact',
            ]);
            $event->setFailed('No active Telegram subscription for contact');
            return;
        }

        $message = $this->replaceTokens($messageTemplate, $fields);
        $mediaUrlProcessed = !empty($mediaUrl) ? $this->replaceTokens($mediaUrl, $fields) : '';

        try {
            if ('text' !== $mediaType && empty($mediaUrlProcessed)) {
                throw new \RuntimeException(sprintf('Media URL is required for Telegram %s message.', $mediaType));
            }

            $sent = 0;
            $failures = [];

            foreach ($recipients as $recipient) {
                $result = $this->telegramApi->send([
                    'bot_id'                   => $recipient['bot_id'],
                    'chat_id'                  => $recipient['chat_id'],
                    'type'                     => $mediaType,
                    'text'                     => $message,
                    'media_url'                => $mediaUrlProcessed,
                    'parse_mode'               => $config['parse_mode'] ?? 'HTML',
                    'buttons'                  => $buttons,
                    'disable_notification'     => !empty($config['disable_notification']),
                    'protect_content'          => !empty($config['protect_content']),
                    'disable_web_page_preview' => !empty($config['disable_web_page_preview']),
                ]);

                $this->logSendAttempt($event, (int) $recipient['bot_id'], [
                    'chat_id'             => $recipient['chat_id'],
                    'message_type'        => $mediaType,
                    'message_text'        => $message,
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                    'status'              => ($result['ok'] ?? false) ? 'sent' : 'failed',
                    'error_code'          => $result['error_code'] ?? null,
                    'error_description'   => $result['description'] ?? null,
                ]);

                if ($this->isBlockedByUser($result)) {
                    $this->markSubscriptionInactive((int) $recipient['bot_id'], (string) $recipient['chat_id']);
                }

                if ($result['ok'] ?? false) {
                    ++$sent;
                } else {
                    $failures[] = $result['description'] ?? 'Telegram error';
                }
            }

            if ($sent > 0) {
                $event->setResult(['sent' => $sent, 'failed' => count($failures)]);
            } else {
                $event->setResult(['failed' => true, 'reason' => implode('; ', array_unique($failures)) ?: 'Telegram error']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Telegram send error: ' . $e->getMessage());
            $this->logSendAttempt($event, null, [
                'chat_id'           => '',
                'message_type'      => $mediaType,
                'message_text'      => $message,
                'status'            => 'failed',
                'error_description' => $e->getMessage(),
            ]);
            $event->setFailed($e->getMessage());
        }
    }

    private function replaceTokens(string $text, array $fields): string
    {
        foreach ($fields as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $text = str_replace(['{' . $key . '}', '{contact.' . $key . '}'], (string) $value, $text);
            }
        }
        return $text;
    }

    private function parseButtons(array $config): array
    {
        $structuredButtons = [];
        for ($index = 1; $index <= 3; ++$index) {
            $text  = trim((string) ($config['button_'.$index.'_text'] ?? ''));
            $type  = (string) ($config['button_'.$index.'_type'] ?? 'url');
            $value = trim((string) ($config['button_'.$index.'_value'] ?? ''));

            if ('' === $text || '' === $value) {
                continue;
            }

            $button = ['text' => $text];
            if ('callback' === $type) {
                $button['callback_data'] = $value;
            } else {
                $button['url'] = $value;
            }

            $structuredButtons[] = $button;
        }

        if ([] !== $structuredButtons) {
            return $structuredButtons;
        }

        $buttonsText = (string) ($config['buttons'] ?? '');
        if (empty($buttonsText)) {
            return [];
        }

        $buttons = [];
        foreach (explode("\n", trim($buttonsText)) as $line) {
            $parts = explode('|', trim($line), 2);
            if (count($parts) === 2) {
                $value = trim($parts[1]);
                $button = ['text' => trim($parts[0])];

                if (str_starts_with($value, 'callback:')) {
                    $button['callback_data'] = substr($value, 9);
                } else {
                    $button['url'] = $value;
                }

                $buttons[] = $button;
            } elseif ('' !== trim($line)) {
                $this->logger->warning('Telegram button config line ignored because it is malformed', [
                    'line' => $line,
                ]);
            }
        }
        return $buttons;
    }

    private function resolveRecipients(CampaignExecutionEvent $event, array $config, array $fields): array
    {
        if (!$this->telegramSubscriptionsTableExists()) {
            return [];
        }

        $leadId = $event->getLead()?->getId();
        if (empty($leadId)) {
            return [];
        }

        $scope = (string) ($config['send_scope'] ?? 'first_subscribed');
        $botSelection = (string) ($config['bot_selection'] ?? ($config['bot_id'] ?? ''));
        $selectedBotId = 'selected_only' === $scope && '' !== $botSelection && 'auto' !== $botSelection
            ? (int) $botSelection
            : null;

        if ('selected_only' === $scope && null === $selectedBotId) {
            return [];
        }

        $params = ['leadId' => (int) $leadId];
        $sql = 'SELECT s.bot_id, s.chat_id
            FROM telegram_subscriptions s
            INNER JOIN telegram_bots b ON b.id = s.bot_id
            WHERE s.lead_id = :leadId AND s.is_active = 1 AND b.is_published = 1';

        if (null !== $selectedBotId) {
            $sql .= ' AND s.bot_id = :botId';
            $params['botId'] = $selectedBotId;
        }

        $sql .= ' ORDER BY s.id ASC';

        if ('all_subscribed' !== $scope) {
            $sql .= ' LIMIT 1';
        }

        $recipients = $this->em->getConnection()->fetchAllAssociative($sql, $params);
        if ([] !== $recipients) {
            return $recipients;
        }

        $chatId = (string) ($fields['telegram_chat_id'] ?? '');
        if ('' === $chatId) {
            return [];
        }

        $params = ['chatId' => $chatId];
        $sql = 'SELECT s.bot_id, s.chat_id
            FROM telegram_subscriptions s
            INNER JOIN telegram_bots b ON b.id = s.bot_id
            WHERE s.chat_id = :chatId AND s.is_active = 1 AND b.is_published = 1';

        if (null !== $selectedBotId) {
            $sql .= ' AND s.bot_id = :botId';
            $params['botId'] = $selectedBotId;
        }

        $sql .= ' ORDER BY s.id ASC';

        if ('all_subscribed' !== $scope) {
            $sql .= ' LIMIT 1';
        }

        return $this->em->getConnection()->fetchAllAssociative($sql, $params);
    }

    private function isBlockedByUser(array $telegramResult): bool
    {
        $description = strtolower((string) ($telegramResult['description'] ?? ''));

        return false === ($telegramResult['ok'] ?? false)
            && 403 === (int) ($telegramResult['error_code'] ?? 0)
            && str_contains($description, 'blocked');
    }

    private function isActiveTelegramSubscriber(string $chatId): bool
    {
        $connection = $this->em->getConnection();

        if (!$this->telegramSubscriptionsTableExists()) {
            return true;
        }

        $status = $connection->fetchOne(
            'SELECT is_active FROM telegram_subscriptions WHERE chat_id = :chatId ORDER BY is_active DESC LIMIT 1',
            ['chatId' => $chatId]
        );

        return false === $status || (bool) $status;
    }

    private function markSubscriptionInactive(int $botId, string $chatId): void
    {
        if (!$this->telegramSubscriptionsTableExists()) {
            return;
        }

        $this->em->getConnection()->executeStatement(
            'UPDATE telegram_subscriptions SET is_active = 0, unsubscribed_at = NOW() WHERE bot_id = :botId AND chat_id = :chatId AND is_active = 1',
            ['botId' => $botId, 'chatId' => $chatId]
        );

        $this->logger->info('Telegram campaign subscriber deactivated after blocked response', [
            'bot_id'  => $botId,
            'chat_id' => $chatId,
        ]);
    }

    private function telegramSubscriptionsTableExists(): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName',
            ['tableName' => 'telegram_subscriptions']
        );
    }

    private function logSendAttempt(CampaignExecutionEvent $event, ?int $botId, array $data): void
    {
        if (!$this->telegramMessageLogsTableExists()) {
            return;
        }

        $lead = $event->getLead();
        $eventDetails = method_exists($event, 'getEvent') ? $event->getEvent() : [];
        $campaignId = is_array($eventDetails) && isset($eventDetails['campaign_id']) ? (int) $eventDetails['campaign_id'] : null;
        $eventId = is_array($eventDetails) && isset($eventDetails['id']) ? (int) $eventDetails['id'] : null;
        if (null === $campaignId && null !== $eventId) {
            $campaignId = $this->em->getConnection()->fetchOne(
                'SELECT campaign_id FROM campaign_events WHERE id = :eventId',
                ['eventId' => $eventId]
            ) ?: null;
            $campaignId = null !== $campaignId ? (int) $campaignId : null;
        }

        $this->em->getConnection()->insert('telegram_message_logs', [
            'lead_id'             => $lead?->getId(),
            'campaign_id'         => $campaignId,
            'event_id'            => $eventId,
            'bot_id'              => $botId,
            'chat_id'             => (string) ($data['chat_id'] ?? ''),
            'message_type'        => (string) ($data['message_type'] ?? 'text'),
            'message_text'        => $data['message_text'] ?? null,
            'telegram_message_id' => isset($data['telegram_message_id']) ? (int) $data['telegram_message_id'] : null,
            'status'              => (string) ($data['status'] ?? 'failed'),
            'error_code'          => isset($data['error_code']) ? (int) $data['error_code'] : null,
            'error_description'   => $data['error_description'] ?? null,
            'date_added'          => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function telegramMessageLogsTableExists(): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName',
            ['tableName' => 'telegram_message_logs']
        );
    }
}
