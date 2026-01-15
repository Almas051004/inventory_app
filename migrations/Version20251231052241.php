<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231052241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_comments_created_at ON comments');
        $this->addSql('DROP INDEX ft_inventory_search ON inventory');
        $this->addSql('DROP INDEX idx_inventory_category_created ON inventory');
        $this->addSql('DROP INDEX idx_inventory_created_at ON inventory');
        $this->addSql('DROP INDEX idx_inventory_creator_created ON inventory');
        $this->addSql('DROP INDEX idx_inventory_is_public ON inventory');
        $this->addSql('DROP INDEX idx_inventory_updated_at ON inventory');
        $this->addSql('ALTER TABLE inventory_access CHANGE access_type access_type VARCHAR(10) NOT NULL');
        $this->addSql('DROP INDEX ft_items_search ON items');
        $this->addSql('DROP INDEX idx_items_created_at ON items');
        $this->addSql('DROP INDEX idx_items_created_by ON items');
        $this->addSql('DROP INDEX idx_items_inventory_created ON items');
        $this->addSql('DROP INDEX idx_items_inventory_updated ON items');
        $this->addSql('DROP INDEX idx_items_updated_at ON items');
        $this->addSql('DROP INDEX idx_likes_item_user ON likes');
        $this->addSql('DROP INDEX idx_user_created_at ON user');
        $this->addSql('DROP INDEX idx_user_is_blocked ON user');
        $this->addSql('ALTER TABLE user ADD email_verified_at DATETIME DEFAULT NULL, ADD email_verification_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C4995C67 ON user (email_verification_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_comments_created_at ON comments (created_at)');
        $this->addSql('CREATE FULLTEXT INDEX ft_inventory_search ON inventory (title, description)');
        $this->addSql('CREATE INDEX idx_inventory_category_created ON inventory (category_id, created_at)');
        $this->addSql('CREATE INDEX idx_inventory_created_at ON inventory (created_at)');
        $this->addSql('CREATE INDEX idx_inventory_creator_created ON inventory (creator_id, created_at)');
        $this->addSql('CREATE INDEX idx_inventory_is_public ON inventory (is_public)');
        $this->addSql('CREATE INDEX idx_inventory_updated_at ON inventory (updated_at)');
        $this->addSql('ALTER TABLE inventory_access CHANGE access_type access_type VARCHAR(10) DEFAULT \'write\' NOT NULL');
        $this->addSql('CREATE FULLTEXT INDEX ft_items_search ON items (custom_string1_value, custom_string2_value, custom_string3_value, custom_text1_value, custom_text2_value, custom_text3_value)');
        $this->addSql('CREATE INDEX idx_items_created_at ON items (created_at)');
        $this->addSql('CREATE INDEX idx_items_created_by ON items (created_by_id)');
        $this->addSql('CREATE INDEX idx_items_inventory_created ON items (inventory_id, created_at)');
        $this->addSql('CREATE INDEX idx_items_inventory_updated ON items (inventory_id, updated_at)');
        $this->addSql('CREATE INDEX idx_items_updated_at ON items (updated_at)');
        $this->addSql('CREATE INDEX idx_likes_item_user ON likes (item_id, user_id)');
        $this->addSql('DROP INDEX UNIQ_8D93D649C4995C67 ON user');
        $this->addSql('ALTER TABLE user DROP email_verified_at, DROP email_verification_token');
        $this->addSql('CREATE INDEX idx_user_created_at ON user (created_at)');
        $this->addSql('CREATE INDEX idx_user_is_blocked ON user (is_blocked)');
    }
}
