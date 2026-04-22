<?php

declare(strict_types=1);

require_once __DIR__.'/../Helper/TelegramBotApiHelper.php';

use MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper;

$defaultUrl = TelegramBotApiHelper::buildTelegramApiUrl('', '123:ABC', 'setWebhook');
$customUrl = TelegramBotApiHelper::buildTelegramApiUrl('https://example.com/custom/bot', '123:ABC', 'setWebhook');

if ('https://api.telegram.org/bot123:ABC/setWebhook' !== $defaultUrl) {
    fwrite(STDERR, "Default URL mismatch: {$defaultUrl}\n");
    exit(1);
}

if ('https://example.com/custom/bot123:ABC/setWebhook' !== $customUrl) {
    fwrite(STDERR, "Custom URL mismatch: {$customUrl}\n");
    exit(1);
}

if (str_contains($defaultUrl.$customUrl, 'workers'.'.dev')) {
    fwrite(STDERR, "Public build must not contain a hard-coded worker URL.\n");
    exit(1);
}

echo "TelegramBotApiHelperUrlTest passed\n";
