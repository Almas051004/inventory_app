<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add performance indexes for filtering and sorting
 */
final class Version20251226055233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for filtering and sorting operations';
    }

    public function up(Schema $schema): void
    {
        // Add indexes for inventory table filtering and sorting
        $this->addSql('CREATE INDEX idx_inventory_created_at ON inventory (created_at)');
        $this->addSql('CREATE INDEX idx_inventory_updated_at ON inventory (updated_at)');
        $this->addSql('CREATE INDEX idx_inventory_is_public ON inventory (is_public)');
        $this->addSql('CREATE INDEX idx_inventory_category_created ON inventory (category_id, created_at)');
        $this->addSql('CREATE INDEX idx_inventory_creator_created ON inventory (creator_id, created_at)');

        // Add indexes for items table filtering and sorting
        $this->addSql('CREATE INDEX idx_items_created_at ON items (created_at)');
        $this->addSql('CREATE INDEX idx_items_updated_at ON items (updated_at)');
        $this->addSql('CREATE INDEX idx_items_created_by ON items (created_by_id)');
        $this->addSql('CREATE INDEX idx_items_inventory_created ON items (inventory_id, created_at)');
        $this->addSql('CREATE INDEX idx_items_inventory_updated ON items (inventory_id, updated_at)');

        // Add indexes for user table
        $this->addSql('CREATE INDEX idx_user_created_at ON user (created_at)');
        $this->addSql('CREATE INDEX idx_user_is_blocked ON user (is_blocked)');

        // Add indexes for comments and likes for performance
        $this->addSql('CREATE INDEX idx_comments_created_at ON comments (created_at)');
        $this->addSql('CREATE INDEX idx_likes_item_user ON likes (item_id, user_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove indexes in reverse order
        $this->addSql('DROP INDEX idx_likes_item_user ON likes');
        $this->addSql('DROP INDEX idx_comments_created_at ON comments');
        $this->addSql('DROP INDEX idx_user_is_blocked ON user');
        $this->addSql('DROP INDEX idx_user_created_at ON user');
        $this->addSql('DROP INDEX idx_items_inventory_updated ON items');
        $this->addSql('DROP INDEX idx_items_inventory_created ON items');
        $this->addSql('DROP INDEX idx_items_created_by ON items');
        $this->addSql('DROP INDEX idx_items_updated_at ON items');
        $this->addSql('DROP INDEX idx_items_created_at ON items');
        $this->addSql('DROP INDEX idx_inventory_creator_created ON inventory');
        $this->addSql('DROP INDEX idx_inventory_category_created ON inventory');
        $this->addSql('DROP INDEX idx_inventory_is_public ON inventory');
        $this->addSql('DROP INDEX idx_inventory_updated_at ON inventory');
        $this->addSql('DROP INDEX idx_inventory_created_at ON inventory');
    }
}
