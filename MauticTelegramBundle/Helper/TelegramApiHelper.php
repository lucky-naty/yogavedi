<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Psr\Log\LoggerInterface;

class TelegramApiHelper
{
    private ?string $botToken = null;
    private ?string $parseMode = null;
    private ?int $botId = null;
    private string $apiBaseUrl = '';

    public function __construct(
        private IntegrationHelper $integrationHelper,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
    ) {
    }

    private function getIntegrationSettings(?int $botId = null, ?string $parseMode = null): void
    {
        if ($this->botToken !== null && $this->botId === $botId) {
            if (null !== $parseMode) {
                $this->parseMode = $parseMode ?: null;
            }
            return;
        }

        if ($botId) {
            $this->loadSettingsFromBotId($botId, $parseMode);
            return;
        }

        $integration = $this->integrationHelper->getIntegrationObject('Telegram');
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            $this->loadSettingsFromPublishedBot($parseMode);
            return;
        }

        $keys = $integration->getDecryptedApiKeys();
        $this->botToken  = $keys['bot_token'] ?? null;
        $this->botId     = null;
        $this->parseMode = $parseMode ?? ($keys['parse_mode'] ?? 'HTML');
        $this->apiBaseUrl = (string) ($keys['api_base_url'] ?? '');

        if (empty($this->botToken)) {
            $this->loadSettingsFromPublishedBot($parseMode);
        }
    }

    private function loadSettingsFromPublishedBot(?string $parseMode = null): void
    {
        $connection = $this->entityManager->getConnection();
        $apiBaseUrlSelect = $this->telegramBotsColumnExists('api_base_url') ? ', api_base_url' : '';
        $row = $connection->fetchAssociative(
            sprintf('SELECT id, token%s FROM telegram_bots WHERE is_published = 1 ORDER BY id ASC LIMIT 1', $apiBaseUrlSelect)
        );

        if (!$row || empty($row['token'])) {
            throw new \RuntimeException('Telegram integration is not configured and no published Telegram bot was found.');
        }

        $this->botId     = (int) $row['id'];
        $this->botToken  = (string) $row['token'];
        $this->parseMode = $parseMode ?: 'HTML';
        $this->apiBaseUrl = (string) ($row['api_base_url'] ?? '');
    }

    private function loadSettingsFromBotId(int $botId, ?string $parseMode = null): void
    {
        $connection = $this->entityManager->getConnection();
        $apiBaseUrlSelect = $this->telegramBotsColumnExists('api_base_url') ? ', api_base_url' : '';
        $row = $connection->fetchAssociative(
            sprintf('SELECT id, token%s FROM telegram_bots WHERE id = :id AND is_published = 1', $apiBaseUrlSelect),
            ['id' => $botId]
        );

        if (!$row || empty($row['token'])) {
            throw new \RuntimeException(sprintf('Published Telegram bot %d was not found.', $botId));
        }

        $this->botId     = (int) $row['id'];
        $this->botToken  = (string) $row['token'];
        $this->parseMode = $parseMode ?: 'HTML';
        $this->apiBaseUrl = (string) ($row['api_base_url'] ?? '');
    }

    public function getSelectedBotId(): ?int
    {
        return $this->botId;
    }

    public function send(array $message): array
    {
        $botId = isset($message['bot_id']) && '' !== (string) $message['bot_id'] ? (int) $message['bot_id'] : null;
        $this->getIntegrationSettings($botId, $message['parse_mode'] ?? null);

        $type   = $message['type'] ?? 'text';
        $text   = (string) ($message['text'] ?? '');
        $params = $this->buildCommonParams($message);

        return match ($type) {
            'text'      => $this->request('sendMessage', $params + ['text' => $text]),
            'photo'     => $this->request('sendPhoto', $params + ['photo' => $message['media_url'], 'caption' => $text]),
            'document'  => $this->request('sendDocument', $params + ['document' => $message['media_url'], 'caption' => $text]),
            'video'     => $this->request('sendVideo', $params + ['video' => $message['media_url'], 'caption' => $text]),
            'audio'     => $this->request('sendAudio', $params + ['audio' => $message['media_url'], 'caption' => $text]),
            'voice'     => $this->request('sendVoice', $params + ['voice' => $message['media_url'], 'caption' => $text]),
            'animation' => $this->request('sendAnimation', $params + ['animation' => $message['media_url'], 'caption' => $text]),
            default     => ['ok' => false, 'description' => sprintf('Unsupported Telegram message type: %s', $type)],
        };
    }

    private function buildCommonParams(array $message): array
    {
        $params = [
            'chat_id' => (string) $message['chat_id'],
        ];

        if (!empty($this->parseMode)) {
            $params['parse_mode'] = $this->parseMode;
        }

        if (!empty($message['disable_notification'])) {
            $params['disable_notification'] = true;
        }

        if (!empty($message['protect_content'])) {
            $params['protect_content'] = true;
        }

        if (!empty($message['disable_web_page_preview']) && 'text' === ($message['type'] ?? 'text')) {
            $params['link_preview_options'] = json_encode(['is_disabled' => true]);
        }

        if (!empty($message['buttons'])) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $this->buildInlineKeyboard($message['buttons']),
            ]);
        }

        return $params;
    }

    /**
     * Send a text message to a chat_id
     */
    public function sendMessage(string $chatId, string $text, array $buttons = []): array
    {
        $this->getIntegrationSettings();

        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => $this->parseMode,
        ];

        if (!empty($buttons)) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $this->buildInlineKeyboard($buttons),
            ]);
        }

        return $this->request('sendMessage', $params);
    }

    /**
     * Send a photo with optional caption
     */
    public function sendPhoto(string $chatId, string $photoUrl, string $caption = '', array $buttons = []): array
    {
        $this->getIntegrationSettings();

        $params = [
            'chat_id'    => $chatId,
            'photo'      => $photoUrl,
            'caption'    => $caption,
            'parse_mode' => $this->parseMode,
        ];

        if (!empty($buttons)) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $this->buildInlineKeyboard($buttons),
            ]);
        }

        return $this->request('sendPhoto', $params);
    }

    /**
     * Send a document/file
     */
    public function sendDocument(string $chatId, string $documentUrl, string $caption = ''): array
    {
        $this->getIntegrationSettings();

        return $this->request('sendDocument', [
            'chat_id'    => $chatId,
            'document'   => $documentUrl,
            'caption'    => $caption,
            'parse_mode' => $this->parseMode,
        ]);
    }

    /**
     * Build inline keyboard from buttons config
     * buttons format: [['text' => 'Button', 'url' => 'https://...'], ...]
     */
    private function buildInlineKeyboard(array $buttons): array
    {
        $keyboard = [];
        $row = [];

        foreach ($buttons as $button) {
            $btn = ['text' => $button['text']];

            if (!empty($button['url'])) {
                $btn['url'] = $button['url'];
            } elseif (!empty($button['callback_data'])) {
                $btn['callback_data'] = $button['callback_data'];
            }

            $row[] = $btn;

            // Max 2 buttons per row
            if (count($row) >= 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }

        if (!empty($row)) {
            $keyboard[] = $row;
        }

        return $keyboard;
    }

    /**
     * Make API request to Telegram
     */
    private function request(string $method, array $params): array
    {
        $url = self::buildTelegramApiUrl($this->apiBaseUrl, (string) $this->botToken, $method);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('Telegram API cURL error: ' . $error);
            return ['ok' => false, 'description' => $error];
        }

        $result = json_decode($response, true);

        if (!($result['ok'] ?? false)) {
            $this->logger->error('Telegram API error: ' . ($result['description'] ?? 'Unknown error'), [
                'method' => $method,
                'params' => array_diff_key($params, ['chat_id' => '']),
            ]);
        }

        return $result;
    }

    /**
     * Get bot info to verify token
     */
    public function getMe(): array
    {
        $this->getIntegrationSettings();
        return $this->request('getMe', []);
    }

    public static function buildTelegramApiUrl(string $apiBaseUrl, string $token, string $method): string
    {
        $apiBaseUrl = trim($apiBaseUrl) ?: 'https://api.telegram.org';
        $apiBaseUrl = rtrim($apiBaseUrl, '/');

        if (!str_ends_with($apiBaseUrl, '/bot')) {
            $apiBaseUrl .= '/bot';
        }

        return $apiBaseUrl.$token.'/'.$method;
    }

    private function telegramBotsColumnExists(string $columnName): bool
    {
        try {
            return $this->entityManager
                ->getConnection()
                ->createSchemaManager()
                ->introspectTable('telegram_bots')
                ->hasColumn($columnName);
        } catch (\Throwable) {
            return false;
        }
    }
}
