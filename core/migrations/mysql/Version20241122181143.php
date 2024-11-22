<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241122181143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status field to mirrored server';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mirror_server ADD status VARCHAR(255) NOT NULL DEFAULT "active"');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mirror_server DROP status');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
