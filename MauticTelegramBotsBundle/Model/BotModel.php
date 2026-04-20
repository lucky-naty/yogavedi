<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Form\Type\BotType;
use MauticPlugin\MauticTelegramBotsBundle\Entity\BotRepository;
use Symfony\Component\Form\Form;

class BotModel extends FormModel
{
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
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function deleteEntity($entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }
}
