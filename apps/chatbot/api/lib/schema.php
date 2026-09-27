<?php
declare(strict_types=1);

// Az adatbázis táblái (CREATE TABLE IF NOT EXISTS, így többször is futtatható).

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

        // Egy látogatói beszélgetés. answers: {kérdés_kulcs: válasz} JSON.
        "CREATE TABLE IF NOT EXISTS {conversations} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token CHAR(32) NOT NULL UNIQUE,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            answers TEXT NULL,
            user_messages INT UNSIGNED NOT NULL DEFAULT 0,
            source_url VARCHAR(500) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            notified_at DATETIME NULL,
            seen TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_status (status)
        ) $opts",

        // Üzenetek; az asszisztens soraihoz a tokenhasználat is (költségbecsléshez).
        "CREATE TABLE IF NOT EXISTS {messages} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT UNSIGNED NOT NULL,
            role VARCHAR(16) NOT NULL,
            content TEXT NOT NULL,
            model VARCHAR(64) NOT NULL DEFAULT '',
            input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            cache_read_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            cache_write_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_conv (conversation_id, id)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {rate_limits} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            k VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_k (k, created_at)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {password_resets} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id)
        ) $opts",
    ];
}
