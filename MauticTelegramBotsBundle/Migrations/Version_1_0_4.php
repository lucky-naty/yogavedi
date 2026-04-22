<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_4 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix('telegram_subscriptions'));

            return !$table->hasIndex('telegram_subscriptions_bot_chat');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $subscriptionsTable = $this->concatPrefix('telegram_subscriptions');

        $this->addSql("
            DELETE s1 FROM `{$subscriptionsTable}` s1
            INNER JOIN `{$subscriptionsTable}` s2
                ON s1.bot_id = s2.bot_id
                AND s1.chat_id = s2.chat_id
                AND s1.id > s2.id
        ");

        $this->addSql("
            ALTER TABLE `{$subscriptionsTable}`
                ADD UNIQUE INDEX `telegram_subscriptions_bot_chat` (`bot_id`, `chat_id`)
        ");
    }
}
