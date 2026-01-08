<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for full-text search indexes
 */
final class Version20251225071508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add full-text search indexes for inventories and items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory ADD FULLTEXT INDEX ft_inventory_search (title, description)');

        $this->addSql('ALTER TABLE items ADD FULLTEXT INDEX ft_items_search (custom_string1_value, custom_string2_value, custom_string3_value, custom_text1_value, custom_text2_value, custom_text3_value)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ft_inventory_search ON inventory');
        $this->addSql('DROP INDEX ft_items_search ON items');
    }
}
