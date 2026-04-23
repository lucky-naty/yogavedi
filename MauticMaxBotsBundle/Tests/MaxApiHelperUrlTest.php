<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Helper\MaxApiHelper;

require_once __DIR__.'/../Helper/MaxApiHelper.php';

$url = MaxApiHelper::buildApiUrlForTest('https://api.max.ru', '/messages');

if ('https://api.max.ru/messages' !== $url) {
    throw new \RuntimeException('Unexpected MAX API URL: '.$url);
}

$validated = MaxApiHelper::validateBaseUrlForTest('https://api.max.ru');

if ('https://api.max.ru' !== $validated) {
    throw new \RuntimeException('Expected validated URL to pass through unchanged');
}

try {
    MaxApiHelper::validateBaseUrlForTest('http://127.0.0.1:9000');
    throw new \RuntimeException('Expected localhost URL to be rejected');
} catch (\InvalidArgumentException) {
}

try {
    MaxApiHelper::validateBaseUrlForTest('https://10.0.0.5');
    throw new \RuntimeException('Expected private IP URL to be rejected');
} catch (\InvalidArgumentException) {
}

try {
    MaxApiHelper::validateBaseUrlForTest('https://192.168.1.10');
    throw new \RuntimeException('Expected reserved IP URL to be rejected');
} catch (\InvalidArgumentException) {
}

echo "MaxApiHelperUrlTest passed\n";
