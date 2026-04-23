<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Entity\FormEntity;

#[ORM\Entity(repositoryClass: BotRepository::class)]
#[ORM\Table(name: 'max_bots')]
class Bot extends FormEntity
{
    #[ORM\Column(name: 'name', type: 'string', length: 191)]
    private string $name = '';

    #[ORM\Column(name: 'token', type: 'text')]
    private string $token = '';

    #[ORM\Column(name: 'username', type: 'string', length: 191)]
    private string $username = '';

    #[ORM\Column(name: 'display_name', type: 'string', length: 191)]
    private string $displayName = '';

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'welcome_message', type: 'text')]
    private string $welcomeMessage = '';

    #[ORM\Column(name: 'phone_request_message', type: 'text')]
    private string $phoneRequestMessage = '';

    #[ORM\Column(name: 'contact_success_message', type: 'text')]
    private string $contactSuccessMessage = '';

    #[ORM\Column(name: 'api_base_url', type: 'string', length: 255)]
    private string $apiBaseUrl = '';

    #[ORM\Column(name: 'webhook_base_url', type: 'string', length: 255)]
    private string $webhookBaseUrl = '';

    #[ORM\Column(name: 'webhook_secret', type: 'string', length: 191, unique: true)]
    private string $webhookSecret = '';

    #[ORM\Column(name: 'webhook_status', type: 'string', length: 32)]
    private string $webhookStatus = 'idle';

    #[ORM\Column(name: 'last_webhook_error', type: 'text', nullable: true)]
    private ?string $lastWebhookError = null;

    #[ORM\Column(name: 'last_webhook_synced_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastWebhookSyncedAt = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getWelcomeMessage(): string
    {
        return $this->welcomeMessage;
    }

    public function setWelcomeMessage(string $welcomeMessage): self
    {
        $this->welcomeMessage = $welcomeMessage;

        return $this;
    }

    public function getPhoneRequestMessage(): string
    {
        return $this->phoneRequestMessage;
    }

    public function setPhoneRequestMessage(string $phoneRequestMessage): self
    {
        $this->phoneRequestMessage = $phoneRequestMessage;

        return $this;
    }

    public function getContactSuccessMessage(): string
    {
        return $this->contactSuccessMessage;
    }

    public function setContactSuccessMessage(string $contactSuccessMessage): self
    {
        $this->contactSuccessMessage = $contactSuccessMessage;

        return $this;
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function setApiBaseUrl(string $apiBaseUrl): self
    {
        $this->apiBaseUrl = trim($apiBaseUrl);

        return $this;
    }

    public function getWebhookBaseUrl(): string
    {
        return $this->webhookBaseUrl;
    }

    public function setWebhookBaseUrl(string $webhookBaseUrl): self
    {
        $this->webhookBaseUrl = rtrim(trim($webhookBaseUrl), '/');

        return $this;
    }

    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }

    public function setWebhookSecret(string $webhookSecret): self
    {
        $this->webhookSecret = $webhookSecret;

        return $this;
    }

    public function getWebhookStatus(): string
    {
        return $this->webhookStatus;
    }

    public function setWebhookStatus(string $webhookStatus): self
    {
        $this->webhookStatus = $webhookStatus;

        return $this;
    }

    public function getLastWebhookError(): ?string
    {
        return $this->lastWebhookError;
    }

    public function setLastWebhookError(?string $lastWebhookError): self
    {
        $this->lastWebhookError = $lastWebhookError;

        return $this;
    }

    public function getLastWebhookSyncedAt(): ?\DateTimeInterface
    {
        return $this->lastWebhookSyncedAt;
    }

    public function setLastWebhookSyncedAt(?\DateTimeInterface $lastWebhookSyncedAt): self
    {
        $this->lastWebhookSyncedAt = $lastWebhookSyncedAt;

        return $this;
    }
}
