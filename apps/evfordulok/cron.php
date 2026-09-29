<?php
// Napi emlékeztető-kör. Naponta egyszer kell meghívni (GitHub Actions ütemező,
// vagy a tárhely CRON funkciója):  https://…/cron.php?key=<kulcs>
// A kulcs az admin felület Felhasználók fülén látszik. Többszöri hívás sem küld
// kétszer ugyanarról az alkalomról.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!app_installed()) {
    http_response_code(503);
    echo json_encode(['error' => 'not installed']);
    exit;
}
if (!hash_equals(cron_key(), (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}
set_time_limit(300);
// A dátum felülírható (?date=YYYY-MM-DD) — csak a teszteléshez, a kulccsal együtt.
$date = (string) ($_GET['date'] ?? '');
$today = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
echo json_encode(['date' => $today] + run_reminders($today));
