<?php
// A gyorsellenőrzés érdeklődői (my-ai.hu/kiberbiztonsag/admin/). Jelszó nincs: a belépőlink
// az értesítési címre (info@my-ai.hu) megy, 15 percig él, egyszer használható. A link
// megnyitása után egy gombbal lehet belépni (a levelezők linkellenőrzője nem lépteti be).
// Belépve: lista, szűrés, CSV-export, egy cím összes adatának törlése.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';
require dirname(__DIR__, 2) . '/_inc/kiber-lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$self = '/kiberbiztonsag/admin/';
$notify = (string) (kiber_config()['notify'] ?? SITE['email']);
$msg = null; $err = null;
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

function admin_cookie(string $value, int $exp): void {
    global $https;
    setcookie(KIBER_ADMIN_COOKIE, $value, ['expires' => $exp, 'path' => '/kiberbiztonsag/admin/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
}

try {
    $action = $isPost ? (string) ($_POST['do'] ?? '') : '';

    if ($action === 'send-link') {
        if (!kiber_rate_limit('adminlink', 5, 3600)) throw new KiberError('Túl sok belépőlink-kérés. Próbáld újra egy óra múlva.', 429);
        $t = bin2hex(random_bytes(24));
        kiber_q("DELETE FROM {logins} WHERE expires_at < NOW()")->close();
        kiber_q("INSERT INTO {logins} (token_hash, expires_at) VALUES (?, NOW() + INTERVAL 15 MINUTE)", [hash('sha256', $t)])->close();
        $ok = kiber_mail($notify, 'Belépés: gyorsellenőrzés érdeklődői',
            "Belépőlink a my-ai.hu gyorsellenőrzés érdeklődőinek listájához (15 percig érvényes, egyszer használható):\n\n"
            . kiber_site_url() . $self . "?belepes=$t\n\nHa nem te kérted, nyugodtan hagyd figyelmen kívül ezt a levelet.");
        $msg = $ok ? "Elküldtük a belépőlinket a(z) $notify címre. 15 percig érvényes." : 'A levelet nem sikerült elküldeni.';
    } elseif ($action === 'login') {
        $t = preg_replace('/[^0-9a-f]/', '', (string) ($_POST['t'] ?? ''));
        $st = kiber_q("UPDATE {logins} SET used = 1 WHERE token_hash = ? AND used = 0 AND expires_at > NOW()", [hash('sha256', $t)]);
        $valid = $st->affected_rows === 1;
        $st->close();
        if (!$valid) throw new KiberError('A belépőlink lejárt vagy már felhasználták. Kérj újat.');
        $exp = time() + 7 * 86400;
        admin_cookie(kiber_admin_sign($exp), $exp);
        header("Location: $self", true, 303);
        exit;
    } elseif ($action === 'logout') {
        admin_cookie('', time() - 3600);
        header("Location: $self", true, 303);
        exit;
    } elseif ($action === 'delete' && kiber_is_admin()) {
        if (!hash_equals(kiber_csrf(), (string) ($_POST['csrf'] ?? ''))) throw new KiberError('Lejárt űrlap, töltsd újra az oldalt.');
        $st = kiber_q("DELETE FROM {leads} WHERE email = ?", [strtolower(trim((string) ($_POST['email'] ?? '')))]);
        $msg = $st->affected_rows . ' bejegyzés törölve.';
        $st->close();
    }
} catch (KiberError $e) {
    $err = $e->getMessage();
}

$admin = kiber_is_admin();
$filter = (string) ($_GET['f'] ?? 'all');
$where = $filter === 'sub' ? 'WHERE newsletter = 1 AND confirmed_at IS NOT NULL AND unsubscribed_at IS NULL' : '';

// CSV-export (Excelben megnyitható: UTF-8 BOM, pontosvessző)
if ($admin && isset($_GET['csv'])) {
    try { $rows = kiber_all("SELECT created_at, email, company, domain, score, q_score, d_score, todos, todos_now, newsletter, confirmed_at, unsubscribed_at FROM {leads} $where ORDER BY created_at DESC"); }
    catch (KiberError $e) { http_response_code(503); exit($e->getMessage()); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gyorsellenorzes-erdeklodok-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Időpont', 'E-mail', 'Cég', 'Domain', 'Pontszám', 'Kérdőív', 'Domain pont', 'Teendők', 'Azonnali', 'Hírlevél', 'Megerősítve', 'Leiratkozott'], ';');
    foreach ($rows as $r) {
        $sub = $r['newsletter'] ? ($r['confirmed_at'] ? 'feliratkozott' : 'megerősítésre vár') : ($r['unsubscribed_at'] ? 'leiratkozott' : 'nem kérte');
        // a cellák elején álló =, +, -, @ jelet az Excel képletnek venné: elé teszünk egy aposztrófot
        $safe = fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v;
        fputcsv($out, array_map($safe, [$r['created_at'], $r['email'], $r['company'], $r['domain'], $r['score'], $r['q_score'], $r['d_score'], $r['todos'], $r['todos_now'], $sub, $r['confirmed_at'], $r['unsubscribed_at']]), ';');
    }
    exit;
}

$rows = []; $stats = ['total' => 0, 'emails' => 0, 'subs' => 0, 'pending' => 0];
if ($admin) {
    try {
        $rows = kiber_all("SELECT id, created_at, email, company, domain, score, todos, todos_now, newsletter, confirmed_at, unsubscribed_at, mail_ok FROM {leads} $where ORDER BY created_at DESC LIMIT 500");
        $s = kiber_all("SELECT COUNT(*) AS total, COUNT(DISTINCT email) AS emails,
            COUNT(DISTINCT CASE WHEN newsletter = 1 AND confirmed_at IS NOT NULL AND unsubscribed_at IS NULL THEN email END) AS subs,
            COUNT(DISTINCT CASE WHEN newsletter = 1 AND confirmed_at IS NULL THEN email END) AS pending FROM {leads}")[0] ?? [];
        $stats = array_map('intval', $s + $stats);
    } catch (KiberError $e) { $err = $e->getMessage(); }
}

page_start('Gyorsellenőrzés – érdeklődők', 'Admin', 'kiberbiztonsag');
?>
  <style>
    .kba { max-width: 1120px; }
    .kba-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin: 20px 0; }
    .kba-stats div { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 14px; }
    .kba-stats b { display: block; font-size: 1.6rem; }
    .kba-stats span { color: var(--muted); font-size: .88rem; }
    .kba-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
    .kba-table { width: 100%; border-collapse: collapse; font-size: .92rem; }
    .kba-table th, .kba-table td { text-align: left; padding: 9px 8px; border-bottom: 1px solid var(--border); vertical-align: top; }
    .kba-table th { color: var(--muted); font-weight: 600; font-size: .82rem; }
    .kba-wrap { overflow-x: auto; background: var(--card); border: 1px solid var(--border); border-radius: 14px; }
    .kba-table form { display: inline; }
    .kba-table button { background: none; border: 0; color: var(--muted); cursor: pointer; font: inherit; text-decoration: underline; }
    .kba-pill { font-size: .78rem; padding: 2px 8px; border-radius: 999px; background: var(--bg-soft); white-space: nowrap; }
    .kba-pill.sub { background: color-mix(in srgb, #15803d 16%, transparent); color: #15803d; }
  </style>
  <section style="padding-top:32px">
    <div class="wrap kba">
      <h1 style="font-size:1.8rem">Gyorsellenőrzés – érdeklődők</h1>
<?php if ($msg !== null): ?>      <p class="order-msg ok" style="margin-top:14px"><?= e($msg) ?></p>
<?php endif; if ($err !== null): ?>      <p class="order-msg alert" style="margin-top:14px"><?= e($err) ?></p>
<?php endif; ?>

<?php if (!$admin && isset($_GET['belepes']) && $err === null): ?>
      <form method="post" class="card form" style="max-width:480px;margin-top:20px">
        <p>Belépés a gyorsellenőrzés érdeklődőinek listájához.</p>
        <input type="hidden" name="do" value="login"><input type="hidden" name="t" value="<?= e(preg_replace('/[^0-9a-f]/', '', (string) $_GET['belepes'])) ?>">
        <button class="btn btn-primary" type="submit">Belépés</button>
      </form>
<?php elseif (!$admin): ?>
      <form method="post" class="card form" style="max-width:480px;margin-top:20px">
        <p>Jelszó nincs: a belépőlinket a(z) <b><?= e($notify) ?></b> címre küldjük.</p>
        <input type="hidden" name="do" value="send-link">
        <button class="btn btn-primary" type="submit">Belépőlink küldése</button>
      </form>
<?php else: ?>
      <div class="kba-stats">
        <div><b><?= $stats['total'] ?></b><span>kitöltés összesen</span></div>
        <div><b><?= $stats['emails'] ?></b><span>különböző e-mail-cím</span></div>
        <div><b><?= $stats['subs'] ?></b><span>megerősített hírlevél-feliratkozó</span></div>
        <div><b><?= $stats['pending'] ?></b><span>hírlevél: megerősítésre vár</span></div>
      </div>
      <div class="kba-bar">
        <a class="btn <?= $filter === 'sub' ? 'btn-ghost' : 'btn-primary' ?>" href="<?= $self ?>">Mind</a>
        <a class="btn <?= $filter === 'sub' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= $self ?>?f=sub">Csak feliratkozók</a>
        <a class="btn btn-ghost" href="<?= $self ?>?csv=1<?= $filter === 'sub' ? '&amp;f=sub' : '' ?>">CSV letöltése</a>
        <form method="post" style="margin-left:auto"><input type="hidden" name="do" value="logout"><button class="btn btn-ghost" type="submit">Kilépés</button></form>
      </div>
      <p class="kb-muted" style="color:var(--muted);font-size:.88rem;margin-bottom:10px">Marketinglevelet csak a megerősített feliratkozóknak szabad küldeni. A többiek csak a saját teendőlistájukat kérték.</p>
      <div class="kba-wrap">
        <table class="kba-table">
          <tr><th>Időpont</th><th>E-mail</th><th>Cég</th><th>Domain</th><th>Pont</th><th>Teendők</th><th>Hírlevél</th><th></th></tr>
<?php if (!$rows): ?>          <tr><td colspan="8" style="color:var(--muted)">Még nincs kitöltés.</td></tr>
<?php endif; foreach ($rows as $r):
    $sub = $r['newsletter'] ? ($r['confirmed_at'] ? ['feliratkozott', 'sub'] : ['megerősítésre vár', '']) : ($r['unsubscribed_at'] ? ['leiratkozott', ''] : ['–', '']); ?>
          <tr>
            <td><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
            <td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?= $r['mail_ok'] ? '' : ' <span class="kba-pill" title="A teendőlista-levél kiküldése hibát adott">levél hiba</span>' ?></td>
            <td><?= e((string) $r['company']) ?></td>
            <td><?= e((string) $r['domain']) ?></td>
            <td><?= $r['score'] === null ? '–' : (int) $r['score'] ?></td>
            <td><?= (int) $r['todos'] ?><?= $r['todos_now'] ? ' (' . (int) $r['todos_now'] . ' azonnali)' : '' ?></td>
            <td><span class="kba-pill <?= $sub[1] ?>"><?= $sub[0] ?></span></td>
            <td><form method="post" onsubmit="return confirm('Törlöd ennek a címnek az összes bejegyzését?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="csrf" value="<?= e(kiber_csrf()) ?>"><input type="hidden" name="email" value="<?= e($r['email']) ?>"><button type="submit">törlés</button></form></td>
          </tr>
<?php endforeach; ?>
        </table>
      </div>
<?php endif; ?>
    </div>
  </section>
<?php
page_end();
