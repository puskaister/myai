<?php
declare(strict_types=1);

function schema_statements(): array {
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS {settings} (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v MEDIUMTEXT NOT NULL
        ) $opts",

        // is_admin: felhasználókat kezelhet; cal_token: a naptár-feliratkozás titkos linkje
        "CREATE TABLE IF NOT EXISTS {users} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            notify TINYINT(1) NOT NULL DEFAULT 1,
            cal_token CHAR(32) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",

        // year: ismert évszám (egyszerinél kötelező; évesnél a hányadik alkalom számításához)
        // remind_days: ennyi nappal előtte menjen email (-1 = ne menjen)
        "CREATE TABLE IF NOT EXISTS {events} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(150) NOT NULL,
            category VARCHAR(20) NOT NULL DEFAULT 'other',
            month TINYINT UNSIGNED NOT NULL,
            day TINYINT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NULL,
            recurrence VARCHAR(10) NOT NULL DEFAULT 'yearly',
            remind_days SMALLINT NOT NULL DEFAULT 1,
            note TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {sent_reminders} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            occurrence DATE NOT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_event_occ (event_id, occurrence)
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
