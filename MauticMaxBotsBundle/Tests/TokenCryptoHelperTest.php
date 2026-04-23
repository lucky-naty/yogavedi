<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Helper\TokenCryptoHelper;

require_once __DIR__.'/../Helper/TokenCryptoHelper.php';

$helper = new TokenCryptoHelper('test-secret-key');
$encrypted = $helper->encryptIfNeeded('max-token-123');
$decrypted = $helper->decryptIfNeeded($encrypted);

if (!str_starts_with($encrypted, 'enc:v1:')) {
    throw new \RuntimeException('Token was not encrypted with the expected prefix');
}

if (!$helper->isEncrypted($encrypted)) {
    throw new \RuntimeException('Encrypted token was not recognized');
}

if ('max-token-123' !== $decrypted) {
    throw new \RuntimeException('Token decryption failed');
}

if ('plain-token' !== $helper->decryptIfNeeded('plain-token')) {
    throw new \RuntimeException('Plaintext token should pass through unchanged');
}

try {
    $helper->decryptIfNeeded('enc:v1:invalid');
    throw new \RuntimeException('Malformed encrypted token should fail');
} catch (\RuntimeException) {
}

echo "TokenCryptoHelperTest passed\n";
