<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_6 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix('telegram_bots'));

            return !$table->hasColumn('phone_received_message');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $telegramBotsTable = $this->concatPrefix('telegram_bots');

        $this->addSql("
            ALTER TABLE `{$telegramBotsTable}`
                ADD COLUMN `phone_received_message` LONGTEXT DEFAULT NULL
        ");
    }
}
