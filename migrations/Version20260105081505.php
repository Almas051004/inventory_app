<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105081505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE error_logs (id INT AUTO_INCREMENT NOT NULL, error_id VARCHAR(36) NOT NULL, message LONGTEXT NOT NULL, trace LONGTEXT DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, user_agent VARCHAR(255) DEFAULT NULL, user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_B4F80B60836088D7 (error_id), INDEX IDX_B4F80B60A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE error_logs ADD CONSTRAINT FK_B4F80B60A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE error_logs DROP FOREIGN KEY FK_B4F80B60A76ED395');
        $this->addSql('DROP TABLE error_logs');
    }
}
