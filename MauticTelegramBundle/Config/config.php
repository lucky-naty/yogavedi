<?php
declare(strict_types=1);
return [
    'name'        => 'Telegram',
    'description' => 'Enables sending messages via Telegram Bot API',
    'version'     => '1.0.0',
    'author'      => 'YogaVedi',
    'services' => [
        'other' => [
            'mautic.telegram.helper.api' => [
                'class'     => \MauticPlugin\MauticTelegramBundle\Helper\TelegramApiHelper::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
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
                ],
                'tags' => ['kernel.event_subscriber'],
            ],
        ],
        'forms' => [
            'mautic.telegram.form.type.send_message' => [
                'class' => \MauticPlugin\MauticTelegramBundle\Form\Type\TelegramSendMessageType::class,
                'tags'  => ['form.type'],
            ],
        ],
    ],
    'menu'       => [],
    'parameters' => [],
];
