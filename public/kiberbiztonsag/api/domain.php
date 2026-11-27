<?php
// Domain-vizsgálat a gyorsellenőrzéshez: GET ?d=pelda.hu → JSON (lásd _inc/domaincheck.php).
// Semmit nem tárol, csak IP-nkénti hívásszámot (visszaélés ellen, 10 percig, a temp mappában).
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Budapest');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

require dirname(__DIR__, 2) . '/_inc/domaincheck.php';

function out(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') out(405, ['error' => 'Csak GET kérés.']);

$domain = dc_normalize_domain((string) ($_GET['d'] ?? ''));
if ($domain === null) out(400, ['error' => 'Adj meg egy érvényes domaint, például: cegem.hu']);

// IP-nkénti korlát: 15 vizsgálat / 10 perc.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$rlFile = sys_get_temp_dir() . '/myai-kiber-' . substr(hash('sha256', 'kiber|' . $ip), 0, 24);
$now = time();
$hits = array_filter((array) json_decode((string) @file_get_contents($rlFile), true), fn ($t) => is_int($t) && $t > $now - 600);
if (count($hits) >= 15) out(429, ['error' => 'Túl sok vizsgálat rövid idő alatt. Próbáld újra néhány perc múlva.']);
$hits[] = $now;
@file_put_contents($rlFile, json_encode(array_values($hits)), LOCK_EX);

set_time_limit(60);
try {
    out(200, dc_run($domain));
} catch (Throwable $e) {
    error_log('[kiberbiztonsag] ' . $e->getMessage());
    out(500, ['error' => 'A vizsgálat nem sikerült. Próbáld újra később.']);
}
