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

    $services->load('MauticPlugin\\MauticMaxBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, [])).'}');

    $services->alias('mautic.max.helper.message', MauticPlugin\MauticMaxBundle\Helper\MaxMessageHelper::class);
    $services->alias('mautic.max.form.type.send_message', MauticPlugin\MauticMaxBundle\Form\Type\MaxSendMessageType::class);
};
