<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701142855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE credential_reveal (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reveal_token VARCHAR(64) NOT NULL, secret_ciphertext CLOB DEFAULT NULL, viewed_at DATETIME DEFAULT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, projet_id INTEGER NOT NULL, CONSTRAINT FK_D27B2423C18272 FOREIGN KEY (projet_id) REFERENCES projet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D27B24234B1EB8FA ON credential_reveal (reveal_token)');
        $this->addSql('CREATE INDEX IDX_D27B2423C18272 ON credential_reveal (projet_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE credential_reveal');
    }
}
