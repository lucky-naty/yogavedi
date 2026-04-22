<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_8 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix('telegram_bots'));

            return !$table->hasColumn('api_base_url') || !$table->hasColumn('webhook_base_url');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $telegramBotsTable = $this->concatPrefix('telegram_bots');

        $this->addSql("ALTER TABLE `{$telegramBotsTable}` ADD COLUMN IF NOT EXISTS `api_base_url` VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE `{$telegramBotsTable}` ADD COLUMN IF NOT EXISTS `webhook_base_url` VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
