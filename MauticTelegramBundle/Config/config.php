<?php
declare(strict_types=1);
return [
    'name'        => 'Telegram',
    'description' => 'Enables sending Telegram messages from campaigns',
    'version'     => '1.0.5',
    'author'      => 'YogaVedi',
    'services' => [
        'other' => [
            'mautic.telegram.helper.api' => [
                'class'     => \MauticPlugin\MauticTelegramBundle\Helper\TelegramApiHelper::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'events' => [
            'mautic.telegram.campaign.subscriber' => [
                'class'     => \MauticPlugin\MauticTelegramBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    'mautic.telegram.helper.api',
                    'mautic.lead.model.lead',
                    'monolog.logger.mautic',
                    'doctrine.orm.entity_manager',
                ],
                'tags' => ['kernel.event_subscriber'],
            ],
            'mautic.telegram.timeline.subscriber' => [
                'class'     => \MauticPlugin\MauticTelegramBundle\EventListener\TimelineSubscriber::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'translator',
                ],
                'tags' => ['kernel.event_subscriber'],
            ],
        ],
        'forms' => [
            'mautic.telegram.form.type.send_message' => [
                'class'     => \MauticPlugin\MauticTelegramBundle\Form\Type\TelegramSendMessageType::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
                'tags'      => ['form.type'],
            ],
        ],
    ],
    'menu'       => [],
    'parameters' => [],
];
