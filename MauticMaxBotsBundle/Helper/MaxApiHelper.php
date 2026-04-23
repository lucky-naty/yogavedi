<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Helper;

final class MaxApiHelper
{
    private const DEFAULT_API_BASE_URL = 'https://platform-api.max.ru';
    private const DEFAULT_PHONE_REQUEST_BUTTON_TEXT = 'Share phone number';

    public static function buildApiUrlForTest(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    public static function validateBaseUrlForTest(string $baseUrl): string
    {
        self::assertAllowedApiBaseUrl($baseUrl);

        return rtrim($baseUrl, '/');
    }

    public static function getMe(string $token, ?string $apiBaseUrl = null): array
    {
        return self::request('GET', '/me', $token, [], $apiBaseUrl);
    }

    public static function sendTextMessage(string $token, string $chatId, string $text, ?string $apiBaseUrl = null): array
    {
        return self::request('POST', '/messages', $token, self::buildTextPayload($chatId, $text), $apiBaseUrl);
    }

    public static function sendPhoneRequestMessage(string $token, string $chatId, string $text, ?string $apiBaseUrl = null): array
    {
        return self::request('POST', '/messages', $token, self::buildPhoneRequestPayload($chatId, $text), $apiBaseUrl);
    }

    public static function registerWebhook(string $token, string $url, string $secret, ?string $apiBaseUrl = null): array
    {
        return self::request('POST', '/subscriptions', $token, [
            'url'    => $url,
            'secret' => $secret,
        ], $apiBaseUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildTextPayloadForTest(string $chatId, string $text): array
    {
        return self::buildTextPayload($chatId, $text);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildPhoneRequestPayloadForTest(string $chatId, string $text): array
    {
        return self::buildPhoneRequestPayload($chatId, $text);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, string $token, array $payload, ?string $apiBaseUrl = null): array
    {
        $apiBaseUrl = self::normalizeApiBaseUrl($apiBaseUrl);
        $url = self::buildApiUrlForTest($apiBaseUrl, $path);

        $ch = curl_init();
        if (false === $ch) {
            return [
                'ok'          => false,
                'description' => 'Unable to initialize MAX API transport.',
            ];
        }

        $headers = [
            'Authorization: '.$token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ('GET' === $method) {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ('' !== $error) {
            return [
                'ok'          => false,
                'description' => $error,
            ];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return [
                'ok'          => $httpCode >= 200 && $httpCode < 300,
                'status'      => $httpCode,
                'description' => null === $response || '' === $response ? 'Empty MAX API response.' : 'Unexpected MAX API response.',
                'response'    => $response,
            ];
        }

        if (!array_key_exists('status', $decoded)) {
            $decoded['status'] = $httpCode;
        }

        if (!array_key_exists('ok', $decoded)) {
            $decoded['ok'] = $httpCode >= 200 && $httpCode < 300;
        }

        return $decoded;
    }

    private static function normalizeApiBaseUrl(?string $apiBaseUrl): string
    {
        $apiBaseUrl = trim((string) $apiBaseUrl);
        if ('' === $apiBaseUrl) {
            $apiBaseUrl = self::DEFAULT_API_BASE_URL;
        }

        return self::validateBaseUrlForTest($apiBaseUrl);
    }

    private static function assertAllowedApiBaseUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid MAX API URL.');
        }

        if ('https' !== strtolower((string) $parts['scheme'])) {
            throw new \InvalidArgumentException('MAX base URL must use https');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('MAX base URL must not contain credentials');
        }

        $host = strtolower((string) $parts['host']);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new \InvalidArgumentException('MAX base URL must not target localhost');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && false === filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            throw new \InvalidArgumentException('MAX base URL must not target private or reserved IP ranges.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildTextPayload(string $chatId, string $text): array
    {
        $chatId = trim($chatId);
        $text = trim($text);

        if ('' === $chatId) {
            throw new \InvalidArgumentException('chat_id is required.');
        }

        if ('' === $text) {
            throw new \InvalidArgumentException('text is required.');
        }

        return [
            'chat_id' => $chatId,
            'text' => $text,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPhoneRequestPayload(string $chatId, string $text): array
    {
        $payload = self::buildTextPayload($chatId, $text);
        $payload['reply_markup'] = [
            'keyboard' => [
                [
                    [
                        'text' => self::DEFAULT_PHONE_REQUEST_BUTTON_TEXT,
                        'request_contact' => true,
                    ],
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];

        return $payload;
    }
}
