<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TelegramSendMessageType extends AbstractType
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'bot_selection',
            ChoiceType::class,
            [
                'label'    => 'mautic.telegram.form.bot_selection',
                'choices'  => $this->getBotChoices(),
                'data'     => $options['data']['bot_selection'] ?? $this->legacyBotSelection($options['data'] ?? []),
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'help'     => 'mautic.telegram.form.bot_selection.help',
            ]
        );

        $builder->add(
            'send_scope',
            ChoiceType::class,
            [
                'label'    => 'mautic.telegram.form.send_scope',
                'choices'  => [
                    'mautic.telegram.form.send_scope.first_subscribed' => 'first_subscribed',
                    'mautic.telegram.form.send_scope.all_subscribed'   => 'all_subscribed',
                    'mautic.telegram.form.send_scope.selected_only'    => 'selected_only',
                ],
                'data'     => $options['data']['send_scope'] ?? 'first_subscribed',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'help'     => 'mautic.telegram.form.send_scope.help',
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
            'token_picker',
            ChoiceType::class,
            [
                'label'       => 'mautic.telegram.form.token_picker',
                'choices'     => $this->getContactTokenChoices(),
                'placeholder' => 'mautic.telegram.form.token_picker.placeholder',
                'required'    => false,
                'mapped'      => false,
                'attr'        => [
                    'class'                           => 'form-control telegram-token-picker',
                    'autocomplete'                    => 'off',
                    'data-minimum-results-for-search' => 'Infinity',
                    'onchange'                        => "var option=this.options[this.selectedIndex];var token=option?option.getAttribute('data-token'):'';if(!token){return;}var target=document.querySelector('textarea[name$=\"[message]\"]');if(target){var start=target.selectionStart||target.value.length;var end=target.selectionEnd||target.value.length;var prefix=target.value.substring(0,start);var suffix=target.value.substring(end);var spacer=prefix&&!/\\s$/.test(prefix)?' ':'';target.value=prefix+spacer+token+suffix;target.focus();target.selectionStart=target.selectionEnd=start+spacer.length+token.length;target.dispatchEvent(new Event('change',{bubbles:true}));}this.selectedIndex=0;if(window.jQuery){jQuery(this).trigger('change.select2');}",
                ],
                'help'        => 'mautic.telegram.form.token_picker.help',
                'choice_attr' => function (mixed $choice): array {
                    return is_string($choice) && str_starts_with($choice, '{contact.')
                        ? ['data-token' => $choice]
                        : [];
                },
            ]
        );

        $builder->add(
            'media_type',
            ChoiceType::class,
            [
                'label'   => 'mautic.telegram.form.media_type',
                'choices' => [
                    'mautic.telegram.form.media_type.text'      => 'text',
                    'mautic.telegram.form.media_type.photo'     => 'photo',
                    'mautic.telegram.form.media_type.document'  => 'document',
                    'mautic.telegram.form.media_type.video'     => 'video',
                    'mautic.telegram.form.media_type.audio'     => 'audio',
                    'mautic.telegram.form.media_type.voice'     => 'voice',
                    'mautic.telegram.form.media_type.animation' => 'animation',
                ],
                'data'     => $options['data']['media_type'] ?? 'text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
                'help'     => 'mautic.telegram.form.media_type.help',
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
            'parse_mode',
            ChoiceType::class,
            [
                'label'   => 'mautic.telegram.form.parse_mode',
                'choices' => [
                    'HTML'       => 'HTML',
                    'MarkdownV2' => 'MarkdownV2',
                    'Plain text' => '',
                ],
                'data'     => $options['data']['parse_mode'] ?? 'HTML',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'disable_notification',
            CheckboxType::class,
            [
                'label'    => 'mautic.telegram.form.disable_notification',
                'data'     => (bool) ($options['data']['disable_notification'] ?? false),
                'required' => false,
            ]
        );

        $builder->add(
            'protect_content',
            CheckboxType::class,
            [
                'label'    => 'mautic.telegram.form.protect_content',
                'data'     => (bool) ($options['data']['protect_content'] ?? false),
                'required' => false,
            ]
        );

        $builder->add(
            'disable_web_page_preview',
            CheckboxType::class,
            [
                'label'    => 'mautic.telegram.form.disable_web_page_preview',
                'data'     => (bool) ($options['data']['disable_web_page_preview'] ?? false),
                'required' => false,
            ]
        );

        $builder->add(
            'button_1_text',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.button_1_text',
                'attr'     => ['class' => 'form-control'],
                'required' => false,
            ]
        );

        $builder->add(
            'button_1_type',
            ChoiceType::class,
            [
                'label'   => 'mautic.telegram.form.button_type',
                'choices' => $this->getButtonTypeChoices(),
                'data'     => $options['data']['button_1_type'] ?? 'url',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'button_1_value',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.button_value',
                'attr'     => ['class' => 'form-control', 'placeholder' => 'https://example.com'],
                'required' => false,
                'help'     => 'mautic.telegram.form.button_value.help',
            ]
        );

        $builder->add(
            'button_2_text',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.button_2_text',
                'attr'     => ['class' => 'form-control'],
                'required' => false,
            ]
        );

        $builder->add(
            'button_2_type',
            ChoiceType::class,
            [
                'label'    => 'mautic.telegram.form.button_type',
                'choices'  => $this->getButtonTypeChoices(),
                'data'     => $options['data']['button_2_type'] ?? 'url',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'button_2_value',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.button_value',
                'attr'     => ['class' => 'form-control'],
                'required' => false,
            ]
        );

        $builder->add(
            'button_3_text',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.button_3_text',
                'attr'     => ['class' => 'form-control'],
                'required' => false,
            ]
        );

        $builder->add(
            'button_3_type',
            ChoiceType::class,
            [
                'label'    => 'mautic.telegram.form.button_type',
                'choices'  => $this->getButtonTypeChoices(),
                'data'     => $options['data']['button_3_type'] ?? 'url',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'button_3_value',
            TextType::class,
            [
                'label'    => 'mautic.telegram.form.button_value',
                'attr'     => ['class' => 'form-control'],
                'required' => false,
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data' => [],
        ]);
    }

    private function getBotChoices(): array
    {
        $choices = [
            'mautic.telegram.form.bot_selection.auto' => 'auto',
        ];

        try {
            $bots = $this->entityManager->getConnection()->fetchAllAssociative(
                'SELECT id, name, bot_username FROM telegram_bots WHERE is_published = 1 ORDER BY name ASC, id ASC'
            );

            foreach ($bots as $bot) {
                $label = trim((string) $bot['name']);
                if (!empty($bot['bot_username'])) {
                    $label .= ' (' . $bot['bot_username'] . ')';
                }
                $choices[$label] = (string) $bot['id'];
            }
        } catch (\Throwable) {
        }

        return $choices;
    }

    private function getContactTokenChoices(): array
    {
        $choices = [
            'Email - {contact.email}'           => '{contact.email}',
            'First name - {contact.firstname}'  => '{contact.firstname}',
            'Last name - {contact.lastname}'    => '{contact.lastname}',
            'Phone - {contact.mobile}'          => '{contact.mobile}',
        ];

        try {
            $fields = $this->entityManager->getConnection()->fetchAllAssociative(
                'SELECT label, alias FROM lead_fields WHERE is_published = 1 ORDER BY object ASC, label ASC'
            );

            foreach ($fields as $field) {
                $alias = (string) $field['alias'];
                if ('' === $alias) {
                    continue;
                }

                $label = trim((string) $field['label']) ?: $alias;
                $choices[$label . ' {' . $alias . '}'] = '{contact.' . $alias . '}';
            }
        } catch (\Throwable) {
        }

        return $choices;
    }

    private function getButtonTypeChoices(): array
    {
        return [
            'mautic.telegram.form.button_type.url'      => 'url',
            'mautic.telegram.form.button_type.callback' => 'callback',
        ];
    }

    private function legacyBotSelection(array $data): string
    {
        return isset($data['bot_id']) && '' !== (string) $data['bot_id'] ? (string) $data['bot_id'] : 'auto';
    }
}
