<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Entity\TelegramSubscription;

/**
 * @extends ServiceEntityRepository<Bot>
 */
class BotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bot::class);
    }

    /**
     * Возвращает реальное количество подписчиков для конкретного бота
     */
    public function countSubscribers(int $botId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        
        return (int) $qb->select('count(s.id)')
            ->from(TelegramSubscription::class, 's')
            ->where('s.bot = :botId')
            ->setParameter('botId', $botId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}