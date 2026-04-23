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

    $services->load('MauticPlugin\\MauticMaxBotsBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, ['Entity', 'Migrations'])).'}');

    $services->load('MauticPlugin\\MauticMaxBotsBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->alias('mautic.max.bots.model.bot', MauticPlugin\MauticMaxBotsBundle\Model\BotModel::class);
    $services->alias('mautic.max.bots.api_helper', MauticPlugin\MauticMaxBotsBundle\Helper\MaxApiHelper::class);
};
