<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;

class BotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->buildButtons($builder);
        $builder->add('name', TextType::class, [
            'label'       => 'mautic.telegram.bots.form.name',
            'attr'        => ['class' => 'form-control'],
            'constraints' => [new NotBlank()],
        ]);

        /** @var Bot|null $bot */
        $bot = $options['data'] instanceof Bot ? $options['data'] : null;

        $builder->add('token', PasswordType::class, [
            'label'       => 'mautic.telegram.bots.form.token',
            'attr'        => [
                'class'        => 'form-control',
                'autocomplete' => 'new-password',
                'placeholder'  => $bot && $bot->getId() ? 'Leave empty to keep current token' : '',
            ],
            'constraints' => $bot && $bot->getId() ? [] : [new NotBlank()],
            'help'        => 'mautic.telegram.bots.form.token.help',
            'required'    => !$bot || !$bot->getId(),
        ]);

        $builder->add('isPublished', CheckboxType::class, [
            'label'    => 'mautic.core.form.published',
            'required' => false,
            'attr'     => ['class' => 'form-check-input'],
        ]);

        $builder->add('apiBaseUrl', TextType::class, [
            'label'    => 'mautic.telegram.bots.form.api_base_url',
            'attr'     => [
                'class'       => 'form-control',
                'placeholder' => 'https://api.telegram.org',
            ],
            'required' => false,
            'help'     => 'mautic.telegram.bots.form.api_base_url.help',
        ]);

        $builder->add('webhookBaseUrl', TextType::class, [
            'label'    => 'mautic.telegram.bots.form.webhook_base_url',
            'attr'     => [
                'class'       => 'form-control',
                'placeholder' => 'https://your-mautic.example.com',
            ],
            'required' => false,
            'help'     => 'mautic.telegram.bots.form.webhook_base_url.help',
        ]);

        $builder->add('welcomeMessage', TextareaType::class, [
            'label'    => 'mautic.telegram.bots.form.welcome_message',
            'attr'     => ['class' => 'form-control', 'rows' => 5],
            'required' => false,
            'help'     => 'mautic.telegram.bots.form.welcome_message.help',
        ]);

        $builder->add('askPhone', CheckboxType::class, [
            'label'    => 'mautic.telegram.bots.form.ask_phone',
            'required' => false,
            'attr'     => ['class' => 'form-check-input'],
        ]);

        $builder->add('askPhoneMessage', TextareaType::class, [
            'label'    => 'mautic.telegram.bots.form.ask_phone_message',
            'attr'     => ['class' => 'form-control', 'rows' => 3],
            'required' => false,
        ]);

        $builder->add('phoneReceivedMessage', TextareaType::class, [
            'label'    => 'mautic.telegram.bots.form.phone_received_message',
            'attr'     => ['class' => 'form-control', 'rows' => 3],
            'required' => false,
            'help'     => 'mautic.telegram.bots.form.phone_received_message.help',
        ]);

        $builder->add('tags', TextType::class, [
            'label'    => 'mautic.telegram.bots.form.tags',
            'attr'     => ['class' => 'form-control', 'placeholder' => 'telegram-subscriber, bot-name'],
            'required' => false,
            'help'     => 'mautic.telegram.bots.form.tags.help',
        ]);
    }

    public function buildButtons(FormBuilderInterface $builder): void
    {
        $builder->add('buttons', FormButtonsType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Bot::class]);
    }
}
