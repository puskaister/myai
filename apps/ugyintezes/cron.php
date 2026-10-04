<?php
// Napi kör: lejárat előtti emlékeztetők és a lejárt előfizetések jelzése.
// Naponta egyszer kell meghívni (GitHub Actions ütemező vagy a tárhely CRON-ja):
//   https://…/cron.php?key=<kulcs>   — a kulcs az admin Beállítások fülén látszik.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!app_installed()) {
    echo json_encode(['skipped' => 'not installed']);
    exit;
}
if (!hash_equals(cron_key(), (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
set_time_limit(300);
$date = (string) ($_GET['date'] ?? ''); // csak teszteléshez
$today = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
echo json_encode(['date' => $today] + run_billing($today), JSON_UNESCAPED_UNICODE);
