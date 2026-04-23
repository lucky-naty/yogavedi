<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class MaxSendMessageType extends AbstractType
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'send_scope',
            ChoiceType::class,
            [
                'label'    => 'mautic.max.form.send_scope',
                'choices'  => [
                    'mautic.max.form.send_scope.first_subscribed' => 'first_subscribed',
                    'mautic.max.form.send_scope.all_subscribed'   => 'all_subscribed',
                    'mautic.max.form.send_scope.selected_only'    => 'selected_only',
                ],
                'data'     => $options['data']['send_scope'] ?? 'first_subscribed',
                'required' => false,
                'attr'     => ['class' => 'form-control max-send-scope'],
                'help'     => 'mautic.max.form.send_scope.help',
            ]
        );

        $builder->add(
            'bot_selection',
            ChoiceType::class,
            [
                'label'       => 'mautic.max.form.bot_selection',
                'choices'     => $this->getBotChoices(),
                'data'        => $this->normalizeBotSelection($options['data'] ?? []),
                'required'    => false,
                'placeholder' => 'mautic.max.form.bot_selection.placeholder',
                'attr'        => ['class' => 'form-control max-bot-selection'],
                'help'        => 'mautic.max.form.bot_selection.help',
            ]
        );

        $builder->add(
            'message',
            TextareaType::class,
            [
                'label'       => 'mautic.max.form.message',
                'required'    => true,
                'constraints' => [new NotBlank()],
                'attr'        => [
                    'class' => 'form-control',
                    'rows'  => 8,
                ],
                'help'        => 'mautic.max.form.message.help',
            ]
        );

        $builder->add(
            'token_picker',
            ChoiceType::class,
            [
                'label'       => 'mautic.max.form.token_picker',
                'choices'     => $this->getContactTokenChoices(),
                'placeholder' => 'mautic.max.form.token_picker.placeholder',
                'required'    => false,
                'mapped'      => false,
                'attr'        => [
                    'class'                           => 'form-control max-token-picker',
                    'autocomplete'                    => 'off',
                    'data-minimum-results-for-search' => 'Infinity',
                ],
                'help'        => 'mautic.max.form.token_picker.help',
                'choice_attr' => static function (mixed $choice): array {
                    return is_string($choice) && str_starts_with($choice, '{contact.')
                        ? ['data-token' => $choice]
                        : [];
                },
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
        $choices = [];

        try {
            $bots = $this->entityManager->getConnection()->fetchAllAssociative(
                'SELECT id, name, username FROM max_bots WHERE is_published = 1 AND is_active = 1 ORDER BY name ASC, id ASC'
            );

            foreach ($bots as $bot) {
                $label = trim((string) $bot['name']);
                if (!empty($bot['username'])) {
                    $label .= ' (@'.ltrim((string) $bot['username'], '@').')';
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
            'Email - {contact.email}'          => '{contact.email}',
            'First name - {contact.firstname}' => '{contact.firstname}',
            'Last name - {contact.lastname}'   => '{contact.lastname}',
            'Phone - {contact.mobile}'         => '{contact.mobile}',
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
                $choices[$label.' - {contact.'.$alias.'}'] = '{contact.'.$alias.'}';
            }
        } catch (\Throwable) {
        }

        return $choices;
    }

    private function normalizeBotSelection(array $data): ?string
    {
        $selection = trim((string) ($data['bot_selection'] ?? ''));

        return '' === $selection ? null : $selection;
    }
}
