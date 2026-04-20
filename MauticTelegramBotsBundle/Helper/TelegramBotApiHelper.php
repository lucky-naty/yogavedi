<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Helper;

use Psr\Log\LoggerInterface;

class TelegramBotApiHelper
{
    private string $apiBase = 'https://round-term-e233.shamaeva-natalija.workers.dev/bot';

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function sendMessage(string $token, int|string $chatId, string $text, array $replyMarkup = []): array
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if (!empty($replyMarkup)) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request($token, 'sendMessage', $params);
    }

    public function setWebhook(string $token, string $webhookUrl, string $secret = ''): array
    {
        $params = ['url' => $webhookUrl];
        if (!empty($secret)) {
            $params['secret_token'] = $secret;
        }
        return $this->request($token, 'setWebhook', $params);
    }

    public function deleteWebhook(string $token): array
    {
        return $this->request($token, 'deleteWebhook', []);
    }

    public function getWebhookInfo(string $token): array
    {
        return $this->request($token, 'getWebhookInfo', []);
    }

    public function getMe(string $token): array
    {
        return $this->request($token, 'getMe', []);
    }

    public function answerCallbackQuery(string $token, string $queryId, string $text = ''): array
    {
        return $this->request($token, 'answerCallbackQuery', [
            'callback_query_id' => $queryId,
            'text'              => $text,
        ]);
    }

    public function buildContactKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '📱 Поделиться номером', 'request_contact' => true]],
                [['text' => '❌ Пропустить']],
            ],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ];
    }

    public function removeKeyboard(): array
    {
        return ['remove_keyboard' => true];
    }

    private function request(string $token, string $method, array $params): array
    {
        $url = $this->apiBase . $token . '/' . $method;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
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
}
