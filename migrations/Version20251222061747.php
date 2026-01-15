<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222061747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comments (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, inventory_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_5F9E962A9EEA759 (inventory_id), INDEX IDX_5F9E962AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, image_url VARCHAR(500) DEFAULT NULL, is_public TINYINT DEFAULT 0 NOT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, custom_id_format JSON DEFAULT NULL, custom_string1_state TINYINT DEFAULT 0, custom_string1_name VARCHAR(255) DEFAULT NULL, custom_string1_description LONGTEXT DEFAULT NULL, custom_string1_show_in_table TINYINT DEFAULT 0, custom_string2_state TINYINT DEFAULT 0, custom_string2_name VARCHAR(255) DEFAULT NULL, custom_string2_description LONGTEXT DEFAULT NULL, custom_string2_show_in_table TINYINT DEFAULT 0, custom_string3_state TINYINT DEFAULT 0, custom_string3_name VARCHAR(255) DEFAULT NULL, custom_string3_description LONGTEXT DEFAULT NULL, custom_string3_show_in_table TINYINT DEFAULT 0, custom_text1_state TINYINT DEFAULT 0, custom_text1_name VARCHAR(255) DEFAULT NULL, custom_text1_description LONGTEXT DEFAULT NULL, custom_text1_show_in_table TINYINT DEFAULT 0, custom_text2_state TINYINT DEFAULT 0, custom_text2_name VARCHAR(255) DEFAULT NULL, custom_text2_description LONGTEXT DEFAULT NULL, custom_text2_show_in_table TINYINT DEFAULT 0, custom_text3_state TINYINT DEFAULT 0, custom_text3_name VARCHAR(255) DEFAULT NULL, custom_text3_description LONGTEXT DEFAULT NULL, custom_text3_show_in_table TINYINT DEFAULT 0, custom_int1_state TINYINT DEFAULT 0, custom_int1_name VARCHAR(255) DEFAULT NULL, custom_int1_description LONGTEXT DEFAULT NULL, custom_int1_show_in_table TINYINT DEFAULT 0, custom_int2_state TINYINT DEFAULT 0, custom_int2_name VARCHAR(255) DEFAULT NULL, custom_int2_description LONGTEXT DEFAULT NULL, custom_int2_show_in_table TINYINT DEFAULT 0, custom_int3_state TINYINT DEFAULT 0, custom_int3_name VARCHAR(255) DEFAULT NULL, custom_int3_description LONGTEXT DEFAULT NULL, custom_int3_show_in_table TINYINT DEFAULT 0, custom_bool1_state TINYINT DEFAULT 0, custom_bool1_name VARCHAR(255) DEFAULT NULL, custom_bool1_description LONGTEXT DEFAULT NULL, custom_bool1_show_in_table TINYINT DEFAULT 0, custom_bool2_state TINYINT DEFAULT 0, custom_bool2_name VARCHAR(255) DEFAULT NULL, custom_bool2_description LONGTEXT DEFAULT NULL, custom_bool2_show_in_table TINYINT DEFAULT 0, custom_bool3_state TINYINT DEFAULT 0, custom_bool3_name VARCHAR(255) DEFAULT NULL, custom_bool3_description LONGTEXT DEFAULT NULL, custom_bool3_show_in_table TINYINT DEFAULT 0, custom_link1_state TINYINT DEFAULT 0, custom_link1_name VARCHAR(255) DEFAULT NULL, custom_link1_description LONGTEXT DEFAULT NULL, custom_link1_show_in_table TINYINT DEFAULT 0, custom_link2_state TINYINT DEFAULT 0, custom_link2_name VARCHAR(255) DEFAULT NULL, custom_link2_description LONGTEXT DEFAULT NULL, custom_link2_show_in_table TINYINT DEFAULT 0, custom_link3_state TINYINT DEFAULT 0, custom_link3_name VARCHAR(255) DEFAULT NULL, custom_link3_description LONGTEXT DEFAULT NULL, custom_link3_show_in_table TINYINT DEFAULT 0, creator_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_B12D4A3661220EA6 (creator_id), INDEX IDX_B12D4A3612469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_tags (inventory_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_251A5B089EEA759 (inventory_id), INDEX IDX_251A5B08BAD26311 (tag_id), PRIMARY KEY (inventory_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_access (id INT AUTO_INCREMENT NOT NULL, inventory_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_6B5B7FF9EEA759 (inventory_id), INDEX IDX_6B5B7FFA76ED395 (user_id), UNIQUE INDEX unique_inventory_user (inventory_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE items (id INT AUTO_INCREMENT NOT NULL, custom_id VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, version INT DEFAULT 1 NOT NULL, custom_string1_value VARCHAR(255) DEFAULT NULL, custom_string2_value VARCHAR(255) DEFAULT NULL, custom_string3_value VARCHAR(255) DEFAULT NULL, custom_text1_value LONGTEXT DEFAULT NULL, custom_text2_value LONGTEXT DEFAULT NULL, custom_text3_value LONGTEXT DEFAULT NULL, custom_int1_value INT DEFAULT NULL, custom_int2_value INT DEFAULT NULL, custom_int3_value INT DEFAULT NULL, custom_bool1_value TINYINT DEFAULT NULL, custom_bool2_value TINYINT DEFAULT NULL, custom_bool3_value TINYINT DEFAULT NULL, custom_link1_value VARCHAR(500) DEFAULT NULL, custom_link2_value VARCHAR(500) DEFAULT NULL, custom_link3_value VARCHAR(500) DEFAULT NULL, inventory_id INT NOT NULL, created_by_id INT NOT NULL, INDEX IDX_E11EE94D9EEA759 (inventory_id), INDEX IDX_E11EE94DB03A8386 (created_by_id), UNIQUE INDEX unique_inventory_custom_id (inventory_id, custom_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE likes (id INT AUTO_INCREMENT NOT NULL, item_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_49CA4E7D126F525E (item_id), INDEX IDX_49CA4E7DA76ED395 (user_id), UNIQUE INDEX unique_user_item (user_id, item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tags (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX unique_tag_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, is_blocked TINYINT DEFAULT 0 NOT NULL, google_id VARCHAR(255) DEFAULT NULL, facebook_id VARCHAR(255) DEFAULT NULL, avatar_url VARCHAR(500) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D64976F5C865 (google_id), UNIQUE INDEX UNIQ_8D93D6499BE8FD98 (facebook_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962A9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E962AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE inventory ADD CONSTRAINT FK_B12D4A3661220EA6 FOREIGN KEY (creator_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE inventory ADD CONSTRAINT FK_B12D4A3612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE inventory_tags ADD CONSTRAINT FK_251A5B089EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_tags ADD CONSTRAINT FK_251A5B08BAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_access ADD CONSTRAINT FK_6B5B7FF9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_access ADD CONSTRAINT FK_6B5B7FFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94DB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE likes ADD CONSTRAINT FK_49CA4E7D126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE likes ADD CONSTRAINT FK_49CA4E7DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962A9EEA759');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E962AA76ED395');
        $this->addSql('ALTER TABLE inventory DROP FOREIGN KEY FK_B12D4A3661220EA6');
        $this->addSql('ALTER TABLE inventory DROP FOREIGN KEY FK_B12D4A3612469DE2');
        $this->addSql('ALTER TABLE inventory_tags DROP FOREIGN KEY FK_251A5B089EEA759');
        $this->addSql('ALTER TABLE inventory_tags DROP FOREIGN KEY FK_251A5B08BAD26311');
        $this->addSql('ALTER TABLE inventory_access DROP FOREIGN KEY FK_6B5B7FF9EEA759');
        $this->addSql('ALTER TABLE inventory_access DROP FOREIGN KEY FK_6B5B7FFA76ED395');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D9EEA759');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94DB03A8386');
        $this->addSql('ALTER TABLE likes DROP FOREIGN KEY FK_49CA4E7D126F525E');
        $this->addSql('ALTER TABLE likes DROP FOREIGN KEY FK_49CA4E7DA76ED395');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE comments');
        $this->addSql('DROP TABLE inventory');
        $this->addSql('DROP TABLE inventory_tags');
        $this->addSql('DROP TABLE inventory_access');
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE likes');
        $this->addSql('DROP TABLE tags');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
