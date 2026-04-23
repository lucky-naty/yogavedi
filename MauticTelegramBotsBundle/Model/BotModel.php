<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Form\Type\BotType;
use MauticPlugin\MauticTelegramBotsBundle\Entity\BotRepository;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TokenCryptoHelper;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class BotModel extends FormModel
{
    private int $total = 0;

    public function getRepository(): BotRepository
    {
        /** @var BotRepository $repo */
        $repo = $this->em->getRepository(Bot::class);
        return $repo;
    }

    public function getPermissionBase(): string
    {
        return 'plugin:mauticTelegramBots:bots';
    }

    /**
     * @return Bot[]
     */
    public function getList(Request $request, int $page = 1): array
    {
        $limit = max(1, min(100, (int) $request->query->get('limit', 30)));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $repository = $this->getRepository();

        $this->total = (int) $repository->count([]);

        return $repository->findBy([], ['id' => 'DESC'], $limit, $offset);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function createForm($entity, $formFactory, $action = null, $options = []): Form
    {
        if ($action) {
            $options['action'] = $action;
        }
        return $formFactory->create(BotType::class, $entity, $options);
    }

    public function getEntity($id = null): ?Bot
    {
        if ($id === null) {
            return new Bot();
        }
        return parent::getEntity($id);
    }

    public function saveEntity($entity, $unlock = true): void
    {
        if ($entity instanceof Bot && $entity->getId() && '' === trim($entity->getToken())) {
            $existingToken = $this->em->getConnection()->fetchOne(
                'SELECT token FROM telegram_bots WHERE id = :id',
                ['id' => $entity->getId()]
            );

            if ($existingToken) {
                $entity->setToken((string) $existingToken);
            }
        }

        if ($entity instanceof Bot) {
            $entity->setToken((new TokenCryptoHelper())->encryptIfNeeded($entity->getToken()));
        }

        $this->em->persist($entity);
        $this->em->flush();
    }

    public function deleteEntity($entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }
}
