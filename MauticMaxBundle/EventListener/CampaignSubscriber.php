<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use MauticPlugin\MauticMaxBundle\Form\Type\MaxSendMessageType;
use MauticPlugin\MauticMaxBundle\Helper\MaxMessageHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public const ACTION_SEND_MESSAGE = 'max.send_message';
    public const EVENT_NAME = 'mautic.max.campaign.send_message';

    public function __construct(
        private MaxMessageHelper $messageHelper,
        private EntityManagerInterface $entityManager,
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
                'label'       => 'mautic.max.campaign.action.send_message',
                'description' => 'mautic.max.campaign.action.send_message.desc',
                'eventName'   => self::EVENT_NAME,
                'formType'    => MaxSendMessageType::class,
                'channel'     => 'max',
            ]
        );
    }

    public function onSendMessage(CampaignExecutionEvent $event): void
    {
        $config = $event->getConfig();
        $lead   = $event->getLead();
        $fields = $lead->getProfileFields();

        $messageTemplate = trim((string) ($config['message'] ?? ''));
        if ('' === $messageTemplate) {
            $event->setFailed('MAX message is empty.');

            return;
        }

        $recipients = $this->resolveRecipients($event, $config, $fields);
        if ([] === $recipients) {
            $this->logSendAttempt($event, null, [
                'chat_id'           => '',
                'message_text'      => $messageTemplate,
                'status'            => 'failed',
                'error_description' => 'No active MAX subscription for contact',
            ]);
            $event->setFailed('No active MAX subscription for contact');

            return;
        }

        $message = $this->replaceTokens($messageTemplate, $fields);
        $sent = 0;
        $failures = [];

        foreach ($recipients as $recipient) {
            $botId = (int) $recipient['bot_id'];
            $chatId = (string) $recipient['chat_id'];

            try {
                $result = $this->messageHelper->sendTextToSubscription($botId, $chatId, $message);
            } catch (\Throwable $exception) {
                $result = [
                    'ok'          => false,
                    'description' => $exception->getMessage(),
                ];
            }

            $statusCode = (int) ($result['status'] ?? 0);
            $isSuccessful = false !== ($result['ok'] ?? false) && (0 === $statusCode || $statusCode < 400);

            $this->logSendAttempt($event, $botId, [
                'chat_id'           => $chatId,
                'message_text'      => $message,
                'status'            => $isSuccessful ? 'sent' : 'failed',
                'error_code'        => $result['error_code'] ?? $result['code'] ?? ($statusCode > 0 ? (string) $statusCode : null),
                'error_description' => $result['description'] ?? $result['message'] ?? null,
            ]);

            if ($isSuccessful) {
                ++$sent;
                continue;
            }

            if ($this->messageHelper->isRecipientUnavailable($result)) {
                $this->messageHelper->deactivateSubscription($botId, $chatId);
            }

            $failures[] = (string) ($result['description'] ?? $result['message'] ?? 'MAX error');
        }

        if ($sent > 0) {
            $event->setResult([
                'sent'   => $sent,
                'failed' => count($failures),
            ]);

            return;
        }

        $event->setFailed(implode('; ', array_unique($failures)) ?: 'MAX error');
    }

    private function resolveRecipients(CampaignExecutionEvent $event, array $config, array $fields): array
    {
        if (!$this->tableExists('max_subscriptions') || !$this->tableExists('max_bots')) {
            return [];
        }

        $leadId = $event->getLead()?->getId();
        if (empty($leadId)) {
            return [];
        }

        $scope = (string) ($config['send_scope'] ?? 'first_subscribed');
        $botSelection = trim((string) ($config['bot_selection'] ?? ''));
        $selectedBotId = 'selected_only' === $scope && '' !== $botSelection ? (int) $botSelection : null;

        if ('selected_only' === $scope && null === $selectedBotId) {
            return [];
        }

        $connection = $this->entityManager->getConnection();
        $params = ['leadId' => (int) $leadId];
        $sql = 'SELECT s.bot_id, s.chat_id
                  FROM max_subscriptions s
                  INNER JOIN max_bots b ON b.id = s.bot_id
                 WHERE s.lead_id = :leadId
                   AND s.is_active = 1
                   AND b.is_published = 1
                   AND b.is_active = 1';

        if (null !== $selectedBotId) {
            $sql .= ' AND s.bot_id = :botId';
            $params['botId'] = $selectedBotId;
        }

        $sql .= ' ORDER BY s.id ASC';

        if ('all_subscribed' !== $scope) {
            $sql .= ' LIMIT 1';
        }

        $recipients = $connection->fetchAllAssociative($sql, $params);
        if ([] !== $recipients) {
            return $recipients;
        }

        $chatId = trim((string) ($fields['max_chat_id'] ?? ''));
        if ('' === $chatId) {
            return [];
        }

        $params = ['chatId' => $chatId];
        $sql = 'SELECT s.bot_id, s.chat_id
                  FROM max_subscriptions s
                  INNER JOIN max_bots b ON b.id = s.bot_id
                 WHERE s.chat_id = :chatId
                   AND s.is_active = 1
                   AND b.is_published = 1
                   AND b.is_active = 1';

        if (null !== $selectedBotId) {
            $sql .= ' AND s.bot_id = :botId';
            $params['botId'] = $selectedBotId;
        }

        $sql .= ' ORDER BY s.id ASC';

        if ('all_subscribed' !== $scope) {
            $sql .= ' LIMIT 1';
        }

        return $connection->fetchAllAssociative($sql, $params);
    }

    private function replaceTokens(string $text, array $fields): string
    {
        foreach ($fields as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $text = str_replace(['{'.$key.'}', '{contact.'.$key.'}'], (string) $value, $text);
            }
        }

        return $text;
    }

    private function logSendAttempt(CampaignExecutionEvent $event, ?int $botId, array $data): void
    {
        if (!$this->tableExists('max_message_logs')) {
            return;
        }

        $lead = $event->getLead();
        $eventDetails = method_exists($event, 'getEvent') ? $event->getEvent() : [];
        $campaignId = is_array($eventDetails) && isset($eventDetails['campaign_id']) ? (int) $eventDetails['campaign_id'] : null;
        $eventId = is_array($eventDetails) && isset($eventDetails['id']) ? (int) $eventDetails['id'] : null;

        if (null === $campaignId && null !== $eventId && $this->tableExists('campaign_events')) {
            $campaignId = $this->entityManager->getConnection()->fetchOne(
                'SELECT campaign_id FROM campaign_events WHERE id = :eventId',
                ['eventId' => $eventId]
            ) ?: null;
            $campaignId = null !== $campaignId ? (int) $campaignId : null;
        }

        $this->entityManager->getConnection()->insert('max_message_logs', [
            'lead_id'           => $lead?->getId(),
            'campaign_id'       => $campaignId,
            'event_id'          => $eventId,
            'bot_id'            => $botId,
            'chat_id'           => (string) ($data['chat_id'] ?? ''),
            'message_text'      => $data['message_text'] ?? null,
            'status'            => (string) ($data['status'] ?? 'failed'),
            'error_code'        => isset($data['error_code']) ? (string) $data['error_code'] : null,
            'error_description' => $data['error_description'] ?? null,
            'date_added'        => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName',
            ['tableName' => $tableName]
        );
    }
}
