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

    public function countSubscribers(int $botId): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT COUNT(DISTINCT chat_id) FROM telegram_subscriptions WHERE bot_id = :botId AND is_active = 1',
                ['botId' => $botId]
            );
    }

    public function getTableAlias(): string
    {
        return 'b';
    }
}
