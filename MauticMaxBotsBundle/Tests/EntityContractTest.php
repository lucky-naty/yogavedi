<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Tests;

use MauticPlugin\MauticMaxBotsBundle\Entity\Bot;
use MauticPlugin\MauticMaxBotsBundle\Entity\MaxIncomingEvent;
use MauticPlugin\MauticMaxBotsBundle\Entity\MaxSubscription;

require_once __DIR__.'/../Entity/Bot.php';
require_once __DIR__.'/../Entity/MaxSubscription.php';
require_once __DIR__.'/../Entity/MaxIncomingEvent.php';

$bot = new Bot();
$subscription = new MaxSubscription();
$event = new MaxIncomingEvent();

if (!method_exists($bot, 'getWebhookSecret')) {
    throw new \RuntimeException('Bot::getWebhookSecret() is missing');
}

if (!method_exists($subscription, 'setChatId')) {
    throw new \RuntimeException('MaxSubscription::setChatId() is missing');
}

if (!method_exists($event, 'setPayload')) {
    throw new \RuntimeException('MaxIncomingEvent::setPayload() is missing');
}

echo "EntityContractTest passed\n";
