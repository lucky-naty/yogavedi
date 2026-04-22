<?php

declare(strict_types=1);

require_once __DIR__.'/../Helper/TelegramApiHelper.php';

use MauticPlugin\MauticTelegramBundle\Helper\TelegramApiHelper;

$defaultUrl = TelegramApiHelper::buildTelegramApiUrl('', '123:ABC', 'sendMessage');
$customUrl = TelegramApiHelper::buildTelegramApiUrl('https://example.com/custom/bot', '123:ABC', 'sendMessage');

if ('https://api.telegram.org/bot123:ABC/sendMessage' !== $defaultUrl) {
    fwrite(STDERR, "Default URL mismatch: {$defaultUrl}\n");
    exit(1);
}

if ('https://example.com/custom/bot123:ABC/sendMessage' !== $customUrl) {
    fwrite(STDERR, "Custom URL mismatch: {$customUrl}\n");
    exit(1);
}

if (str_contains($defaultUrl.$customUrl, 'workers'.'.dev')) {
    fwrite(STDERR, "Public build must not contain a hard-coded worker URL.\n");
    exit(1);
}

echo "TelegramApiHelperUrlTest passed\n";
