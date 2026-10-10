<?php
// Adatkezelési tájékoztató (my-ai.hu/adatkezeles/). Az adatkezelő adatait
// itt kell kitölteni; a módosítás dátumát frissítsd, ha a tartalom változik.
declare(strict_types=1);

// http → https (a böngészőben megnyitott oldalakon; helyi gépen / IP-címen nem)
(function (): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (PHP_SAPI === 'cli' || $https || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || $host === ''
        || preg_match('/^(localhost|\d+\.\d+\.\d+\.\d+|\[[0-9a-f:]+\])(:\d+)?$/i', $host)) return;
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
})();

$controller = [
    'name'    => 'Immobilis Partners KFT',
    'address' => '1238. Budapest, Molnár utca 65/b',
    'taxid'   => '11941736-2-43',
    'email'   => 'info@my-ai.hu',
    'phone'   => '+36 30 584 5937',
];
$updated = '2026. október 10.';

$e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$v = fn (string $s): string => $s !== '' ? $e($s) : '<mark>[kitöltendő]</mark>';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Adatkezelési tájékoztató – my-ai.hu</title>
<meta name="description" content="Hogyan kezeli a my-ai.hu a látogatók és ügyfelek személyes adatait.">
<meta name="theme-color" content="#4f46e5">
<style>
  :root { --bg: #fff; --text: #0f172a; --muted: #5b6477; --border: #e6e8f0; --soft: #f5f6fb; --accent: #4f46e5; color-scheme: light; }
  @media (prefers-color-scheme: dark) { :root { --bg: #0b0e17; --text: #eef1f7; --muted: #9aa3b5; --border: #242a38; --soft: #11151f; --accent: #a5a2ff; color-scheme: dark; } }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 17px/1.65 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  a { color: var(--accent); }
  .wrap { max-width: 780px; margin: 0 auto; padding: 0 16px; }
  header { border-bottom: 1px solid var(--border); }
  header .wrap { display: flex; align-items: center; justify-content: space-between; height: 60px; }
  header a { text-decoration: none; font-weight: 800; color: var(--text); }
  main { padding: 40px 0 60px; }
  h1 { font-size: clamp(1.8rem, 5vw, 2.4rem); line-height: 1.15; margin: 0 0 8px; letter-spacing: -0.02em; }
  h2 { font-size: 1.3rem; margin: 40px 0 10px; letter-spacing: -0.01em; }
  h3 { font-size: 1.05rem; margin: 24px 0 6px; }
  p, li { margin: 0 0 10px; }
  .muted { color: var(--muted); }
  .box { background: var(--soft); border: 1px solid var(--border); border-radius: 14px; padding: 16px 18px; margin: 16px 0; }
  table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: .95rem; }
  th, td { text-align: left; vertical-align: top; padding: 9px 10px; border-bottom: 1px solid var(--border); }
  th { width: 34%; color: var(--muted); font-weight: 600; }
  mark { background: #fde68a; color: #0f172a; padding: 0 4px; border-radius: 4px; }
  button.link { background: none; border: 0; padding: 0; font: inherit; color: var(--accent); text-decoration: underline; cursor: pointer; }
  footer { border-top: 1px solid var(--border); padding: 24px 0 40px; color: var(--muted); font-size: .9rem; }
  @media (max-width: 560px) { th { width: 40%; } }
</style>
<script src="/stats/consent.js" data-privacy="/adatkezeles/" defer></script>
<script src="/stats/t.js" defer></script>
</head>
<body>
<header><div class="wrap"><a href="/">my-ai.hu</a><a href="/" style="font-weight:500;color:var(--muted)">← Főoldal</a></div></header>

<main class="wrap">
  <h1>Adatkezelési tájékoztató</h1>
  <p class="muted">Hatályos: <?= $e($updated) ?></p>

  <p>Ez a tájékoztató leírja, milyen személyes adatokat kezelünk a my-ai.hu weboldalon és az itt működő alkalmazásokban (időpontfoglaló, chatbot), milyen célból, mennyi ideig, és milyen jogaid vannak. A tájékoztató az Európai Unió általános adatvédelmi rendelete (GDPR) és az információs önrendelkezési jogról szóló 2011. évi CXII. törvény alapján készült.</p>

  <h2>1. Az adatkezelő</h2>
  <table>
    <tr><th>Név</th><td><?= $v($controller['name']) ?></td></tr>
    <tr><th>Cím</th><td><?= $v($controller['address']) ?></td></tr>
    <?php if ($controller['taxid'] !== ''): ?><tr><th>Adószám</th><td><?= $e($controller['taxid']) ?></td></tr><?php endif; ?>
    <tr><th>Email</th><td><a href="mailto:<?= $e($controller['email']) ?>"><?= $e($controller['email']) ?></a></td></tr>
    <tr><th>Telefon</th><td><a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $controller['phone'])) ?>"><?= $e($controller['phone']) ?></a></td></tr>
  </table>

  <h2>2. Milyen adatokat kezelünk, és miért?</h2>

  <h3>2.1. Látogatottsági statisztika</h3>
  <p>Csak akkor mérünk, ha ehhez a megjelenő sávban hozzájárultál. Hozzájárulás nélkül az oldal teljes értékűen használható.</p>
  <table>
    <tr><th>Adatok</th><td>IP-cím, a látogatás időpontja, böngésző és verziója, operációs rendszer, eszköztípus, képernyőméret, nyelvi beállítás, a meglátogatott oldal címe, a hivatkozó oldal, az oldalon (látható lappal) eltöltött idő, a kattintott linkek és gombok, valamint egy véletlenszerű látogatóazonosító, amelyet a böngésződ helyi tárhelyén (localStorage) tárolunk, hogy a visszatérő látogatót felismerjük.</td></tr>
    <tr><th>Cél</th><td>Annak mérése, hogy mely tartalmak hasznosak, és az oldal fejlesztése.</td></tr>
    <tr><th>Jogalap</th><td>A hozzájárulásod (GDPR 6. cikk (1) a) pont; az eszközön történő tárolás tekintetében az elektronikus hírközlésről szóló 2003. évi C. törvény 155. § (4) bekezdése).</td></tr>
    <tr><th>Időtartam</th><td>Legfeljebb 395 nap (kb. 13 hónap), utána automatikusan töröljük.</td></tr>
    <tr><th>Visszavonás</th><td>Bármikor, a <button type="button" class="link" onclick="window.myaiConsent && myaiConsent.open()">Süti-beállítások</button> linkkel; ekkor a böngésződben tárolt azonosítót is töröljük. A visszavonás nem érinti a korábbi adatkezelés jogszerűségét.</td></tr>
  </table>
  <p>A statisztikát saját rendszerünk készíti, külső elemzőszolgáltatást (pl. Google Analytics) nem használunk, és az adatokat nem adjuk át reklámcélra.</p>

  <h3>2.2. Kapcsolatfelvétel és ajánlatkérés</h3>
  <table>
    <tr><th>Adatok</th><td>Név, email cím, telefonszám, valamint az üzenetben megadott egyéb információk.</td></tr>
    <tr><th>Cél</th><td>A megkeresés megválaszolása, ajánlatadás, szerződéskötés előkészítése.</td></tr>
    <tr><th>Jogalap</th><td>Szerződés megkötését megelőző lépések (GDPR 6. cikk (1) b) pont).</td></tr>
    <tr><th>Időtartam</th><td>Az ügy lezárásáig; szerződéskötés esetén a számviteli és polgári jogi elévülési határidőkig.</td></tr>
  </table>

  <h3>2.3. Online időpontfoglaló</h3>
  <table>
    <tr><th>Adatok</th><td>Név, telefonszám, email cím, a választott szolgáltatás és időpont, a foglalási űrlapon megadott egyéb adatok (pl. rendszám), megjegyzés, valamint a foglaláskor használt IP-cím (visszaélések kiszűrésére).</td></tr>
    <tr><th>Cél</th><td>Az időpont lefoglalása, visszaigazolása, módosítása és a kapcsolódó értesítések küldése.</td></tr>
    <tr><th>Jogalap</th><td>Szerződés teljesítése, illetve annak előkészítése (GDPR 6. cikk (1) b) pont).</td></tr>
    <tr><th>Időtartam</th><td>Amíg a foglalás kezeléséhez szükséges; kérésre töröljük.</td></tr>
  </table>
  <div class="box">Ha egy vállalkozás a saját időpontfoglalóját vagy chatbotját üzemelteti nálunk (pl. <em>my-ai.hu/cégnév</em> címen), az ott megadott adatok kezelője <strong>az adott vállalkozás</strong>; mi adatfeldolgozóként, az ő megbízásából tároljuk az adatokat. Ilyen esetben a vállalkozás adatkezelési tájékoztatója az irányadó.</div>

  <h3>2.4. Ügyintézési Segéd (chatbot)</h3>
  <p>A weboldalon és az ügyfeleink weboldalán működő Ügyintézési Segéd szabályalapú chatbot: a kérdésben szereplő kulcsszavak alapján, előre megírt válaszokból felel. A kérdések feldolgozása a látogató böngészőjében történik — <strong>a beírt kérdéseket nem tároljuk, és nem továbbítjuk sem nekünk, sem harmadik félnek</strong>, mesterséges intelligencia szolgáltatást nem használ. Kérjük, ne adj meg a chatben személyes adatot.</p>

  <h3>2.5. Megrendelés és előfizetés (Ügyintézési Segéd)</h3>
  <table>
    <tr><th>Adatok</th><td>Cég / vállalkozás neve, kapcsolattartó neve, email cím, telefonszám, weboldal címe, számlázási név és cím, adószám, a megrendeléskor megadott üzenet, a befizetések és az előfizetés érvényessége, valamint a tanító felület belépési adatai (email cím, a jelszó csak visszafejthetetlen, hash formában) és a chatbot tudásbázisa.</td></tr>
    <tr><th>Cél</th><td>A megrendelés teljesítése, a szolgáltatás beállítása, díjbekérő és számla kiállítása, a lejárat előtti emlékeztetők küldése.</td></tr>
    <tr><th>Jogalap</th><td>Szerződés teljesítése (GDPR 6. cikk (1) b) pont); a számlázási adatok tekintetében jogi kötelezettség teljesítése (GDPR 6. cikk (1) c) pont, a számvitelről szóló 2000. évi C. törvény).</td></tr>
    <tr><th>Időtartam</th><td>Az előfizetés megszűnéséig; a számviteli bizonylatok adatait a számviteli törvény szerint 8 évig őrizzük.</td></tr>
  </table>

  <h2>3. Kik férhetnek hozzá az adatokhoz? (adatfeldolgozók)</h2>
  <table>
    <tr><th>DiMa.hu tárhelyszolgáltató</th><td>A weboldal és az adatbázis tárhelye (Magyarország).</td></tr>
    <tr><th>Email-szolgáltatás</th><td>A visszaigazoló és értesítő levelek kézbesítése a tárhelyszolgáltató levelezőrendszerén keresztül.</td></tr>
  </table>
  <p>Az adatokat más harmadik félnek nem adjuk át, kivéve, ha jogszabály kötelez rá (pl. hatósági megkeresés).</p>

  <h2>4. Adatbiztonság</h2>
  <p>Az adatokat jelszóval védett adminisztrációs felületen, hozzáférés-korlátozással kezeljük; a jelszavakat csak visszafejthetetlen (hash) formában tároljuk. Az adatbázis-hozzáférés adatait nyilvános helyen nem tároljuk.</p>

  <h2>5. Jogaid</h2>
  <ul>
    <li><strong>Hozzáférés:</strong> tájékoztatást kérhetsz arról, milyen adataidat kezeljük.</li>
    <li><strong>Helyesbítés:</strong> kérheted a pontatlan adatok javítását.</li>
    <li><strong>Törlés:</strong> kérheted adataid törlését („elfeledtetés”).</li>
    <li><strong>Korlátozás:</strong> kérheted az adatkezelés korlátozását.</li>
    <li><strong>Adathordozhatóság:</strong> kérheted, hogy az általad megadott adatokat géppel olvasható formában kiadjuk.</li>
    <li><strong>Tiltakozás</strong> és a <strong>hozzájárulás visszavonása</strong> bármikor.</li>
  </ul>
  <p>Kérésedet a <a href="mailto:<?= $e($controller['email']) ?>"><?= $e($controller['email']) ?></a> címen jelezheted; legkésőbb egy hónapon belül válaszolunk.</p>

  <h2>6. Jogorvoslat</h2>
  <p>Ha úgy érzed, hogy adataid kezelése nem megfelelő, kérjük, először keress minket. Panaszt tehetsz a felügyeleti hatóságnál is:</p>
  <div class="box">
    <strong>Nemzeti Adatvédelmi és Információszabadság Hatóság (NAIH)</strong><br>
    1055 Budapest, Falk Miksa utca 9–11.<br>
    Postacím: 1363 Budapest, Pf. 9.<br>
    Telefon: +36 1 391 1400 · Email: <a href="mailto:ugyfelszolgalat@naih.hu">ugyfelszolgalat@naih.hu</a> · <a href="https://www.naih.hu" rel="noopener">www.naih.hu</a>
  </div>
  <p>Jogaid megsértése esetén bírósághoz is fordulhatsz; a pert a lakóhelyed szerinti törvényszék előtt is megindíthatod.</p>

  <h2>7. A tájékoztató módosítása</h2>
  <p>A tájékoztatót szükség szerint frissítjük; a mindenkori változat ezen az oldalon érhető el, a hatályba lépés dátumával.</p>
</main>

<footer><div class="wrap">© <?= date('Y') ?> my-ai.hu · <a href="/">Főoldal</a> · <a href="/aszf/">ÁSZF</a> · <button type="button" class="link" onclick="window.myaiConsent && myaiConsent.open()">Süti-beállítások</button></div></footer>
<script src="/ugyintezes/widget.js" data-ugyfel="my-ai" data-szin="#4f46e5" data-felirat="Kérdezz tőlünk" defer></script>
</body>
</html>
