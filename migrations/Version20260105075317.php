<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105075317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE items CHANGE custom_int1_value custom_int1_value BIGINT DEFAULT NULL, CHANGE custom_int2_value custom_int2_value BIGINT DEFAULT NULL, CHANGE custom_int3_value custom_int3_value BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE items CHANGE custom_int1_value custom_int1_value INT DEFAULT NULL, CHANGE custom_int2_value custom_int2_value INT DEFAULT NULL, CHANGE custom_int3_value custom_int3_value INT DEFAULT NULL');
    }
}
