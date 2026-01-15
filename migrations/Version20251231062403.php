<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251231062403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Automatically verify emails for existing OAuth users (Google/Facebook)';
    }

    public function up(Schema $schema): void
    {
        // Автоматически подтверждаем почту для существующих пользователей OAuth (Google/Facebook)
        $this->addSql('UPDATE user SET email_verified_at = NOW() WHERE (google_id IS NOT NULL OR facebook_id IS NOT NULL) AND email_verified_at IS NULL');

    }

    public function down(Schema $schema): void
    {
        // Откат: снимаем подтверждение почты с OAuth пользователей (только для тестирования)
        $this->addSql('UPDATE user SET email_verified_at = NULL WHERE google_id IS NOT NULL OR facebook_id IS NOT NULL');

    }
}
