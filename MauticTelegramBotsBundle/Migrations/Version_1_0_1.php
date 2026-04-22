<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_1 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix('telegram_bots'))
            || !$schema->hasTable($this->concatPrefix('telegram_subscriptions'));
    }

    protected function up(): void
    {
        $telegramBotsTable = $this->concatPrefix('telegram_bots');
        $subscriptionsTable = $this->concatPrefix('telegram_subscriptions');
        $leadsTable = $this->concatPrefix('leads');

        $this->addSql("
            CREATE TABLE IF NOT EXISTS `{$telegramBotsTable}` (
                `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `description` LONGTEXT DEFAULT NULL,
                `token` VARCHAR(255) NOT NULL,
                `is_published` TINYINT(1) NOT NULL DEFAULT 1,
                `welcome_message` LONGTEXT DEFAULT NULL,
                `ask_phone_message` LONGTEXT DEFAULT NULL,
                `ask_phone` TINYINT(1) NOT NULL DEFAULT 0,
                `tags` LONGTEXT DEFAULT NULL,
                `webhook_url` VARCHAR(255) DEFAULT NULL,
                `webhook_registered_at` DATETIME DEFAULT NULL,
                `bot_username` VARCHAR(255) DEFAULT NULL,
                UNIQUE INDEX `telegram_bots_token` (`token`),
                INDEX `telegram_bots_is_published` (`is_published`),
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS `{$subscriptionsTable}` (
                `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                `bot_id` INT UNSIGNED NOT NULL,
                `lead_id` INT UNSIGNED NOT NULL,
                `chat_id` VARCHAR(191) NOT NULL,
                INDEX `telegram_subscriptions_bot_id` (`bot_id`),
                INDEX `telegram_subscriptions_lead_id` (`lead_id`),
                UNIQUE INDEX `telegram_subscriptions_bot_chat` (`bot_id`, `chat_id`),
                CONSTRAINT `fk_telegram_subscriptions_bot`
                    FOREIGN KEY (`bot_id`) REFERENCES `{$telegramBotsTable}` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_telegram_subscriptions_lead`
                    FOREIGN KEY (`lead_id`) REFERENCES `{$leadsTable}` (`id`) ON DELETE CASCADE,
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }
}
