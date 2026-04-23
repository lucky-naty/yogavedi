<?php

declare(strict_types=1);

$file = __DIR__.'/../Form/Type/MaxSendMessageType.php';
$content = file_get_contents($file);

foreach ([
    'send_scope',
    'bot_selection',
    'max-send-scope',
    'max-bot-selection',
    'message',
    'token_picker',
] as $expected) {
    if (false === $content || !str_contains($content, $expected)) {
        throw new \RuntimeException(sprintf('Expected "%s" in MaxSendMessageType.', $expected));
    }
}

echo "MaxSendMessageTypeContractTest passed\n";
