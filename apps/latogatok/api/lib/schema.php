<?php
declare(strict_types=1);

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

        // Egy oldalmegtekintés. active_seconds: amíg a lap látható volt.
        "CREATE TABLE IF NOT EXISTS {pageviews} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visitor_id CHAR(16) NOT NULL,
            is_new TINYINT(1) NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            host VARCHAR(120) NOT NULL DEFAULT '',
            path VARCHAR(500) NOT NULL DEFAULT '/',
            title VARCHAR(200) NOT NULL DEFAULT '',
            referrer VARCHAR(500) NOT NULL DEFAULT '',
            browser VARCHAR(40) NOT NULL DEFAULT '',
            browser_version VARCHAR(20) NOT NULL DEFAULT '',
            os VARCHAR(40) NOT NULL DEFAULT '',
            device VARCHAR(10) NOT NULL DEFAULT '',
            screen VARCHAR(20) NOT NULL DEFAULT '',
            lang VARCHAR(20) NOT NULL DEFAULT '',
            active_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_started (started_at),
            INDEX idx_visitor (visitor_id),
            INDEX idx_ip (ip)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {clicks} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pageview_id BIGINT UNSIGNED NOT NULL,
            tag VARCHAR(20) NOT NULL DEFAULT '',
            label VARCHAR(120) NOT NULL DEFAULT '',
            href VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pv (pageview_id),
            INDEX idx_created (created_at)
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
