<?php

declare(strict_types=1);

return [
    'name'        => 'MAX Bots',
    'description' => 'Manage MAX bots and collect MAX subscribers into Mautic contacts',
    'version'     => '1.0.0',
    'author'      => 'YogaVedi',
    'routes'      => [
        'main' => [
            'mautic_max_bots_index' => [
                'path'       => '/max/bots/{page}',
                'controller' => 'MauticPlugin\\MauticMaxBotsBundle\\Controller\\BotController::indexAction',
                'defaults'   => ['page' => 1],
            ],
            'mautic_max_bots_action' => [
                'path'       => '/max/bots/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\\MauticMaxBotsBundle\\Controller\\BotController::executeAction',
                'defaults'   => ['objectId' => 0],
            ],
            'mautic_max_bots_register_webhook' => [
                'path'       => '/max/bots/{id}/register-webhook',
                'controller' => 'MauticPlugin\\MauticMaxBotsBundle\\Controller\\BotController::registerWebhookAction',
            ],
        ],
        'public' => [
            'mautic_max_bots_webhook' => [
                'path'       => '/max/webhook/{token}',
                'controller' => 'MauticPlugin\\MauticMaxBotsBundle\\Controller\\WebhookController::handleAction',
            ],
        ],
    ],
    'menu'        => [
        'main' => [
            'MAX Bots' => [
                'route'     => 'mautic_max_bots_index',
                'iconClass' => 'ri-message-3-line',
                'access'    => 'plugin:mauticMaxBots:bots:view',
                'parent'    => 'mautic.core.channels',
                'priority'  => 61,
            ],
        ],
    ],
    'parameters'  => [],
];
