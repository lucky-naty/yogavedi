<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;

/**
 * @extends CommonRepository<Bot>
 */
class BotRepository extends CommonRepository
{
    public function findByToken(string $token): ?Bot
    {
        return $this->findOneBy(['token' => $token, 'isPublished' => true]);
    }

    public function getTableAlias(): string
    {
        return 'b';
    }
}
