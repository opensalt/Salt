<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Framework\LsDefItemType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250123204700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add "Job" item type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            INSERT INTO ls_def_item_type (identifier, uri, extra, updated_at, changed_at, title, description, code, hierarchy_code)
                                   VALUES(:identifier, :uri, :extra, NOW(), NOW(), :title, :description, :code, :hierarchy_code)
         ', [
            'identifier' => LsDefItemType::TYPE_COURSE_IDENTIFIER,
            'uri' => 'local:'.LsDefItemType::TYPE_COURSE_IDENTIFIER,
            'extra' => '{"system-value": true, "system-class": "Course"}',
            'title' => 'Course',
            'description' => 'Represents a course',
            'code' => 'course',
            'hierarchy_code' => 'course',
        ]);

        $this->addSql('UPDATE ls_def_item_type SET extra = :extra WHERE identifier = :identifier', ['identifier' => LsDefItemType::TYPE_JOB_IDENTIFIER, 'extra' => '{"system-value": true, "system-class": "Job"}']);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE IGNORE FROM ls_def_item_type WHERE identifier = :identifier', ['identifier' => LsDefItemType::TYPE_COURSE_IDENTIFIER]);
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
