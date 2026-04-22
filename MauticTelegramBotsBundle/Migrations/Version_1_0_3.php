<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_3 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->getTable($this->concatPrefix('leads'))->hasColumn('telegram_username');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $leadsTable = $this->concatPrefix('leads');

        $this->addSql("
            ALTER TABLE `{$leadsTable}`
                ADD `telegram_username` VARCHAR(191) DEFAULT NULL,
                ADD INDEX `telegram_username_search` (`telegram_username`)
        ");
    }
}
