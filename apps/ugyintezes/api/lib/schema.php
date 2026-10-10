<?php
declare(strict_types=1);

// Az ügyfelek jelszóbeállító linkjei (meghívó és elfelejtett jelszó).
const PORTAL_RESETS_SQL = "CREATE TABLE IF NOT EXISTS {portal_resets} (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Meglévő telepítés frissítése (az első API-hívásnál, egyszer).
function migrate_schema(): void {
    if ((int) (setting_value('schema_version') ?? 1) >= 2) return;
    if (!q_one("SHOW COLUMNS FROM {customers} LIKE 'portal_password_hash'")) {
        q_exec('ALTER TABLE {customers} ADD COLUMN portal_password_hash VARCHAR(255) NULL AFTER paid_until');
    }
    if (!db()->query(sql_tables(PORTAL_RESETS_SQL))) throw new AppError('Adatbázis-frissítési hiba: ' . db()->error, 500);
    set_setting_value('schema_version', '2');
}

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

        // Ügyfél = megrendelés + előfizetés. status: pending (megrendelte), active, paused.
        // slug: a beépítő kódban a data-ugyfel értéke. kb_json: a tudásbázis.
        "CREATE TABLE IF NOT EXISTS {customers} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(40) NOT NULL UNIQUE,
            name VARCHAR(150) NOT NULL,
            contact_name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(60) NOT NULL DEFAULT '',
            website VARCHAR(255) NOT NULL DEFAULT '',
            billing_name VARCHAR(150) NOT NULL DEFAULT '',
            billing_address VARCHAR(255) NOT NULL DEFAULT '',
            tax_number VARCHAR(40) NOT NULL DEFAULT '',
            message TEXT NULL,
            admin_note TEXT NULL,
            color VARCHAR(9) NOT NULL DEFAULT '',
            kb_json MEDIUMTEXT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            paid_until DATE NULL,
            portal_password_hash VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status, paid_until)
        ) $opts",

        "CREATE TABLE IF NOT EXISTS {payments} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            months SMALLINT UNSIGNED NOT NULL,
            amount INT UNSIGNED NOT NULL,
            note VARCHAR(200) NOT NULL DEFAULT '',
            period_until DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id)
        ) $opts",

        // Kiküldött lejárati levelek (egy időszakra egy fajta csak egyszer).
        "CREATE TABLE IF NOT EXISTS {reminders} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id INT UNSIGNED NOT NULL,
            kind VARCHAR(12) NOT NULL,
            period_until DATE NOT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rem (customer_id, kind, period_until)
        ) $opts",

        PORTAL_RESETS_SQL,

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
