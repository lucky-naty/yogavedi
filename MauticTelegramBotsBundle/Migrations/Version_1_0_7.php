<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_7 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix('telegram_bots'));

            return !$table->hasColumn('webhook_secret');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $telegramBotsTable = $this->concatPrefix('telegram_bots');

        $this->addSql("
            ALTER TABLE `{$telegramBotsTable}`
                ADD COLUMN `webhook_secret` VARCHAR(191) DEFAULT NULL,
                ADD INDEX `telegram_bots_webhook_secret` (`webhook_secret`)
        ");
    }
}
