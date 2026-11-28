<?php
// A teendőlista-levél linkjei: ?a=megerosites (hírlevél-feliratkozás megerősítése) és
// ?a=leiratkozas (leiratkozás vagy az adatok törlése). Megnyitásra csak egy gomb jelenik meg,
// a művelet POST-ra történik: a levelezők linkellenőrzői így nem iratkoztatnak fel/le senkit.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';
require dirname(__DIR__, 2) . '/_inc/kiber-lib.php';

header('X-Robots-Tag: noindex');
$token = preg_replace('/[^0-9a-f]/', '', (string) ($_REQUEST['t'] ?? ''));
$action = (string) ($_REQUEST['a'] ?? '');
$msg = null; $err = null; $lead = null;

try {
    if (strlen($token) === 32) {
        $lead = kiber_all("SELECT id, email, newsletter, confirmed_at, unsubscribed_at FROM {leads} WHERE token = ?", [$token])[0] ?? null;
    }
    if (!$lead) {
        $err = 'Ez a link érvénytelen vagy már nem él. Ha segítség kell, írj nekünk: ' . SITE['email'];
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $do = (string) ($_POST['do'] ?? '');
        if ($do === 'confirm') {
            kiber_q("UPDATE {leads} SET newsletter = 1, confirmed_at = COALESCE(confirmed_at, NOW()), unsubscribed_at = NULL WHERE email = ?", [$lead['email']])->close();
            $msg = 'Köszönjük, a feliratkozás megerősítve. Ritkán írunk, és csak hasznos dolgokról. Minden levél alján le tudsz iratkozni.';
        } elseif ($do === 'unsub') {
            kiber_q("UPDATE {leads} SET newsletter = 0, unsubscribed_at = NOW() WHERE email = ?", [$lead['email']])->close();
            $msg = 'Leiratkoztál, több levelet nem küldünk erre a címre.';
        } elseif ($do === 'delete') {
            kiber_q("DELETE FROM {leads} WHERE email = ?", [$lead['email']])->close();
            $msg = 'Töröltük a címedet és az összes gyorsellenőrzés-eredményedet az adatbázisunkból.';
            $lead = null;
        }
    }
} catch (KiberError $e) {
    $err = $e->getMessage();
}

page_start('Kiberbiztonsági hírlevél – my-ai.hu', 'Feliratkozás megerősítése és leiratkozás.', 'kiberbiztonsag');
?>
  <section class="page-head">
    <div class="wrap">
      <span class="eyebrow">Kiberbiztonság</span>
      <h1><?= $action === 'megerosites' ? 'Feliratkozás megerősítése' : 'Leiratkozás' ?></h1>
    </div>
  </section>
  <section style="padding-top:8px">
    <div class="wrap" style="max-width:640px">
      <div class="card form">
<?php if ($err !== null): ?>
        <p class="order-msg alert"><?= e($err) ?></p>
<?php elseif ($msg !== null): ?>
        <p class="order-msg ok"><?= e($msg) ?></p>
        <p><a href="/kiberbiztonsag/">Vissza a kiberbiztonsági oldalra →</a></p>
<?php elseif ($action === 'megerosites'): ?>
        <p>Megerősíted, hogy a(z) <b><?= e($lead['email']) ?></b> címre időnként levelet küldjünk a kiscégeket érintő kiberveszélyekről és az új eszközeinkről?</p>
        <form method="post"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="a" value="megerosites"><input type="hidden" name="do" value="confirm">
          <button class="btn btn-primary" type="submit">Igen, feliratkozom</button></form>
<?php else: ?>
        <p>Mit szeretnél a(z) <b><?= e($lead['email']) ?></b> címmel?</p>
        <form method="post"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="a" value="leiratkozas"><input type="hidden" name="do" value="unsub">
          <button class="btn btn-primary" type="submit">Leiratkozom, ne küldjetek több levelet</button></form>
        <form method="post" onsubmit="return confirm('Biztosan törlöd a címedet és az eredményeidet?')"><input type="hidden" name="t" value="<?= e($token) ?>"><input type="hidden" name="a" value="leiratkozas"><input type="hidden" name="do" value="delete">
          <button class="btn btn-ghost" type="submit">Töröljétek az összes adatomat</button></form>
<?php endif; ?>
      </div>
    </div>
  </section>
<?php
page_end();
