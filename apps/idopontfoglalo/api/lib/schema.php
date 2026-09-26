<?php
declare(strict_types=1);

// Az adatbázis táblái. A telepítő futtatja (CREATE TABLE IF NOT EXISTS, így
// többször is lefuttatható). Minden tábla InnoDB + utf8mb4.

function schema_statements(): array {
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS {settings} (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v MEDIUMTEXT NOT NULL
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {admins} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {services} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            description TEXT NULL,
            duration_min INT UNSIGNED NOT NULL DEFAULT 30,
            price VARCHAR(60) NOT NULL DEFAULT '',
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort INT NOT NULL DEFAULT 0
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {bookings} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NULL,
            service_name VARCHAR(150) NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL DEFAULT '',
            phone VARCHAR(60) NOT NULL DEFAULT '',
            note TEXT NULL,
            fields TEXT NULL,
            admin_note TEXT NULL,
            token CHAR(32) NOT NULL UNIQUE,
            source VARCHAR(16) NOT NULL DEFAULT 'online',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_start (start_at),
            INDEX idx_status (status)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {rate_limits} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            k VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_k (k, created_at)
        ) $opts",
    ];
}
