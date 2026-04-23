<?php

declare(strict_types=1);

$content = file_get_contents(__DIR__.'/../MauticMaxBundle.php');

foreach ([
    'namespace MauticPlugin\\MauticMaxBundle;',
    'final class MauticMaxBundle',
] as $expected) {
    if (false === $content || !str_contains($content, $expected)) {
        throw new \RuntimeException(sprintf('Expected "%s" in MauticMaxBundle.php', $expected));
    }
}

echo "BundleContractTest passed\n";
