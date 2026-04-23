<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'max_incoming_events')]
class MaxIncomingEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Bot::class)]
    #[ORM\JoinColumn(name: 'bot_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Bot $bot;

    #[ORM\ManyToOne(targetEntity: MaxSubscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MaxSubscription $subscription = null;

    #[ORM\Column(name: 'event_type', type: 'string', length: 64)]
    private string $eventType = '';

    #[ORM\Column(name: 'event_id', type: 'string', length: 191)]
    private string $eventId = '';

    #[ORM\Column(name: 'payload', type: 'text')]
    private string $payload = '{}';

    #[ORM\Column(name: 'status', type: 'string', length: 32)]
    private string $status = 'pending';

    #[ORM\Column(name: 'processed_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'date_added', type: 'datetime')]
    private \DateTimeInterface $dateAdded;

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

    public function getSubscription(): ?MaxSubscription
    {
        return $this->subscription;
    }

    public function setSubscription(?MaxSubscription $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeInterface $processedAt): self
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

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
}
