<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\EventListener;

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
        $mediaType       = $config['media_type'] ?? 'none';
        $chatIdField     = $config['chat_id_field'] ?? 'telegram_chat_id';
        $buttons         = $this->parseButtons($config['buttons'] ?? '');

        $chatId = $fields[$chatIdField] ?? $fields['telegram_chat_id'] ?? null;

        if (empty($chatId)) {
            $event->setFailed('No Telegram chat_id for contact');
            return;
        }

        $message = $this->replaceTokens($messageTemplate, $fields);

        try {
            if ($mediaType !== 'none' && !empty($mediaUrl)) {
                $mediaUrlProcessed = $this->replaceTokens($mediaUrl, $fields);
                if ($mediaType === 'photo') {
                    $result = $this->telegramApi->sendPhoto($chatId, $mediaUrlProcessed, $message, $buttons);
                } else {
                    $result = $this->telegramApi->sendDocument($chatId, $mediaUrlProcessed, $message);
                }
            } else {
                $result = $this->telegramApi->sendMessage($chatId, $message, $buttons);
            }

            if ($result['ok'] ?? false) {
                $event->setResult(true);
            } else {
                $event->setResult(['failed' => true, 'reason' => $result['description'] ?? 'Telegram error']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Telegram send error: ' . $e->getMessage());
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

    private function parseButtons(string $buttonsText): array
    {
        if (empty($buttonsText)) return [];
        $buttons = [];
        foreach (explode("\n", trim($buttonsText)) as $line) {
            $parts = explode('|', trim($line), 2);
            if (count($parts) === 2) {
                $buttons[] = ['text' => trim($parts[0]), 'url' => trim($parts[1])];
            }
        }
        return $buttons;
    }
}
