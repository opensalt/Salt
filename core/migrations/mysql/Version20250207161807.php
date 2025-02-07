<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250207161807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure ls_doc is not null for items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ls_item CHANGE ls_doc_id ls_doc_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ls_item CHANGE ls_doc_id ls_doc_id INT DEFAULT NULL');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
