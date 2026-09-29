<?php
declare(strict_types=1);

function schema_statements(): array {
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS {settings} (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v MEDIUMTEXT NOT NULL
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {users} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",

        // version: minden változásnál nő — a kliensek ebből látják, hogy frissíteniük kell.
        // invite_token: a meghívó link titka (új link generálásával a régi érvénytelen).
        "CREATE TABLE IF NOT EXISTS {lists} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            owner_id INT UNSIGNED NOT NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            invite_token CHAR(32) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_invite (invite_token)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {list_members} (
            list_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role VARCHAR(10) NOT NULL DEFAULT 'member',
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (list_id, user_id),
            INDEX idx_user (user_id)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {items} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            list_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL,
            qty VARCHAR(40) NOT NULL DEFAULT '',
            category VARCHAR(20) NOT NULL DEFAULT 'other',
            note VARCHAR(200) NOT NULL DEFAULT '',
            checked TINYINT(1) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            checked_at DATETIME NULL,
            INDEX idx_list (list_id, checked)
        ) $opts",

        // Javaslatok: listánként a korábban felvett tételek és hányszor kellettek.
        "CREATE TABLE IF NOT EXISTS {item_history} (
            list_id INT UNSIGNED NOT NULL,
            name_key VARCHAR(100) NOT NULL,
            name VARCHAR(120) NOT NULL,
            qty VARCHAR(40) NOT NULL DEFAULT '',
            uses INT UNSIGNED NOT NULL DEFAULT 1,
            last_used DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (list_id, name_key)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {rate_limits} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            k VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_k (k, created_at)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {password_resets} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) $opts",
    ];
}
