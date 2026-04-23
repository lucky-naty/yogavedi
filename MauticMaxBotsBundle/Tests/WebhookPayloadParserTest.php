<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Helper\WebhookPayloadParser;

require_once __DIR__.'/../Helper/WebhookPayloadParser.php';

$payload = json_encode([
    'event_id' => 'evt-1',
    'type' => 'message',
    'chat' => ['id' => 'chat-77'],
    'user' => ['id' => 'user-1', 'username' => 'maxuser'],
    'message' => ['text' => 'start'],
], JSON_THROW_ON_ERROR);

$parsed = (new WebhookPayloadParser())->parse($payload);

if ('evt-1' !== $parsed['event_id']) {
    throw new \RuntimeException('event_id was not parsed');
}

if ('chat-77' !== $parsed['chat_id']) {
    throw new \RuntimeException('chat_id was not parsed');
}

try {
    (new WebhookPayloadParser())->parse(json_encode([
        'type' => 'message',
        'chat' => ['id' => 'chat-77'],
    ], JSON_THROW_ON_ERROR));
    throw new \RuntimeException('Missing event_id should fail');
} catch (\InvalidArgumentException) {
}

try {
    (new WebhookPayloadParser())->parse(json_encode([
        'event_id' => 'evt-2',
        'type' => 'message',
        'chat' => [],
    ], JSON_THROW_ON_ERROR));
    throw new \RuntimeException('Missing chat_id should fail');
} catch (\InvalidArgumentException) {
}

echo "WebhookPayloadParserTest passed\n";
