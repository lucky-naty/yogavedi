<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_2 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix('telegram_message_logs'));
    }

    protected function up(): void
    {
        $table = $this->concatPrefix('telegram_message_logs');

        $this->addSql("
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT AUTO_INCREMENT NOT NULL,
                `lead_id` BIGINT UNSIGNED DEFAULT NULL,
                `campaign_id` INT UNSIGNED DEFAULT NULL,
                `event_id` INT UNSIGNED DEFAULT NULL,
                `bot_id` INT DEFAULT NULL,
                `chat_id` VARCHAR(255) NOT NULL,
                `message_type` VARCHAR(50) NOT NULL,
                `message_text` LONGTEXT DEFAULT NULL,
                `telegram_message_id` BIGINT DEFAULT NULL,
                `status` VARCHAR(20) NOT NULL,
                `error_code` INT DEFAULT NULL,
                `error_description` LONGTEXT DEFAULT NULL,
                `date_added` DATETIME NOT NULL,
                INDEX `telegram_message_logs_lead` (`lead_id`),
                INDEX `telegram_message_logs_campaign_event` (`campaign_id`, `event_id`),
                INDEX `telegram_message_logs_chat` (`chat_id`),
                INDEX `telegram_message_logs_status` (`status`),
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ");
    }
}
