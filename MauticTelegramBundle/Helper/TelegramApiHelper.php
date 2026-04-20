<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\Helper;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use Psr\Log\LoggerInterface;

class TelegramApiHelper
{
    private ?string $botToken = null;
    private ?string $parseMode = null;
    private string $apiUrl = 'https://round-term-e233.shamaeva-natalija.workers.dev/bot';

    public function __construct(
        private IntegrationHelper $integrationHelper,
        private LoggerInterface $logger,
    ) {
    }

    private function getIntegrationSettings(): void
    {
        if ($this->botToken !== null) {
            return;
        }

        $integration = $this->integrationHelper->getIntegrationObject('Telegram');
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            throw new \RuntimeException('Telegram integration is not configured or disabled.');
        }

        $keys = $integration->getDecryptedApiKeys();
        $this->botToken  = $keys['bot_token'] ?? null;
        $this->parseMode = $keys['parse_mode'] ?? 'HTML';

        if (empty($this->botToken)) {
            throw new \RuntimeException('Telegram Bot Token is not set.');
        }
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
        $url = $this->apiUrl . $this->botToken . '/' . $method;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
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
}
