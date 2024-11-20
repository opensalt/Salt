<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241120173059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add messenger queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS `messenger_messages` (
              `id` bigint NOT NULL AUTO_INCREMENT,
              `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
              `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              `available_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              `delivered_at` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              PRIMARY KEY (`id`),
              KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
              KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
              KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
    }
}
