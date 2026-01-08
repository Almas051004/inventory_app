<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105061729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory ADD custom_string1_min_length INT DEFAULT NULL, ADD custom_string1_max_length INT DEFAULT NULL, ADD custom_string1_regex VARCHAR(500) DEFAULT NULL, ADD custom_string2_min_length INT DEFAULT NULL, ADD custom_string2_max_length INT DEFAULT NULL, ADD custom_string2_regex VARCHAR(500) DEFAULT NULL, ADD custom_string3_min_length INT DEFAULT NULL, ADD custom_string3_max_length INT DEFAULT NULL, ADD custom_string3_regex VARCHAR(500) DEFAULT NULL, ADD custom_int1_min_value INT DEFAULT NULL, ADD custom_int1_max_value INT DEFAULT NULL, ADD custom_int2_min_value INT DEFAULT NULL, ADD custom_int2_max_value INT DEFAULT NULL, ADD custom_int3_min_value INT DEFAULT NULL, ADD custom_int3_max_value INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory DROP custom_string1_min_length, DROP custom_string1_max_length, DROP custom_string1_regex, DROP custom_string2_min_length, DROP custom_string2_max_length, DROP custom_string2_regex, DROP custom_string3_min_length, DROP custom_string3_max_length, DROP custom_string3_regex, DROP custom_int1_min_value, DROP custom_int1_max_value, DROP custom_int2_min_value, DROP custom_int2_max_value, DROP custom_int3_min_value, DROP custom_int3_max_value');
    }
}
