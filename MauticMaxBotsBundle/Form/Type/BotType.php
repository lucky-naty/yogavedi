<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\FormButtonsType;
use MauticPlugin\MauticMaxBotsBundle\Entity\Bot;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class BotType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Bot|null $bot */
        $bot = $options['data'] instanceof Bot ? $options['data'] : null;

        $builder->add('buttons', FormButtonsType::class);
        $builder->add('name', TextType::class, [
            'label'       => 'mautic.max.bots.form.name',
            'attr'        => ['class' => 'form-control'],
            'constraints' => [new NotBlank()],
        ]);
        $builder->add('token', PasswordType::class, [
            'label'       => 'mautic.max.bots.form.token',
            'attr'        => [
                'class'        => 'form-control',
                'autocomplete' => 'new-password',
                'placeholder'  => $bot && $bot->getId() ? '********' : '',
            ],
            'constraints' => $bot && $bot->getId() ? [] : [new NotBlank()],
            'required'    => !$bot || !$bot->getId(),
            'help'        => 'mautic.max.bots.form.token.help',
        ]);
        $builder->add('welcomeMessage', TextareaType::class, [
            'label'    => 'mautic.max.bots.form.welcome_message',
            'attr'     => ['class' => 'form-control', 'rows' => 5],
            'required' => false,
            'help'     => 'mautic.max.bots.form.welcome_message.help',
        ]);
        $builder->add('requestPhoneAfterSubscribe', CheckboxType::class, [
            'label'    => 'mautic.max.bots.form.request_phone_after_subscribe',
            'mapped'   => false,
            'required' => false,
            'attr'     => ['class' => 'form-check-input'],
            'help'     => 'mautic.max.bots.form.request_phone_after_subscribe.help',
            'data'     => $bot ? '' !== trim($bot->getPhoneRequestMessage()) : false,
        ]);
        $builder->add('phoneRequestMessage', TextareaType::class, [
            'label'    => 'mautic.max.bots.form.phone_request_message',
            'attr'     => ['class' => 'form-control', 'rows' => 3],
            'required' => false,
        ]);
        $builder->add('contactSuccessMessage', TextareaType::class, [
            'label'    => 'mautic.max.bots.form.contact_success_message',
            'attr'     => ['class' => 'form-control', 'rows' => 3],
            'required' => false,
            'help'     => 'mautic.max.bots.form.contact_success_message.help',
        ]);
        $builder->add('apiBaseUrl', TextType::class, [
            'label'       => 'mautic.max.bots.form.api_base_url',
            'attr'        => [
                'class'       => 'form-control',
                'placeholder' => 'https://platform-api.max.ru',
            ],
            'empty_data'  => 'https://platform-api.max.ru',
            'required'    => true,
            'constraints' => [new NotBlank()],
            'help'        => 'mautic.max.bots.form.api_base_url.help',
        ]);
        $builder->add('webhookBaseUrl', TextType::class, [
            'label'       => 'mautic.max.bots.form.webhook_base_url',
            'attr'        => [
                'class'       => 'form-control',
                'placeholder' => 'https://your-mautic.example.com',
            ],
            'required'    => true,
            'constraints' => [new NotBlank()],
            'help'        => 'mautic.max.bots.form.webhook_base_url.help',
        ]);
        $builder->add('isPublished', CheckboxType::class, [
            'label'    => 'mautic.core.form.published',
            'required' => false,
            'attr'     => ['class' => 'form-check-input'],
        ]);
        $builder->add('isActive', CheckboxType::class, [
            'label'    => 'mautic.max.bots.form.is_active',
            'required' => false,
            'attr'     => ['class' => 'form-check-input'],
        ]);

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();

            if (!$data instanceof Bot || !$form->has('requestPhoneAfterSubscribe')) {
                return;
            }

            $shouldRequestPhone = (bool) $form->get('requestPhoneAfterSubscribe')->getData();
            if (!$shouldRequestPhone) {
                $data->setPhoneRequestMessage('');
                $data->setContactSuccessMessage('');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bot::class,
        ]);
    }
}
