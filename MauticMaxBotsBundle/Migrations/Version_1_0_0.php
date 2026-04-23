<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_0 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->hasTable($this->concatPrefix('max_bots'))
                || !$schema->hasTable($this->concatPrefix('max_subscriptions'))
                || !$schema->hasTable($this->concatPrefix('max_incoming_events'));
        } catch (SchemaException) {
            return true;
        }
    }

    protected function up(): void
    {
        $botsTable = $this->concatPrefix('max_bots');
        $subscriptionsTable = $this->concatPrefix('max_subscriptions');
        $incomingEventsTable = $this->concatPrefix('max_incoming_events');

        if (!$this->tableExists($botsTable)) {
            $this->addSql("CREATE TABLE `{$botsTable}` (
                `id` INT AUTO_INCREMENT NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `token` LONGTEXT NOT NULL,
                `username` VARCHAR(191) NOT NULL DEFAULT '',
                `display_name` VARCHAR(191) NOT NULL DEFAULT '',
                `is_published` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `welcome_message` LONGTEXT NOT NULL,
                `phone_request_message` LONGTEXT NOT NULL,
                `contact_success_message` LONGTEXT NOT NULL,
                `api_base_url` VARCHAR(255) NOT NULL DEFAULT '',
                `webhook_base_url` VARCHAR(255) NOT NULL DEFAULT '',
                `webhook_secret` VARCHAR(191) NOT NULL,
                `webhook_status` VARCHAR(32) NOT NULL DEFAULT 'idle',
                `last_webhook_error` LONGTEXT DEFAULT NULL,
                `last_webhook_synced_at` DATETIME DEFAULT NULL,
                UNIQUE INDEX `uniq_max_bots_webhook_secret` (`webhook_secret`),
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }

        if (!$this->tableExists($subscriptionsTable)) {
            $this->addSql("CREATE TABLE `{$subscriptionsTable}` (
                `id` INT AUTO_INCREMENT NOT NULL,
                `bot_id` INT NOT NULL,
                `lead_id` INT DEFAULT NULL,
                `chat_id` VARCHAR(191) NOT NULL,
                `max_user_id` VARCHAR(191) DEFAULT NULL,
                `username` VARCHAR(191) DEFAULT NULL,
                `first_name` VARCHAR(191) DEFAULT NULL,
                `last_name` VARCHAR(191) DEFAULT NULL,
                `language_code` VARCHAR(32) DEFAULT NULL,
                `phone_number` VARCHAR(64) DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
                `is_phone_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
                `subscribed_at` DATETIME DEFAULT NULL,
                `unsubscribed_at` DATETIME DEFAULT NULL,
                `last_active_at` DATETIME DEFAULT NULL,
                `last_message_at` DATETIME DEFAULT NULL,
                `date_added` DATETIME NOT NULL,
                `date_modified` DATETIME DEFAULT NULL,
                INDEX `idx_max_subscriptions_bot` (`bot_id`),
                INDEX `idx_max_subscriptions_lead` (`lead_id`),
                UNIQUE INDEX `uniq_max_subscription_bot_chat` (`bot_id`, `chat_id`),
                PRIMARY KEY(`id`),
                CONSTRAINT `fk_max_subscriptions_bot` FOREIGN KEY (`bot_id`) REFERENCES `{$botsTable}` (`id`) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }

        if (!$this->tableExists($incomingEventsTable)) {
            $this->addSql("CREATE TABLE `{$incomingEventsTable}` (
                `id` INT AUTO_INCREMENT NOT NULL,
                `bot_id` INT NOT NULL,
                `subscription_id` INT DEFAULT NULL,
                `event_type` VARCHAR(64) NOT NULL,
                `event_id` VARCHAR(191) NOT NULL,
                `payload` LONGTEXT NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `processed_at` DATETIME DEFAULT NULL,
                `error_message` LONGTEXT DEFAULT NULL,
                `date_added` DATETIME NOT NULL,
                INDEX `idx_max_incoming_events_bot` (`bot_id`),
                INDEX `idx_max_incoming_events_subscription` (`subscription_id`),
                INDEX `idx_max_incoming_events_status` (`status`),
                UNIQUE INDEX `uniq_max_incoming_events_bot_event` (`bot_id`, `event_id`),
                PRIMARY KEY(`id`),
                CONSTRAINT `fk_max_incoming_events_bot` FOREIGN KEY (`bot_id`) REFERENCES `{$botsTable}` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_max_incoming_events_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `{$subscriptionsTable}` (`id`) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        }

        if ($this->tableExists($incomingEventsTable) && !$this->indexExists($incomingEventsTable, 'uniq_max_incoming_events_bot_event')) {
            $this->addSql("CREATE UNIQUE INDEX `uniq_max_incoming_events_bot_event` ON `{$incomingEventsTable}` (`bot_id`, `event_id`)");
        }
    }

    private function tableExists(string $tableName): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$tableName]);
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return $this->connection->createSchemaManager()->listTableDetails($tableName)->hasIndex($indexName);
    }
}
