<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701114730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE agent_connection (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, base_url VARCHAR(255) NOT NULL, bearer_token_encrypted CLOB DEFAULT NULL, last_health_check_at DATETIME DEFAULT NULL, last_health_check_status VARCHAR(20) NOT NULL, agent_version VARCHAR(50) DEFAULT NULL, agent_db_engine VARCHAR(20) DEFAULT NULL, etablissement_id INTEGER NOT NULL, CONSTRAINT FK_58B1A269FF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_58B1A269FF631228 ON agent_connection (etablissement_id)');
        $this->addSql('CREATE TABLE classe (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, annee_scolaire VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, etablissement_id INTEGER NOT NULL, CONSTRAINT FK_8F87BF96FF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8F87BF96FF631228 ON classe (etablissement_id)');
        $this->addSql('CREATE TABLE eleve (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, matricule VARCHAR(50) DEFAULT NULL, login VARCHAR(32) NOT NULL, login_suffix INTEGER DEFAULT NULL, imported_at DATETIME NOT NULL, active BOOLEAN NOT NULL, classe_id INTEGER NOT NULL, CONSTRAINT FK_ECA105F78F5EA509 FOREIGN KEY (classe_id) REFERENCES classe (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_ECA105F78F5EA509 ON eleve (classe_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_eleve_login ON eleve (login)');
        $this->addSql('CREATE TABLE etablissement (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, db_engine VARCHAR(20) NOT NULL, web_root_base VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE naming_pattern (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(100) NOT NULL, template VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL, max_length INTEGER DEFAULT NULL, etablissement_id INTEGER NOT NULL, CONSTRAINT FK_9D067FDAFF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9D067FDAFF631228 ON naming_pattern (etablissement_id)');
        $this->addSql('CREATE TABLE projet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, db_engine VARCHAR(20) NOT NULL, db_name VARCHAR(63) DEFAULT NULL, db_user VARCHAR(63) DEFAULT NULL, web_path VARCHAR(255) DEFAULT NULL, linux_username VARCHAR(32) DEFAULT NULL, ssh_auth_method VARCHAR(20) NOT NULL, ssh_public_key_fingerprint VARCHAR(255) DEFAULT NULL, provisioning_status VARCHAR(20) NOT NULL, provisioning_error CLOB DEFAULT NULL, provisioned_at DATETIME DEFAULT NULL, deprovisioned_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, eleve_id INTEGER NOT NULL, CONSTRAINT FK_50159CA9A6CC7B2 FOREIGN KEY (eleve_id) REFERENCES eleve (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_50159CA9A6CC7B2 ON projet (eleve_id)');
        $this->addSql('CREATE TABLE provisioning_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, step VARCHAR(20) NOT NULL, "action" VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, agent_request_id VARCHAR(64) DEFAULT NULL, detail CLOB DEFAULT NULL, occurred_at DATETIME NOT NULL, projet_id INTEGER NOT NULL, CONSTRAINT FK_6A5E3150C18272 FOREIGN KEY (projet_id) REFERENCES projet (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6A5E3150C18272 ON provisioning_event (projet_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE agent_connection');
        $this->addSql('DROP TABLE classe');
        $this->addSql('DROP TABLE eleve');
        $this->addSql('DROP TABLE etablissement');
        $this->addSql('DROP TABLE naming_pattern');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE provisioning_event');
    }
}
