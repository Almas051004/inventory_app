<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260118072542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY `FK_1F5A4D539EEA759`');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY `FK_1F5A4D53A76ED395`');
        $this->addSql('ALTER TABLE support_ticket ADD updated_at DATETIME DEFAULT NULL, CHANGE priority priority VARCHAR(50) DEFAULT \'medium\' NOT NULL, CHANGE status status VARCHAR(50) DEFAULT \'new\' NOT NULL');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D539EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D53A76ED395');
        $this->addSql('ALTER TABLE support_ticket DROP FOREIGN KEY FK_1F5A4D539EEA759');
        $this->addSql('ALTER TABLE support_ticket DROP updated_at, CHANGE priority priority VARCHAR(50) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT `FK_1F5A4D53A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE support_ticket ADD CONSTRAINT `FK_1F5A4D539EEA759` FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
