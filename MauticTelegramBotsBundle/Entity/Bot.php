<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;

class Bot extends FormEntity
{
    private ?int $id = null;
    private string $name = '';
    private ?string $description = null;
    private string $token = '';
    private bool $isPublished = true;
    private string $welcomeMessage = '';
    private string $askPhoneMessage = '';
    private bool $askPhone = false;
    private string $tags = '';
    private string $webhookUrl = '';
    private ?\DateTime $webhookRegisteredAt = null;
    private ?string $botUsername = null;
    private int $subscribersCount = 0;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('telegram_bots');
        $builder->setCustomRepositoryClass(\MauticPlugin\MauticTelegramBotsBundle\Entity\BotRepository::class);

        $builder->addId();
        $builder->addNamedField('name', 'string', 'name');
        $builder->addNullableField('description', 'text', 'description');
        $builder->addNamedField('token', 'string', 'token');
        
        $builder->addNamedField('welcomeMessage', 'text', 'welcome_message', true);
        $builder->addNamedField('askPhoneMessage', 'text', 'ask_phone_message', true);
        $builder->addField('askPhone', 'boolean', ['columnName' => 'ask_phone', 'default' => false]);
        $builder->addNamedField('tags', 'text', 'tags', true);
        $builder->addNamedField('webhookUrl', 'string', 'webhook_url', true);
        $builder->addNullableField('webhookRegisteredAt', 'datetime', 'webhook_registered_at');
        $builder->addNullableField('botUsername', 'string', 'bot_username');
        $builder->addField('subscribersCount', 'integer', ['columnName' => 'subscribers_count', 'default' => 0]);
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getToken(): string { return $this->token; }
    public function setToken(string $token): self { $this->token = $token; return $this; }
    public function getWelcomeMessage(): string { return $this->welcomeMessage; }
    public function setWelcomeMessage(string $welcomeMessage): self { $this->welcomeMessage = $welcomeMessage; return $this; }
    public function getAskPhoneMessage(): string { return $this->askPhoneMessage; }
    public function setAskPhoneMessage(string $askPhoneMessage): self { $this->askPhoneMessage = $askPhoneMessage; return $this; }
    public function isAskPhone(): bool { return $this->askPhone; }
    public function setAskPhone(bool $askPhone): self { $this->askPhone = $askPhone; return $this; }
    public function getTags(): string { return $this->tags; }
    public function setTags(string $tags): self { $this->tags = $tags; return $this; }
    public function getWebhookUrl(): string { return $this->webhookUrl; }
    public function setWebhookUrl(string $webhookUrl): self { $this->webhookUrl = $webhookUrl; return $this; }
    public function getWebhookRegisteredAt(): ?\DateTime { return $this->webhookRegisteredAt; }
    public function setWebhookRegisteredAt(?\DateTime $webhookRegisteredAt): self { $this->webhookRegisteredAt = $webhookRegisteredAt; return $this; }
    public function getBotUsername(): ?string { return $this->botUsername; }
    public function setBotUsername(?string $botUsername): self { $this->botUsername = $botUsername; return $this; }
    public function getSubscribersCount(): int { return $this->subscribersCount; }
    public function setSubscribersCount(int $count): self { $this->subscribersCount = $count; return $this; }
    public function incrementSubscribersCount(): self { $this->subscribersCount++; return $this; }

    public function getTagsArray(): array
    {
        if (empty($this->tags)) return [];
        return array_filter(array_map('trim', explode(',', $this->tags)));
    }

    public function getMaskedToken(): string
    {
        if (strlen($this->token) < 10) return '***';
        return substr($this->token, 0, 6) . '...' . substr($this->token, -4);
    }
}
