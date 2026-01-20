<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260117070050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE support_ticket (id INT AUTO_INCREMENT NOT NULL, summary VARCHAR(255) NOT NULL, priority VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, page_url VARCHAR(1000) NOT NULL, data_json JSON DEFAULT NULL, file_path VARCHAR(1000) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, inventory_id INT DEFAULT NULL, INDEX IDX_1F5A4D53A76ED395 (user_id), INDEX IDX_1F5A4D539EEA759 (inventory_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D539EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D539EEA759');
        $this->addSql('DROP TABLE support_ticket');
    }
}
