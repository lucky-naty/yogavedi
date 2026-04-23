<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<Bot>
 */
class BotRepository extends CommonRepository
{
    public function findOneByWebhookSecret(string $webhookSecret): ?Bot
    {
        return $this->findOneBy([
            'webhookSecret' => $webhookSecret,
            'isPublished'   => true,
            'isActive'      => true,
        ]);
    }

    public function getTableAlias(): string
    {
        return 'b';
    }
}
