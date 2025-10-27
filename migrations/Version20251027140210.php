<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027140210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE command_histories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, vehicle_id INTEGER NOT NULL, command_type VARCHAR(255) DEFAULT NULL, command_text CLOB DEFAULT NULL, response CLOB DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, sent_at DATETIME DEFAULT NULL, received_at DATETIME DEFAULT NULL, status VARCHAR(50) DEFAULT NULL, additional_data CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, CONSTRAINT FK_48C081A8545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_48C081A8545317D1 ON command_histories (vehicle_id)');
        $this->addSql('CREATE INDEX idx_vehicle_sent_at ON command_histories (vehicle_id, sent_at)');
        $this->addSql('CREATE TABLE vehicle_tracks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, vehicle_id INTEGER NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, speed DOUBLE PRECISION DEFAULT NULL, course DOUBLE PRECISION DEFAULT NULL, altitude DOUBLE PRECISION DEFAULT NULL, satellites INTEGER DEFAULT NULL, timestamp DATETIME NOT NULL, additional_data CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, CONSTRAINT FK_8DB32BFD545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8DB32BFD545317D1 ON vehicle_tracks (vehicle_id)');
        $this->addSql('CREATE INDEX idx_vehicle_timestamp ON vehicle_tracks (vehicle_id, timestamp)');
        $this->addSql('CREATE TABLE vehicles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, external_id VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, plate_number VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, speed DOUBLE PRECISION DEFAULT NULL, course DOUBLE PRECISION DEFAULT NULL, last_position_time DATETIME DEFAULT NULL, additional_data CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FCE69FA9F75D7B0 ON vehicles (external_id)');
        $this->addSql('CREATE INDEX idx_external_id ON vehicles (external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE command_histories');
        $this->addSql('DROP TABLE vehicle_tracks');
        $this->addSql('DROP TABLE vehicles');
    }
}
