<?php
// A chatablak (chat.html) innen kéri a tudásbázist: kb.php?u=<ügyfél-azonosító>.
// – a beépített demó és a my-ai.hu saját buboréka (minta, my-ai) mindig működik, fájlból;
// – egy ügyfélé csak érvényes (kifizetett, aktív) előfizetésnél, különben "szünetel".
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');

$id = strtolower((string) ($_GET['u'] ?? 'minta'));
if (!preg_match('/^[a-z0-9-]{1,40}$/', $id)) $id = 'minta';

if (in_array($id, BUILTIN_KBS, true)) {
    readfile(__DIR__ . '/ugyfelek/' . $id . '.json');
    exit;
}

try {
    $c = app_installed() ? q_one('SELECT name, color, kb_json, status, paid_until FROM {customers} WHERE slug = ?', [$id]) : null;
} catch (Throwable $e) {
    $c = null;
}
if (!$c || empty($c['kb_json'])) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}
if (!is_subscription_active($c)) {
    echo json_encode(['inactive' => true, 'nev' => $c['name'],
        'uzenet' => 'A chat jelenleg nem elérhető. Kérjük, keresse ügyfélszolgálatunkat a weboldalon található elérhetőségeken.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$kb = json_decode((string) $c['kb_json'], true) ?: ['temak' => []];
if (empty($kb['nev'])) $kb['nev'] = $c['name'];
if (empty($kb['szin']) && $c['color'] !== '') $kb['szin'] = $c['color'];
echo json_encode($kb, JSON_UNESCAPED_UNICODE);
