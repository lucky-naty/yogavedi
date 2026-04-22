<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_2 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->getTable($this->concatPrefix('leads'))->hasColumn('telegram_chat_id');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $leadsTable = $this->concatPrefix('leads');

        $this->addSql("
            ALTER TABLE `{$leadsTable}`
                ADD `telegram_chat_id` VARCHAR(191) DEFAULT NULL,
                ADD INDEX `telegram_chat_id_search` (`telegram_chat_id`)
        ");
    }
}
