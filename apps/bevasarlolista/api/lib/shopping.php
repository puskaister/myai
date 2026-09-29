<?php
declare(strict_types=1);

// Bevásárlólista: kategorizálás a szótár alapján, hozzáférés-ellenőrzés,
// verziószám (a kliensek ebből látják, hogy változott-e a lista).

function dictionary(): array {
    static $dict = null;
    if ($dict === null) {
        $dict = json_decode((string) file_get_contents(APP_ROOT . '/assets/dict.json'), true)['categories'] ?? [];
    }
    return $dict;
}

// Ékezet nélküli, kisbetűs alak az összehasonlításhoz ("Kenyér" → "kenyer").
function deaccent(string $s): string {
    $s = mb_strtolower(trim($s));
    return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u']);
}

// A tétel kategóriája: a leghosszabb szótári szó, amellyel a név (vagy annak egy
// szava) kezdődik — így a "csirkemell filé" hús, a "fokhagyma" zöldség.
function categorize(string $name): string {
    $n = deaccent($name);
    $words = preg_split('/[\s\-]+/u', $n) ?: [];
    $best = ['other', 0];
    foreach (dictionary() as $cat) {
        foreach ($cat['words'] as $w) {
            $d = deaccent($w);
            $hit = $n === $d || strpos($n, $d . ' ') === 0 || strpos(' ' . $n . ' ', ' ' . $d . ' ') !== false;
            if (!$hit && strpos($d, ' ') === false) {
                foreach ($words as $x) {
                    // ragozott vagy összetett alak: "almát", "tejföllel" → a szótári szó eleje
                    if ($x !== '' && strpos($x, $d) === 0 && mb_strlen($x) - mb_strlen($d) <= 3) { $hit = true; break; }
                }
            }
            if ($hit && mb_strlen($d) > $best[1]) $best = [$cat['key'], mb_strlen($d)];
        }
    }
    return $best[0];
}

function category_keys(): array {
    return array_column(dictionary(), 'key');
}

// A felhasználó tagja-e a listának; ha nem, 404 (nem áruljuk el, hogy létezik).
function require_list(int $listId, int $userId): array {
    $l = q_one('SELECT l.*, m.role FROM {lists} l JOIN {list_members} m ON m.list_id = l.id AND m.user_id = ? WHERE l.id = ?', [$userId, $listId]);
    if (!$l) throw new AppError('A lista nem található.', 404);
    return $l;
}

function bump_version(int $listId): void {
    q_exec('UPDATE {lists} SET version = version + 1, updated_at = NOW() WHERE id = ?', [$listId]);
}

function list_payload(int $listId): array {
    $l = q_one('SELECT id, name, owner_id, version, invite_token FROM {lists} WHERE id = ?', [$listId]);
    $items = q_all('SELECT id, name, qty, category, checked, note, created_by, created_at, checked_at FROM {items} WHERE list_id = ? ORDER BY checked, position, id', [$listId]);
    foreach ($items as &$i) $i['checked'] = (bool) $i['checked'];
    unset($i);
    $members = q_all('SELECT u.id, u.name, m.role FROM {list_members} m JOIN {users} u ON u.id = m.user_id WHERE m.list_id = ? ORDER BY m.role = ?, u.name', [$listId, 'member']);
    return ['list' => ['id' => (int) $l['id'], 'name' => $l['name'], 'owner_id' => (int) $l['owner_id'], 'version' => (int) $l['version']],
            'items' => $items, 'members' => $members];
}

// Gyakran vett tételek (javaslatok) a listához.
function remember_item(int $listId, string $name, string $qty): void {
    q_exec('INSERT INTO {item_history} (list_id, name_key, name, qty, uses) VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE uses = uses + 1, name = VALUES(name), qty = VALUES(qty), last_used = NOW()',
        [$listId, mb_substr(deaccent($name), 0, 100), $name, $qty]);
}
