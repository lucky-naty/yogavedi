<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'max_subscriptions')]
#[ORM\UniqueConstraint(name: 'uniq_max_subscription_bot_chat', columns: ['bot_id', 'chat_id'])]
class MaxSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Bot::class)]
    #[ORM\JoinColumn(name: 'bot_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Bot $bot;

    #[ORM\Column(name: 'lead_id', type: 'integer', nullable: true)]
    private ?int $leadId = null;

    #[ORM\Column(name: 'chat_id', type: 'string', length: 191)]
    private string $chatId = '';

    #[ORM\Column(name: 'max_user_id', type: 'string', length: 191, nullable: true)]
    private ?string $maxUserId = null;

    #[ORM\Column(name: 'username', type: 'string', length: 191, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(name: 'first_name', type: 'string', length: 191, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: 'string', length: 191, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'language_code', type: 'string', length: 32, nullable: true)]
    private ?string $languageCode = null;

    #[ORM\Column(name: 'phone_number', type: 'string', length: 64, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'is_blocked', type: 'boolean')]
    private bool $isBlocked = false;

    #[ORM\Column(name: 'is_phone_confirmed', type: 'boolean')]
    private bool $isPhoneConfirmed = false;

    #[ORM\Column(name: 'subscribed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $subscribedAt = null;

    #[ORM\Column(name: 'unsubscribed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $unsubscribedAt = null;

    #[ORM\Column(name: 'last_active_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastActiveAt = null;

    #[ORM\Column(name: 'last_message_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastMessageAt = null;

    #[ORM\Column(name: 'date_added', type: 'datetime')]
    private \DateTimeInterface $dateAdded;

    #[ORM\Column(name: 'date_modified', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateModified = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBot(): Bot
    {
        return $this->bot;
    }

    public function setBot(Bot $bot): self
    {
        $this->bot = $bot;

        return $this;
    }

    public function getLeadId(): ?int
    {
        return $this->leadId;
    }

    public function setLeadId(?int $leadId): self
    {
        $this->leadId = $leadId;

        return $this;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function setChatId(string $chatId): self
    {
        $this->chatId = $chatId;

        return $this;
    }

    public function getMaxUserId(): ?string
    {
        return $this->maxUserId;
    }

    public function setMaxUserId(?string $maxUserId): self
    {
        $this->maxUserId = $maxUserId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getLanguageCode(): ?string
    {
        return $this->languageCode;
    }

    public function setLanguageCode(?string $languageCode): self
    {
        $this->languageCode = $languageCode;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;

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

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;

        return $this;
    }

    public function isPhoneConfirmed(): bool
    {
        return $this->isPhoneConfirmed;
    }

    public function setIsPhoneConfirmed(bool $isPhoneConfirmed): self
    {
        $this->isPhoneConfirmed = $isPhoneConfirmed;

        return $this;
    }

    public function getSubscribedAt(): ?\DateTimeInterface
    {
        return $this->subscribedAt;
    }

    public function setSubscribedAt(?\DateTimeInterface $subscribedAt): self
    {
        $this->subscribedAt = $subscribedAt;

        return $this;
    }

    public function getUnsubscribedAt(): ?\DateTimeInterface
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?\DateTimeInterface $unsubscribedAt): self
    {
        $this->unsubscribedAt = $unsubscribedAt;

        return $this;
    }

    public function getLastActiveAt(): ?\DateTimeInterface
    {
        return $this->lastActiveAt;
    }

    public function setLastActiveAt(?\DateTimeInterface $lastActiveAt): self
    {
        $this->lastActiveAt = $lastActiveAt;

        return $this;
    }

    public function getLastMessageAt(): ?\DateTimeInterface
    {
        return $this->lastMessageAt;
    }

    public function setLastMessageAt(?\DateTimeInterface $lastMessageAt): self
    {
        $this->lastMessageAt = $lastMessageAt;

        return $this;
    }

    public function getDateAdded(): \DateTimeInterface
    {
        return $this->dateAdded;
    }

    public function setDateAdded(\DateTimeInterface $dateAdded): self
    {
        $this->dateAdded = $dateAdded;

        return $this;
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return $this->dateModified;
    }

    public function setDateModified(?\DateTimeInterface $dateModified): self
    {
        $this->dateModified = $dateModified;

        return $this;
    }
}
