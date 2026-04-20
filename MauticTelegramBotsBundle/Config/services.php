<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = ['Entity'];

    $services->load('MauticPlugin\\MauticTelegramBotsBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->load('MauticPlugin\\MauticTelegramBotsBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->alias('mautic.telegramBots.model.bot', MauticPlugin\MauticTelegramBotsBundle\Model\BotModel::class);
    $services->alias('mautic.telegram.bots.api_helper', MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper::class);
    $services->alias('mautic.telegram.bots.contact_manager', MauticPlugin\MauticTelegramBotsBundle\Helper\ContactManager::class);
};
