<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add access_type field to inventory_access table
 */
final class Version20251230074446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add access_type field to inventory_access table to support read/write permissions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_access ADD access_type VARCHAR(10) NOT NULL DEFAULT \'write\'');
        $this->addSql('UPDATE inventory_access SET access_type = \'write\' WHERE access_type = \'\'');
        $this->addSql('ALTER TABLE inventory_access ADD CONSTRAINT CHK_access_type CHECK (access_type IN (\'read\', \'write\'))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_access DROP CONSTRAINT CHK_access_type');
        $this->addSql('ALTER TABLE inventory_access DROP access_type');
    }
}
