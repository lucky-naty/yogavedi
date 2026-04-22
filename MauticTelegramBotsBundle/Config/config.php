<?php

declare(strict_types=1);

return [
    'name'        => 'Telegram Bots',
    'description' => 'Manage Telegram bots and collect subscribers into Mautic contacts',
    'version'     => '1.0.5',
    'author'      => 'YogaVedi',

    'routes' => [
        'main' => [
            'mautic_telegram_bots_index' => [
                'path'       => '/telegram/bots/{page}',
                'controller' => 'MauticPlugin\MauticTelegramBotsBundle\Controller\BotController::indexAction',
                'defaults'   => ['page' => 1],
            ],
            'mautic_telegram_bots_action' => [
                'path'       => '/telegram/bots/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\MauticTelegramBotsBundle\Controller\BotController::executeAction',
                'defaults'   => ['objectId' => 0],
            ],
            'mautic_telegram_bots_register_webhook' => [
                'path'       => '/telegram/bots/{id}/register-webhook',
                'controller' => 'MauticPlugin\MauticTelegramBotsBundle\Controller\BotController::registerWebhookAction',
            ],
        ],
        'public' => [
            'mautic_telegram_bots_webhook' => [
                'path'       => '/telegram/webhook/{token}',
                'controller' => 'MauticPlugin\MauticTelegramBotsBundle\Controller\WebhookController::handleAction',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'Telegram Bots' => [
                'route'     => 'mautic_telegram_bots_index',
                'iconClass' => 'ri-telegram-line',
                'access'    => 'plugin:mauticTelegramBots:bots:view',
                'parent'    => 'mautic.core.channels',
                'priority'  => 60,
            ],
        ],
    ],

    'parameters' => [],
];
