<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241108221856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change frontmatter id to be a ulid';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE front_matter
             CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid_binary)\',
             CHANGE last_updated last_updated DATETIME NOT NULL COMMENT \'(DC2Type:datetimetz_immutable)\'
        ');
        $this->addSql("
            UPDATE front_matter
                SET id = UNHEX(CONCAT(
                    LPAD(HEX(UNIX_TIMESTAMP(NOW(3)) * 1000), 12, '0'),
                    '7',
                    SUBSTR(HEX(RANDOM_BYTES(2)), 2),
                    HEX(FLOOR(RAND() * 4 + 8)),
                    SUBSTR(HEX(RANDOM_BYTES(8)), 2)
                ))
        ");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(true, 'This migration cannot be rolled back.  Manual intervention required.');
        $this->addSql('ALTER TABLE front_matter CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE last_updated last_updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\'');
    }
}
