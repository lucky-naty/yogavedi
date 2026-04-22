<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class TelegramIntegration extends AbstractIntegration
{
    public function getName(): string
    {
        return 'Telegram';
    }

    public function getDisplayName(): string
    {
        return 'Telegram';
    }

    public function getIcon(): string
    {
        return 'plugins/MauticTelegramBundle/Assets/img/telegram.png';
    }

    public function getSecretKeys(): array
    {
        return ['bot_token'];
    }

    public function getRequiredKeyFields(): array
    {
        return [
            'bot_token' => 'mautic.telegram.bot_token',
        ];
    }

    public function getKeyFields(): array
    {
        return [
            'bot_token'       => 'mautic.telegram.bot_token',
            'api_base_url'    => 'mautic.telegram.api_base_url',
            'chat_id_field'   => 'mautic.telegram.chat_id_field',
            'parse_mode'      => 'mautic.telegram.parse_mode',
        ];
    }

    public function getFormSettings(): array
    {
        return [
            'requires_callback'      => false,
            'requires_authorization' => false,
        ];
    }

    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('keys' === $formArea) {
            $builder->add(
                'bot_token',
                TextType::class,
                [
                    'label'    => 'mautic.telegram.bot_token',
                    'attr'     => ['class' => 'form-control', 'autocomplete' => 'off'],
                    'required' => true,
                ]
            );

            $builder->add(
                'api_base_url',
                TextType::class,
                [
                    'label'    => 'mautic.telegram.api_base_url',
                    'attr'     => [
                        'class'       => 'form-control',
                        'placeholder' => 'https://api.telegram.org',
                    ],
                    'data'     => $data['api_base_url'] ?? '',
                    'required' => false,
                    'help'     => 'mautic.telegram.api_base_url.help',
                ]
            );

            $builder->add(
                'chat_id_field',
                TextType::class,
                [
                    'label'    => 'mautic.telegram.chat_id_field',
                    'attr'     => ['class' => 'form-control'],
                    'data'     => $data['chat_id_field'] ?? 'telegram_chat_id',
                    'required' => false,
                    'help'     => 'mautic.telegram.chat_id_field.help',
                ]
            );

            $builder->add(
                'parse_mode',
                ChoiceType::class,
                [
                    'label'   => 'mautic.telegram.parse_mode',
                    'choices' => [
                        'HTML'       => 'HTML',
                        'Markdown'   => 'Markdown',
                        'MarkdownV2' => 'MarkdownV2',
                        'Plain Text' => '',
                    ],
                    'data'     => $data['parse_mode'] ?? 'HTML',
                    'required' => false,
                ]
            );
        }
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    public function isAuthorized(): bool
    {
        $keys = $this->getKeys();
        return !empty($keys['bot_token']);
    }
}
