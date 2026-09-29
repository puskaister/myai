<?php
// Letöltések (my-ai.hu/letoltes/). A csomagokat a deploy készíti a files/
// mappába. A letöltés ezen az oldalon keresztül megy (?fajl=<név>), hogy később
// egyedi letöltőkódhoz köthessük; most minden termék ingyenes.
declare(strict_types=1);

$products = [
    'latogatoszamlalo' => [
        'name'  => 'Látogatószámláló',
        'price' => 'Ingyenes',
    ],
];

$file = (string) ($_GET['fajl'] ?? '');
if ($file !== '') {
    $path = __DIR__ . '/files/' . basename($file) . '.zip';
    if (!isset($products[$file]) || !is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'A kért csomag nem található.';
        exit;
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $file . '.zip"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

$e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$zip = __DIR__ . '/files/latogatoszamlalo.zip';
$size = is_file($zip) ? max(1, (int) round(filesize($zip) / 1024)) . ' KB' : '';
$version = is_file(__DIR__ . '/files/latogatoszamlalo.version') ? trim((string) file_get_contents(__DIR__ . '/files/latogatoszamlalo.version')) : '';
$updated = is_file($zip) ? date('Y. m. d.', (int) filemtime($zip)) : '';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Látogatószámláló – ingyenes letöltés – my-ai.hu</title>
<meta name="description" content="Saját webstatisztika a weboldaladra: látogatók, eltöltött idő, kattintások, böngészők — külső szolgáltatás nélkül, a saját tárhelyeden. Ingyenesen letölthető.">
<meta name="theme-color" content="#4f46e5">
<style>
  :root { --bg: #fff; --soft: #f5f6fb; --card: #fff; --text: #0f172a; --muted: #5b6477; --border: #e6e8f0; --accent: #4f46e5; --on-accent: #fff; color-scheme: light; }
  @media (prefers-color-scheme: dark) { :root { --bg: #0b0e17; --soft: #11151f; --card: #151a26; --text: #eef1f7; --muted: #9aa3b5; --border: #242a38; --accent: #8b87ff; --on-accent: #0b0e17; color-scheme: dark; } }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 17px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  a { color: var(--accent); }
  .wrap { max-width: 980px; margin: 0 auto; padding: 0 16px; }
  header { border-bottom: 1px solid var(--border); }
  header .wrap { display: flex; align-items: center; justify-content: space-between; height: 60px; }
  header a { text-decoration: none; font-weight: 800; color: var(--text); }
  h1, h2, h3 { line-height: 1.2; letter-spacing: -0.02em; margin: 0; }
  .hero { padding: 56px 0 32px; }
  .eyebrow { display: inline-block; font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); margin-bottom: 10px; }
  .hero h1 { font-size: clamp(2rem, 5.5vw, 3rem); margin-bottom: 14px; }
  .lead { color: var(--muted); font-size: 1.1rem; max-width: 640px; margin: 0 0 24px; }
  .btn { display: inline-flex; align-items: center; gap: 10px; padding: 14px 22px; border-radius: 12px; background: var(--accent); color: var(--on-accent); font-weight: 700; text-decoration: none; }
  .btn:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
  .btn svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
  .meta { color: var(--muted); font-size: .9rem; margin-top: 10px; }
  section { padding: 36px 0; }
  .soft { background: var(--soft); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
  .grid { display: grid; gap: 16px; grid-template-columns: 1fr; margin-top: 20px; }
  @media (min-width: 720px) { .grid { grid-template-columns: 1fr 1fr 1fr; } }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; }
  .card h3 { font-size: 1.05rem; margin-bottom: 6px; }
  .card p { color: var(--muted); margin: 0; font-size: .96rem; }
  ol.steps { padding-left: 22px; margin: 16px 0 0; }
  ol.steps li { margin-bottom: 10px; }
  code { background: var(--soft); border: 1px solid var(--border); padding: 1px 6px; border-radius: 6px; font-size: .88em; }
  .req { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
  .chip { padding: 6px 12px; border-radius: 999px; background: var(--card); border: 1px solid var(--border); font-size: .88rem; color: var(--muted); font-weight: 600; }
  /* Látogatószámláló böngészőablak-makett */
  .browser { width: 100%; max-width: 520px; margin: 0 auto; border-radius: 14px; overflow: hidden; background: #f5f6f8; color: #111827;
    border: 1px solid #d9dce5; box-shadow: 0 30px 70px rgba(15, 23, 42, .2); font-size: .72rem; }
  .browser-bar { display: flex; align-items: center; gap: 6px; padding: 9px 12px; background: #e9ebf1; border-bottom: 1px solid #d9dce5; }
  .browser-bar i { width: 9px; height: 9px; border-radius: 50%; background: #c9ccd6; display: block; }
  .browser-bar i:nth-child(1) { background: #f87171; } .browser-bar i:nth-child(2) { background: #fbbf24; } .browser-bar i:nth-child(3) { background: #34d399; }
  .browser-bar span { flex: 1; margin-left: 8px; background: #fff; border-radius: 6px; padding: 3px 10px; color: #6b7280; font-size: .66rem; }
  .dash-top { background: #4f46e5; color: #fff; padding: 10px 14px; font-weight: 750; font-size: .8rem; }
  .dash-top small { display: block; font-weight: 400; opacity: .8; font-size: .62rem; }
  .dash-body { padding: 12px; display: grid; gap: 10px; }
  .dash-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
  .dash-tiles div { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 7px 8px; }
  .dash-tiles b { display: block; font-size: .92rem; font-variant-numeric: tabular-nums; }
  .dash-tiles span { color: #6b7280; font-size: .56rem; }
  .dash-chart { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 10px 6px; }
  .dash-chart .h { font-weight: 700; font-size: .66rem; margin-bottom: 6px; }
  .dash-bars { display: flex; align-items: flex-end; gap: 3px; height: 70px; border-bottom: 1px solid #e5e7eb;
    background-image: linear-gradient(to top, #eef0f4 1px, transparent 1px); background-size: 100% 25%; }
  .dash-bars i { flex: 1; background: #4f46e5; border-radius: 3px 3px 0 0; display: block; }
  .dash-lists { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
  .dash-lists div { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 7px 8px; }
  .dash-lists .h { font-weight: 700; font-size: .62rem; margin-bottom: 4px; }
  .dash-lists p { display: flex; justify-content: space-between; margin: 2px 0; font-size: .6rem; position: relative; padding: 2px 4px; border-radius: 4px; overflow: hidden; }
  .dash-lists p em { position: absolute; inset: 0 auto 0 0; background: #eef0ff; z-index: 0; }
  .dash-lists p span { position: relative; z-index: 1; font-style: normal; }
  .dash-lists p span:last-child { color: #6b7280; }
  @media (max-width: 480px) { .dash-tiles { grid-template-columns: 1fr 1fr; } }
  .hero-grid { display: grid; gap: 40px; align-items: center; grid-template-columns: 1fr; }
  @media (min-width: 900px) { .hero-grid { grid-template-columns: 1.05fr 1fr; } }
  footer { border-top: 1px solid var(--border); padding: 24px 0 40px; color: var(--muted); font-size: .9rem; }
  button.link { background: none; border: 0; padding: 0; font: inherit; color: var(--muted); text-decoration: underline; cursor: pointer; }
</style>
<script src="/stats/consent.js" data-privacy="/adatkezeles/" defer></script>
<script src="/stats/t.js" defer></script>
</head>
<body>
<header><div class="wrap"><a href="/">my-ai.hu</a><a href="/" style="font-weight:500;color:var(--muted)">← Főoldal</a></div></header>

<main>
  <section class="hero">
    <div class="wrap hero-grid">
      <div>
      <span class="eyebrow">Ingyenes letöltés</span>
      <h1>Látogatószámláló a saját weboldaladra</h1>
      <p class="lead">Lásd, hányan jönnek, honnan érkeznek, mennyi időt töltenek az oldaladon és mire kattintanak — külső szolgáltatás nélkül, minden adat a saját tárhelyeden marad.</p>
      <?php if ($size !== ''): ?>
        <a class="btn" href="?fajl=latogatoszamlalo" data-track="Letöltés: látogatószámláló">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
          Letöltés (zip, <?= $e($size) ?>)
        </a>
        <p class="meta">Verzió: <?= $e($version) ?> · Frissítve: <?= $e($updated) ?> · A telepítési útmutató a csomagban van (TELEPITES.md).</p>
      <?php else: ?>
        <p class="meta">A csomag hamarosan elérhető.</p>
      <?php endif; ?>
      </div>
        <div aria-hidden="true">
          <div class="browser">
            <div class="browser-bar"><i></i><i></i><i></i><span>a-weboldalad.hu/stats</span></div>
            <div class="dash-top">Látogatók<small>Utolsó 30 nap</small></div>
            <div class="dash-body">
              <div class="dash-tiles">
                <div><b>1 284</b><span>látogató</span></div>
                <div><b>3 902</b><span>megtekintés</span></div>
                <div><b>1 p 42</b><span>átlagos idő</span></div>
                <div><b>611</b><span>kattintás</span></div>
              </div>
              <div class="dash-chart">
                <div class="h">Oldalmegtekintések naponta</div>
                <div class="dash-bars"><i style="height:38%"></i><i style="height:52%"></i><i style="height:45%"></i><i style="height:61%"></i><i style="height:58%"></i><i style="height:30%"></i><i style="height:26%"></i><i style="height:49%"></i><i style="height:66%"></i><i style="height:72%"></i><i style="height:63%"></i><i style="height:80%"></i><i style="height:41%"></i><i style="height:35%"></i><i style="height:57%"></i><i style="height:74%"></i><i style="height:69%"></i><i style="height:88%"></i><i style="height:92%"></i><i style="height:47%"></i><i style="height:39%"></i></div>
              </div>
              <div class="dash-lists">
                <div><div class="h">Honnan jöttek</div><p><em style="width:100%"></em><span>google.com</span><span>412</span></p><p><em style="width:61%"></em><span>facebook.com</span><span>251</span></p><p><em style="width:23%"></em><span>instagram.com</span><span>96</span></p></div>
                <div><div class="h">Mire kattintottak</div><p><em style="width:100%"></em><span>Időpontfoglalás</span><span>188</span></p><p><em style="width:57%"></em><span>Telefonszám</span><span>107</span></p><p><em style="width:35%"></em><span>Árak</span><span>66</span></p></div>
              </div>
            </div>
          </div>
        </div>
    </div>
  </section>

  <section class="soft">
    <div class="wrap">
      <h2>Mit tud?</h2>
      <div class="grid">
        <div class="card"><h3>Látogatók és oldalak</h3><p>Egyedi és új látogatók, oldalmegtekintések napi és óránkénti grafikonon, a legnézettebb oldalak.</p></div>
        <div class="card"><h3>Valódi eltöltött idő</h3><p>Csak azt az időt méri, amíg a lap tényleg látható volt — nem a háttérben nyitva felejtett füleket.</p></div>
        <div class="card"><h3>Kattintások</h3><p>Melyik gombra, linkre kattintanak a látogatók, és hova vezetett.</p></div>
        <div class="card"><h3>Honnan jöttek</h3><p>Hivatkozó oldalak (Google, Facebook…), böngészők, rendszerek, eszközök (asztali, mobil, tablet).</p></div>
        <div class="card"><h3>Egyenkénti látogatások</h3><p>IP-cím, időpont, böngésző, eltöltött idő, kattintások — és egy látogató teljes útja az oldalon.</p></div>
        <div class="card"><h3>GDPR-barát</h3><p>Beépített hozzájárulás-sáv, IP-anonimizálás, kizárható saját IP, automatikus adattörlés, robotszűrés.</p></div>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <h2>Telepítés 5 perc alatt</h2>
      <div class="req">
        <span class="chip">PHP 7.4+ (8.x ajánlott)</span>
        <span class="chip">MySQL / MariaDB</span>
        <span class="chip">Bármely megosztott tárhely</span>
        <span class="chip">Nincs Composer, nincs build</span>
      </div>
      <ol class="steps">
        <li>Töltsd fel a kicsomagolt <code>latogatoszamlalo</code> mappát a tárhelyedre (pl. <code>/stats</code> néven).</li>
        <li>Másold az <code>api/config.example.php</code>-t <code>api/config.php</code> néven, és írd bele az adatbázis adatait.</li>
        <li>Nyisd meg a <code>/stats/install.php</code> oldalt, és hozd létre az admin fiókot.</li>
        <li>Illeszd be a két soros kódot a weboldalad <code>&lt;head&gt;</code> részébe — a pontos kódot az irányítópult Beállítások fülén is megtalálod.</li>
      </ol>
      <p class="meta">Elakadtál, vagy szeretnéd, hogy beállítsuk helyetted? Írj: <a href="mailto:info@my-ai.hu">info@my-ai.hu</a></p>
    </div>
  </section>
</main>

<footer><div class="wrap">© <?= date('Y') ?> my-ai.hu · <a href="/">Főoldal</a> · <a href="/adatkezeles/">Adatkezelési tájékoztató</a> · <button type="button" class="link" onclick="window.myaiConsent && myaiConsent.open()">Süti-beállítások</button></div></footer>
</body>
</html>
