<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Model\BotModel;

require_once __DIR__.'/../Model/BotModel.php';

if (!method_exists(BotModel::class, 'registerWebhook')) {
    throw new \RuntimeException('BotModel::registerWebhook() is missing');
}

if (!method_exists(BotModel::class, 'checkConnection')) {
    throw new \RuntimeException('BotModel::checkConnection() is missing');
}

if (!method_exists(BotModel::class, 'buildWebhookUrl')) {
    throw new \RuntimeException('BotModel::buildWebhookUrl() is missing');
}

echo "BotModelContractTest passed\n";
