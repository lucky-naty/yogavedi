<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Helper\MaxApiHelper;

require_once __DIR__.'/../Helper/MaxApiHelper.php';

$payload = MaxApiHelper::buildTextPayloadForTest('chat-1', 'Welcome');

if ('chat-1' !== ($payload['chat_id'] ?? null)) {
    throw new \RuntimeException('chat_id missing from payload');
}

if ('Welcome' !== ($payload['text'] ?? null)) {
    throw new \RuntimeException('text missing from payload');
}

echo "SendPayloadContractTest passed\n";
