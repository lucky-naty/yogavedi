<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Controller\WebhookController;

require_once __DIR__.'/../Controller/WebhookController.php';

if (!method_exists(WebhookController::class, 'handleAction')) {
    throw new \RuntimeException('WebhookController::handleAction() is missing');
}

echo "WebhookControllerContractTest passed\n";
