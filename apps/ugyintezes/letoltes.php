<?php
// Fizetős csomag letöltése személyes kóddal: letoltes.php?kod=IDO-XXXX-XXXX-XXXX
// A kódot az admin „Letöltőkódok” fülén lehet kiadni; letiltható, és a letöltések száma korlátozott.
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';
require __DIR__ . '/api/lib/schema.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function page(string $title, string $html, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($title) . ' – my-ai.hu</title><style>'
        . ':root{--bg:#f5f6fb;--card:#fff;--text:#0f172a;--muted:#5b6477;--border:#e6e8f0;--accent:#4f46e5;color-scheme:light}'
        . '@media (prefers-color-scheme:dark){:root{--bg:#0b0e17;--card:#151a26;--text:#eef1f7;--muted:#9aa3b5;--border:#242a38;--accent:#8b87ff;color-scheme:dark}}'
        . 'body{margin:0;background:var(--bg);color:var(--text);font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;display:grid;place-items:center;min-height:100vh;padding:16px;box-sizing:border-box}'
        . '.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;max-width:460px;width:100%}h1{font-size:1.4rem;margin:0 0 10px}p{margin:0 0 12px;color:var(--muted)}'
        . 'form{display:flex;gap:8px;flex-wrap:wrap}input{flex:1;min-width:0;font:inherit;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg);color:var(--text);text-transform:uppercase}'
        . 'button{font:600 1rem system-ui,sans-serif;background:var(--accent);color:#fff;border:0;border-radius:10px;padding:10px 16px;cursor:pointer}a{color:var(--accent)}</style></head><body><main class="card">'
        . '<h1>' . h($title) . '</h1>' . $html . '</main></body></html>';
    exit;
}

$form = '<form method="get" action=""><input name="kod" required placeholder="IDO-XXXX-XXXX-XXXX" aria-label="Letöltőkód" autocomplete="off"><button type="submit">Letöltés</button></form>'
    . '<p style="margin-top:14px;font-size:.9rem">Elakadtál? Írj: <a href="mailto:info@my-ai.hu">info@my-ai.hu</a> · +36 30 584 5937</p>';

if (!app_installed()) page('A letöltés most nem érhető el', '<p>Kérjük, próbáld később.</p>', 503);
migrate_schema();

$code = normalize_code((string) ($_GET['kod'] ?? ''));
if ($code === '') page('Letöltés kóddal', '<p>Add meg a vásárláskor emailben kapott letöltőkódot.</p>' . $form);

// túl sok hibás próbálkozás ellen (IP-nként óránként 10)
$ipKey = 'dl:' . substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$fails = q_one('SELECT COUNT(*) AS n FROM {rate_limits} WHERE k = ? AND created_at > ?', [$ipKey, date('Y-m-d H:i:s', time() - 3600)]);
if ((int) ($fails['n'] ?? 0) >= 10) page('Túl sok próbálkozás', '<p>Kérjük, próbáld újra egy óra múlva.</p>', 429);

$l = q_one('SELECT * FROM {licenses} WHERE code = ?', [$code]);
if (!$l || !isset(PAID_PRODUCTS[$l['product']])) {
    q_exec('INSERT INTO {rate_limits} (k) VALUES (?)', [$ipKey]);
    page('Érvénytelen kód', '<p>Ezzel a kóddal nem található csomag. Ellenőrizd, hogy pontosan írtad-e be.</p>' . $form, 404);
}
if ($l['disabled']) page('A kód le van tiltva', '<p>Ez a letöltőkód már nem érvényes. Ha szerinted tévedés, írj nekünk.</p>' . $form, 403);
if ((int) $l['downloads'] >= (int) $l['max_downloads']) page('Elfogyott a letöltések száma', '<p>Ezzel a kóddal már ' . (int) $l['downloads'] . ' alkalommal töltöttek le. Ha újra kell, írj nekünk, és küldünk újat.</p>' . $form, 403);

$file = paid_file($l['product']);
if (!is_file($file)) page('A csomag most nem érhető el', '<p>Kérjük, próbáld néhány perc múlva, vagy írj nekünk.</p>', 503);

q_exec('UPDATE {licenses} SET downloads = downloads + 1, last_download_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $l['id']]);
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $l['product'] . '.zip"');
header('Content-Length: ' . filesize($file));
readfile($file);
