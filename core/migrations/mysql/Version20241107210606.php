<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241107210606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add table for front matter templates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE front_matter (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, source LONGTEXT NOT NULL, last_updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', UNIQUE INDEX UNIQ_5222DD863C0BE965 (filename), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE front_matter');
    }
}
