<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250128221210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add discriminator to item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ls_item ADD discriminator INT DEFAULT 0 NOT NULL AFTER id');
        $this->addSql('CREATE INDEX type_idx ON ls_item (discriminator)');
        $this->addSql("UPDATE ls_item SET discriminator = 1, item_type_id = NULL WHERE item_type_id  = (SELECT id FROM ls_def_item_type WHERE identifier = '27b1b616-d9cb-11ef-881e-b04f1302c2ee')"); // Job
        $this->addSql("UPDATE ls_item SET discriminator = 2, item_type_id = NULL WHERE item_type_id  = (SELECT id FROM ls_def_item_type WHERE identifier = '461cc782-dcda-11ef-a68b-b04f1302c2ee')"); // Course
        $this->addSql("DELETE FROM ls_def_item_type WHERE identifier IN ('27b1b616-d9cb-11ef-881e-b04f1302c2ee', '461cc782-dcda-11ef-a68b-b04f1302c2ee')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX type_idx ON ls_item');
        $this->addSql('ALTER TABLE ls_item DROP discriminator');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
