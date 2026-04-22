<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_5 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix('telegram_subscriptions'));

            return !$table->hasColumn('is_active') || !$table->hasColumn('unsubscribed_at');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $subscriptionsTable = $this->concatPrefix('telegram_subscriptions');

        $this->addSql("
            ALTER TABLE `{$subscriptionsTable}`
                ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                ADD COLUMN `unsubscribed_at` DATETIME DEFAULT NULL,
                ADD INDEX `telegram_subscriptions_active` (`is_active`)
        ");
    }
}
