<?php
declare(strict_types=1);

// Fizetős, letölthető csomagok és a személyes letöltőkódok.
// A csomagokat a deploy készíti a csomagok/ mappába (kívülről nem érhető el);
// letölteni csak a letoltes.php?kod=… címen, érvényes kóddal lehet.

const PAID_PRODUCTS = [
    'idopontfoglalo' => ['name' => 'Önálló online időpontfoglaló', 'file' => 'idopontfoglalo.zip', 'price' => '49 000 Ft + ÁFA'],
];

function paid_file(string $product): string {
    return dirname(__DIR__, 2) . '/csomagok/' . PAID_PRODUCTS[$product]['file'];
}

// Pl. IDO-7K4M-Q2XP-9WRT (összetéveszthető karakterek nélkül).
function make_license_code(string $product): string {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $parts = [];
        for ($p = 0; $p < 3; $p++) {
            $s = '';
            for ($i = 0; $i < 4; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
            $parts[] = $s;
        }
        $code = strtoupper(substr($product, 0, 3)) . '-' . implode('-', $parts);
    } while (q_one('SELECT id FROM {licenses} WHERE code = ?', [$code]));
    return $code;
}

function normalize_code(string $code): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', trim($code)));
}

function license_link(array $l): string {
    return app_base_url() . '/letoltes.php?kod=' . rawurlencode($l['code']);
}

function send_license_email(array $l): void {
    $p = PAID_PRODUCTS[$l['product']] ?? null;
    if (!$p) throw new AppError('Ismeretlen termék.');
    $o = get_options();
    $ok = send_app_email(mail_cfg(), (string) $l['buyer_email'], 'Letöltés: ' . $p['name'],
        "Kedves {$l['buyer_name']}!\n\nKöszönjük a vásárlást! A(z) {$p['name']} telepítőcsomagját ezen a linken töltheted le:\n\n"
        . license_link($l) . "\n\nLetöltőkód: {$l['code']} (a my-ai.hu termékoldalán is megadhatod)\n"
        . "A kód legfeljebb {$l['max_downloads']} letöltésre érvényes.\n\n"
        . "A telepítés lépései a csomagban lévő TELEPITES.md fájlban vannak: feltöltöd a tárhelyedre, megadod az adatbázis adatait, "
        . "megnyitod az install.php oldalt, és az admin felület Beállítások fülén megtalálod a weboldaladba illeszthető kódot.\n\n"
        . 'Ha elakadsz, segítünk: ' . ($o['admin_email'] ?: 'info@my-ai.hu') . ", +36 30 584 5937 (8:00–17:00)\n\nmy-ai.hu · Immobilis Partners KFT");
    if (!$ok) throw new AppError('A levelet nem sikerült elküldeni: ' . (($GLOBALS['mail_last']['error'] ?? '') ?: 'ismeretlen hiba'), 500);
}
