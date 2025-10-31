<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031091808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__vehicles AS SELECT id, external_id, name, plate_number, latitude, longitude, speed, course, last_position_time, additional_data, created_at, updated_at, gps_status, last_server_data_time, connection_status, status_checked_at FROM vehicles');
        $this->addSql('DROP TABLE vehicles');
        $this->addSql('CREATE TABLE vehicles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_vehicle_id INTEGER DEFAULT NULL, external_id VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, plate_number VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, speed DOUBLE PRECISION DEFAULT NULL, course DOUBLE PRECISION DEFAULT NULL, last_position_time DATETIME DEFAULT NULL, additional_data CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, gps_status VARCHAR(20) DEFAULT NULL, last_server_data_time DATETIME DEFAULT NULL, connection_status VARCHAR(20) DEFAULT NULL, status_checked_at DATETIME DEFAULT NULL, device_type VARCHAR(20) DEFAULT NULL, CONSTRAINT FK_1FCE69FA13109381 FOREIGN KEY (parent_vehicle_id) REFERENCES vehicles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO vehicles (id, external_id, name, plate_number, latitude, longitude, speed, course, last_position_time, additional_data, created_at, updated_at, gps_status, last_server_data_time, connection_status, status_checked_at) SELECT id, external_id, name, plate_number, latitude, longitude, speed, course, last_position_time, additional_data, created_at, updated_at, gps_status, last_server_data_time, connection_status, status_checked_at FROM __temp__vehicles');
        $this->addSql('DROP TABLE __temp__vehicles');
        $this->addSql('CREATE INDEX idx_external_id ON vehicles (external_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FCE69FA9F75D7B0 ON vehicles (external_id)');
        $this->addSql('CREATE INDEX IDX_1FCE69FA13109381 ON vehicles (parent_vehicle_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__vehicles AS SELECT id, external_id, name, plate_number, latitude, longitude, speed, course, last_position_time, gps_status, last_server_data_time, connection_status, status_checked_at, additional_data, created_at, updated_at FROM vehicles');
        $this->addSql('DROP TABLE vehicles');
        $this->addSql('CREATE TABLE vehicles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, external_id VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, plate_number VARCHAR(100) DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, speed DOUBLE PRECISION DEFAULT NULL, course DOUBLE PRECISION DEFAULT NULL, last_position_time DATETIME DEFAULT NULL, gps_status VARCHAR(20) DEFAULT NULL, last_server_data_time DATETIME DEFAULT NULL, connection_status VARCHAR(20) DEFAULT NULL, status_checked_at DATETIME DEFAULT NULL, additional_data CLOB DEFAULT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO vehicles (id, external_id, name, plate_number, latitude, longitude, speed, course, last_position_time, gps_status, last_server_data_time, connection_status, status_checked_at, additional_data, created_at, updated_at) SELECT id, external_id, name, plate_number, latitude, longitude, speed, course, last_position_time, gps_status, last_server_data_time, connection_status, status_checked_at, additional_data, created_at, updated_at FROM __temp__vehicles');
        $this->addSql('DROP TABLE __temp__vehicles');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1FCE69FA9F75D7B0 ON vehicles (external_id)');
        $this->addSql('CREATE INDEX idx_external_id ON vehicles (external_id)');
    }
}
