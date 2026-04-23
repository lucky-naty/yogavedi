<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Helper;

final class WebhookPayloadParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $payload): array
    {
        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        $eventId = $this->requiredString($decoded, 'event_id');
        $chatId = $this->requiredString($decoded['chat'] ?? [], 'id', 'chat_id');

        $parsed = [
            'event_id' => $eventId,
            'event_type' => (string) ($decoded['type'] ?? 'unknown'),
            'chat_id' => $chatId,
            'raw' => $decoded,
        ];

        $this->addOptionalField($parsed, $decoded['user'] ?? [], 'id', 'user_id');
        $this->addOptionalField($parsed, $decoded['user'] ?? [], 'username');
        $this->addOptionalField($parsed, $decoded['user'] ?? [], 'first_name');
        $this->addOptionalField($parsed, $decoded['user'] ?? [], 'last_name');
        $this->addOptionalField($parsed, $decoded['user'] ?? [], 'language_code');
        $this->addOptionalField($parsed, $decoded['message'] ?? [], 'text');
        $this->addOptionalField($parsed, $decoded['contact'] ?? [], 'phone_number', 'phone');

        return $parsed;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $target
     */
    private function addOptionalField(array &$target, array $source, string $sourceKey, ?string $targetKey = null): void
    {
        $targetKey ??= $sourceKey;

        if (!array_key_exists($sourceKey, $source)) {
            return;
        }

        $value = trim((string) $source[$sourceKey]);
        if ('' === $value) {
            return;
        }

        $target[$targetKey] = $value;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function requiredString(array $source, string $key, ?string $label = null): string
    {
        if (!array_key_exists($key, $source)) {
            throw new \InvalidArgumentException(sprintf('%s is required.', $label ?? $key));
        }

        $value = trim((string) $source[$key]);
        if ('' === $value) {
            throw new \InvalidArgumentException(sprintf('%s is required.', $label ?? $key));
        }

        return $value;
    }
}
