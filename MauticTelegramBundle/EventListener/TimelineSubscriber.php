<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TimelineSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    public function onTimelineGenerate(LeadTimelineEvent $event): void
    {
        $eventTypeKey  = 'telegram.message';
        $eventTypeName = $this->translator->trans('mautic.telegram.timeline.message');

        $event->addEventType($eventTypeKey, $eventTypeName);

        if (!$event->isApplicable($eventTypeKey) || !$this->telegramMessageLogsTableExists()) {
            return;
        }

        $logs = $this->getTimelineLogs($event->getLeadId(), $event->getQueryOptions());
        $event->addToCounter($eventTypeKey, $logs);

        if ($event->isEngagementCount()) {
            return;
        }

        foreach ($logs['results'] as $log) {
            $status = (string) ($log['status'] ?? 'failed');
            $timestamp = (new DateTimeHelper((string) $log['date_added'], DateTimeHelper::FORMAT_DB, 'local'))->getDateTime();

            $event->addEvent([
                'eventId'       => $eventTypeKey.$log['id'],
                'event'         => $eventTypeKey,
                'eventLabel'    => [
                    'label' => $this->buildEventLabel($log),
                    'href'  => '#',
                ],
                'eventType'     => $eventTypeName,
                'eventPriority' => (int) $log['id'],
                'timestamp'     => $timestamp,
                'extra'         => [
                    'log' => $log,
                ],
                'icon'      => 'ri-telegram-line',
                'contactId' => $log['lead_id'],
                'details'   => [
                    'status'        => $status,
                    'bot'           => $log['bot_name'] ?? null,
                    'message_type'  => $log['message_type'] ?? null,
                    'message_id'    => $log['telegram_message_id'] ?? null,
                    'error'         => $log['error_description'] ?? null,
                    'message_text'  => $log['message_text'] ?? null,
                ],
            ]);
        }
    }

    private function getTimelineLogs(int $leadId, array $queryOptions): array
    {
        $connection = $this->entityManager->getConnection();
        $limit = (int) ($queryOptions['limit'] ?? 25);
        $start = (int) ($queryOptions['start'] ?? 0);

        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM telegram_message_logs WHERE lead_id = :leadId',
            ['leadId' => $leadId]
        );

        $results = $connection->fetchAllAssociative(
            'SELECT l.*, b.name AS bot_name, b.bot_username
                FROM telegram_message_logs l
                LEFT JOIN telegram_bots b ON b.id = l.bot_id
                WHERE l.lead_id = :leadId
                ORDER BY l.date_added DESC, l.id DESC
                LIMIT '.$limit.' OFFSET '.$start,
            ['leadId' => $leadId]
        );

        return [
            'total'   => $total,
            'results' => $results,
        ];
    }

    private function buildEventLabel(array $log): string
    {
        $statusKey = 'sent' === ($log['status'] ?? '') ? 'sent' : 'failed';
        $label = $this->translator->trans('mautic.telegram.timeline.status.'.$statusKey);

        if (!empty($log['bot_name'])) {
            $label .= ': '.$log['bot_name'];
        }

        return $label;
    }

    private function telegramMessageLogsTableExists(): bool
    {
        return (bool) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName',
            ['tableName' => 'telegram_message_logs']
        );
    }
}
