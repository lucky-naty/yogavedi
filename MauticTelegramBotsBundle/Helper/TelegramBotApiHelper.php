<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Helper;

use Psr\Log\LoggerInterface;

class TelegramBotApiHelper
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function sendMessage(string $token, int|string $chatId, string $text, array $replyMarkup = [], string $apiBaseUrl = ''): array
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if (!empty($replyMarkup)) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request($token, 'sendMessage', $params, $apiBaseUrl);
    }

    public function setWebhook(string $token, string $webhookUrl, string $secret = '', string $apiBaseUrl = ''): array
    {
        $params = ['url' => $webhookUrl];
        if (!empty($secret)) {
            $params['secret_token'] = $secret;
        }

        return $this->request($token, 'setWebhook', $params, $apiBaseUrl);
    }

    public function deleteWebhook(string $token, string $apiBaseUrl = ''): array
    {
        return $this->request($token, 'deleteWebhook', [], $apiBaseUrl);
    }

    public function getWebhookInfo(string $token, string $apiBaseUrl = ''): array
    {
        return $this->request($token, 'getWebhookInfo', [], $apiBaseUrl);
    }

    public function getMe(string $token, string $apiBaseUrl = ''): array
    {
        return $this->request($token, 'getMe', [], $apiBaseUrl);
    }

    public function answerCallbackQuery(string $token, string $queryId, string $text = '', string $apiBaseUrl = ''): array
    {
        return $this->request($token, 'answerCallbackQuery', [
            'callback_query_id' => $queryId,
            'text'              => $text,
        ], $apiBaseUrl);
    }

    public function buildContactKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => 'Share phone number', 'request_contact' => true]],
                [['text' => 'Skip']],
            ],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ];
    }

    public function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    private function request(string $token, string $method, array $params, string $apiBaseUrl = ''): array
    {
        $url = self::buildTelegramApiUrl($apiBaseUrl, $token, $method);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error('Telegram API error: ' . $error);
            return ['ok' => false, 'description' => $error];
        }

        $result = json_decode($response, true) ?? [];

        if (!($result['ok'] ?? false)) {
            $this->logger->warning('Telegram API returned error: ' . ($result['description'] ?? 'unknown'), [
                'method' => $method,
            ]);
        }

        return $result;
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
}
