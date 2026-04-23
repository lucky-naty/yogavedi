<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

final class Version_1_0_0 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->hasTable($this->concatPrefix('max_message_logs'));
        } catch (SchemaException) {
            return true;
        }
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix('max_message_logs');

        if ($this->tableExists($tableName)) {
            return;
        }

        $this->addSql("CREATE TABLE `{$tableName}` (
            id INT AUTO_INCREMENT NOT NULL,
            lead_id INT DEFAULT NULL,
            campaign_id INT DEFAULT NULL,
            event_id INT DEFAULT NULL,
            bot_id INT DEFAULT NULL,
            chat_id VARCHAR(191) NOT NULL,
            message_text LONGTEXT DEFAULT NULL,
            status VARCHAR(32) NOT NULL,
            error_code VARCHAR(64) DEFAULT NULL,
            error_description LONGTEXT DEFAULT NULL,
            date_added DATETIME NOT NULL,
            INDEX idx_max_message_logs_lead_id (lead_id),
            INDEX idx_max_message_logs_campaign_id (campaign_id),
            INDEX idx_max_message_logs_event_id (event_id),
            INDEX idx_max_message_logs_bot_id (bot_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    private function tableExists(string $tableName): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([$tableName]);
    }
}
