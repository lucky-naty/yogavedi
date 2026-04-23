<?php

declare(strict_types=1);

$file = __DIR__.'/../Form/Type/BotType.php';
$contents = file_get_contents($file);

if (false === $contents) {
    throw new \RuntimeException('Unable to read BotType.php');
}

$requiredSnippets = [
    "requestPhoneAfterSubscribe",
    "phoneRequestMessage",
    "contactSuccessMessage",
    "apiBaseUrl",
    "webhookBaseUrl",
    "isPublished",
    "isActive",
    "FormButtonsType::class",
];

foreach ($requiredSnippets as $snippet) {
    if (!str_contains($contents, $snippet)) {
        throw new \RuntimeException(sprintf('BotType is missing expected snippet: %s', $snippet));
    }
}

echo "BotTypeContractTest passed\n";
