<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TelegramSendMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'chat_id_field',
            TextType::class,
            [
                'label'      => 'mautic.telegram.form.chat_id_field',
                'attr'       => ['class' => 'form-control'],
                'data'       => $options['data']['chat_id_field'] ?? 'telegram_chat_id',
                'required'   => false,
                'help'       => 'mautic.telegram.form.chat_id_field.help',
            ]
        );

        $builder->add(
            'message',
            TextareaType::class,
            [
                'label'       => 'mautic.telegram.form.message',
                'attr'        => [
                    'class' => 'form-control',
                    'rows'  => 8,
                ],
                'required'    => true,
                'constraints' => [new NotBlank()],
                'help'        => 'mautic.telegram.form.message.help',
            ]
        );

        $builder->add(
            'media_type',
            ChoiceType::class,
            [
                'label'   => 'mautic.telegram.form.media_type',
                'choices' => [
                    'mautic.telegram.form.media_type.none'     => 'none',
                    'mautic.telegram.form.media_type.photo'    => 'photo',
                    'mautic.telegram.form.media_type.document' => 'document',
                ],
                'data'     => $options['data']['media_type'] ?? 'none',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'media_url',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.media_url',
                'attr'     => ['class' => 'form-control'],
                'required' => false,
                'help'     => 'mautic.telegram.form.media_url.help',
            ]
        );

        $builder->add(
            'buttons',
            TextareaType::class,
            [
                'label'    => 'mautic.telegram.form.buttons',
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 4,
                    'placeholder' => "Button Text|https://example.com\nAnother Button|https://example.com/2",
                ],
                'required' => false,
                'help'     => 'mautic.telegram.form.buttons.help',
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data' => [],
        ]);
    }
}
