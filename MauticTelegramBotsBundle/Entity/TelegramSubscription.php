<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;

/**
 * @ORM\Table(name="telegram_subscriptions")
 */
class TelegramSubscription
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity=Bot::class, inversedBy="subscriptions")
     * @ORM\JoinColumn(name="bot_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private Bot $bot;

    /**
     * @ORM\ManyToOne(targetEntity=Lead::class)
     * @ORM\JoinColumn(name="lead_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    private Lead $lead;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private string $chatId;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('telegram_subscriptions');
        $builder->addId();
        $builder->addNamedField('chatId', 'string', 'chat_id');

        $metadata->mapManyToOne([
            'fieldName'    => 'bot',
            'targetEntity' => Bot::class,
            'inversedBy'   => 'subscriptions',
            'joinColumns'  => [[
                'name'                 => 'bot_id',
                'referencedColumnName' => 'id',
                'nullable'             => false,
                'onDelete'             => 'CASCADE',
            ]],
        ]);

        $metadata->mapManyToOne([
            'fieldName'    => 'lead',
            'targetEntity' => Lead::class,
            'joinColumns'  => [[
                'name'                 => 'lead_id',
                'referencedColumnName' => 'id',
                'nullable'             => false,
                'onDelete'             => 'CASCADE',
            ]],
        ]);
    }

    public function __construct(Bot $bot, Lead $lead, string $chatId)
    {
        $this->bot = $bot;
        $this->lead = $lead;
        $this->chatId = $chatId;
    }

    public function getId(): ?int { return $this->id; }
    public function getBot(): Bot { return $this->bot; }
    public function setBot(Bot $bot): self { $this->bot = $bot; return $this; }
    public function getLead(): Lead { return $this->lead; }
    public function setLead(Lead $lead): self { $this->lead = $lead; return $this; }
    public function getChatId(): string { return $this->chatId; }
    public function setChatId(string $chatId): self { $this->chatId = $chatId; return $this; }
}
