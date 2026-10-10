<?php
// Általános Szerződési Feltételek (my-ai.hu/aszf/) — Ügyintézési Segéd előfizetés.
// A szolgáltató adatai lent; a hatály dátumát frissítsd, ha a tartalom változik.
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

$provider = [
    'name'     => 'Immobilis Partners KFT',
    'address'  => '1238. Budapest, Molnár utca 65/b',
    'taxid'    => '11941736-2-43',
    'regno'    => '01-09-684414',
    'email'    => 'info@my-ai.hu',
    'phone'    => '+36 30 584 5937',
];
$updated = '2026. október 10.';

$e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$v = fn (string $s): string => $s !== '' ? $e($s) : '<mark>[kitöltendő]</mark>';
$mail = '<a href="mailto:' . $e($provider['email']) . '">' . $e($provider['email']) . '</a>';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ÁSZF – my-ai.hu</title>
<meta name="description" content="Az Ügyintézési Segéd havidíjas chatbot-szolgáltatás általános szerződési feltételei.">
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
  <h1>Általános Szerződési Feltételek</h1>
  <p class="muted">Ügyintézési Segéd havidíjas szolgáltatás · Hatályos: <?= $e($updated) ?></p>

  <p>Ez az Általános Szerződési Feltételek (a továbbiakban: ÁSZF) tartalmazza a my-ai.hu weboldalon megrendelhető Ügyintézési Segéd chatbot-szolgáltatás igénybevételének feltételeit. A megrendeléssel a Megrendelő elfogadja az ÁSZF-et.</p>

  <h2>1. A szolgáltató</h2>
  <table>
    <tr><th>Név</th><td><?= $v($provider['name']) ?></td></tr>
    <tr><th>Székhely</th><td><?= $v($provider['address']) ?></td></tr>
    <tr><th>Cégjegyzékszám</th><td><?= $v($provider['regno']) ?></td></tr>
    <tr><th>Adószám</th><td><?= $v($provider['taxid']) ?></td></tr>
    <tr><th>Email</th><td><?= $mail ?></td></tr>
    <tr><th>Telefon</th><td><a href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $provider['phone'])) ?>"><?= $e($provider['phone']) ?></a></td></tr>
    <tr><th>Tárhelyszolgáltató</th><td>DiMa.hu (<a href="https://www.dima.hu" rel="noopener">www.dima.hu</a>)</td></tr>
  </table>
  <p>(a továbbiakban: Szolgáltató)</p>

  <h2>2. Az ÁSZF hatálya</h2>
  <p>2.1. Az ÁSZF a Szolgáltató és a szolgáltatást megrendelő vállalkozás, egyéni vállalkozó vagy szervezet (a továbbiakban: Megrendelő) között létrejövő szerződésre vonatkozik. A szolgáltatás gazdálkodó szervezeteknek szól; fogyasztónak (önálló foglalkozásán és gazdasági tevékenységén kívül eljáró természetes személynek) nem nyújtjuk.</p>
  <p>2.2. A szerződés elektronikus úton, magyar nyelven jön létre. Nem minősül írásba foglalt szerződésnek, a Szolgáltató nem iktatja; tartalmát a megrendelés visszaigazoló levele és ez az ÁSZF rögzíti.</p>
  <p>2.3. Az ÁSZF-ben nem szabályozott kérdésekben a magyar jog, különösen a Polgári Törvénykönyvről szóló 2013. évi V. törvény (Ptk.) és az elektronikus kereskedelmi szolgáltatásokról szóló 2001. évi CVIII. törvény rendelkezései az irányadók.</p>

  <h2>3. A szolgáltatás tartalma</h2>
  <p>3.1. Az Ügyintézési Segéd szabályalapú ügyfélszolgálati chatbot, amely a Megrendelő weboldalán egy beépített chat-ablakban válaszol a látogatók kérdéseire, kizárólag a Megrendelővel egyeztetett témákból és válaszokból álló tudásbázis alapján. A szolgáltatás nem használ mesterséges intelligenciát, és nem fogalmaz meg a tudásbázison kívüli válaszokat.</p>
  <p>3.2. A havidíj tartalmazza:</p>
  <ul>
    <li>a tudásbázis összeállítását a Megrendelő által megadott információk alapján, és havonta észszerű mértékű módosítását;</li>
    <li>a chatbot működtetését a Szolgáltató szerverén;</li>
    <li>a beépítő kód átadását, és kérésre a beépítést a Megrendelő weboldalába, ha ehhez a Megrendelő hozzáférést biztosít;</li>
    <li>saját belépést a tanító felülethez (my-ai.hu/ugyintezes/fiok/), ahol a Megrendelő a tudásbázist maga is szerkesztheti és kipróbálhatja.</li>
  </ul>
  <p>3.3. A látogatók kérdéseit a chatbot a látogató böngészőjében dolgozza fel; a kérdéseket a Szolgáltató nem tárolja és nem továbbítja.</p>
  <p>3.4. A Szolgáltató a folyamatos elérhetőségre törekszik, de nem garantálja a megszakítás nélküli működést. Karbantartás, a tárhelyszolgáltató hibája vagy külső ok miatti rövid kiesés nem jelent szerződésszegést.</p>

  <h2>4. A szerződés létrejötte</h2>
  <p>4.1. A megrendelés a <a href="/appok/ugyintezesi-seged/#megrendeles">megrendelő űrlapon</a> történik. Az elküldés előtt a Megrendelő az adatait az űrlapon ellenőrizheti és javíthatja; a megrendelést az ÁSZF elfogadásával küldheti el.</p>
  <p>4.2. A Szolgáltató a megrendelés beérkezését automatikus emailben igazolja vissza. Ez a visszaigazolás a megrendelés megérkezését jelzi, nem a megrendelés elfogadását.</p>
  <p>4.3. A szerződés akkor jön létre, amikor a Szolgáltató a megrendelést emailben elfogadja, vagy elküldi az első díjbekérőt. A Szolgáltató a megrendelést indoklás nélkül elutasíthatja.</p>
  <p>4.4. A chatbot az első díj beérkezése és a tudásbázis elkészülte után indul.</p>

  <h2>5. Díjak és fizetés</h2>
  <p>5.1. A szolgáltatás díja havi <strong>5 000 Ft + ÁFA</strong>, bruttó 6 350 Ft, kivéve, ha a megrendeléskor a megrendelő oldalon más díj szerepelt.</p>
  <p>5.2. A díj előre fizetendő, a Szolgáltató díjbekérője alapján, banki átutalással, egy vagy több hónapra (például 1, 3 vagy 12 hónapra). A beérkezett összegről a Szolgáltató számlát állít ki.</p>
  <p>5.3. A befizetés a már kifizetett időszak végétől hosszabbítja meg az előfizetést; lejárt előfizetésnél a befizetés napjától indul az új időszak.</p>
  <p>5.4. A Szolgáltató a díjat legalább 30 nappal előre, emailben közölt értesítéssel módosíthatja. Az új díj a következő, még ki nem fizetett időszaktól érvényes. Ha a Megrendelő nem fogadja el, az új díj hatálybalépéséig felmondhat.</p>

  <h2>6. Időtartam, lejárat és megszűnés</h2>
  <p>6.1. A szerződés határozatlan időre jön létre, előre kifizetett havi időszakokkal.</p>
  <p>6.2. A Szolgáltató a kifizetett időszak lejárta előtt 7 és 1 nappal emlékeztetőt küld. Ha a következő időszak díja a lejáratig nem érkezik meg, a chatbot automatikusan szünetel: a weboldalon „A chat jelenleg nem elérhető” üzenet jelenik meg. A díj beérkezése után a chatbot újra működik.</p>
  <p>6.3. A Megrendelő a szerződést bármikor, indoklás nélkül felmondhatja emailben (<?= $mail ?>). A felmondás a kifizetett időszak végén hatályos; a megkezdett időszak díját a Szolgáltató nem téríti vissza.</p>
  <p>6.4. A Szolgáltató a szerződést 30 napos határidővel, emailben felmondhatja; ilyenkor a fel nem használt, előre kifizetett teljes hónapok díját visszatéríti. Súlyos szerződésszegés esetén (például a 7. pontba ütköző használat) a Szolgáltató azonnali hatállyal is felmondhat.</p>
  <p>6.5. Ha a díj a lejárattól számított 90 napon belül nem érkezik meg, a szerződés megszűnik, és a Szolgáltató a tudásbázist törölheti.</p>

  <h2>7. A Megrendelő kötelezettségei</h2>
  <p>7.1. A Megrendelő felel a tudásbázishoz átadott információk (például árak, nyitvatartás, ügyintézési tudnivalók) valóságáért, naprakészségéért és jogszerűségéért. A változásokat a Megrendelő jelzi a Szolgáltatónak, vagy a tanító felületen maga módosítja; az így módosított tartalomért is a Megrendelő felel.</p>
  <p>7.4. A Megrendelő a tanító felület jelszavát titokban tartja; a belépési adataival végzett módosításokért felel.</p>
  <p>7.2. A Megrendelő a beépítő kódot csak a saját, a megrendelésben megjelölt vagy a Szolgáltatóval egyeztetett weboldalán használhatja.</p>
  <p>7.3. A szolgáltatás nem használható jogszabályba ütköző, megtévesztő vagy harmadik személy jogait sértő célra.</p>

  <h2>8. Felelősség</h2>
  <p>8.1. A chatbot a tudásbázisban szereplő válaszokat adja. Előfordulhat, hogy egy kérdést nem ismer fel, vagy nem a legmegfelelőbb választ adja. A Szolgáltató nem felel a válaszok alapján hozott döntésekért, és a 7.1. pont szerinti tartalomért sem.</p>
  <p>8.2. A Szolgáltató szerződésszegéssel okozott kárért való felelőssége – a szándékosan, súlyos gondatlansággal, illetve az emberi életet, testi épséget vagy egészséget megsértő szerződésszegés kivételével – a Megrendelő által a kár bekövetkezését megelőző 3 hónapban megfizetett díj összegéig terjed.</p>

  <h2>9. Szellemi tulajdon</h2>
  <p>A chatbot szoftvere a Szolgáltató tulajdona; a Megrendelő a szerződés időtartamára nem kizárólagos, át nem ruházható használati jogot kap. A tudásbázis tartalma a Megrendelőé; a szerződés megszűnésekor kérésére a Szolgáltató átadja.</p>

  <h2>10. Adatkezelés</h2>
  <p>A megrendeléskor megadott adatok kezelését az <a href="/adatkezeles/">adatkezelési tájékoztató</a> írja le.</p>

  <h2>11. Kapcsolat, panaszkezelés, jogviták</h2>
  <p>11.1. Kérdést, kifogást a <?= $mail ?> címen vagy telefonon lehet jelezni; a Szolgáltató 8 munkanapon belül érdemben válaszol.</p>
  <p>11.2. A felek a vitákat elsősorban egyeztetéssel rendezik. Ennek sikertelensége esetén a hatáskörrel és illetékességgel rendelkező magyar bíróság jár el.</p>

  <h2>12. Az ÁSZF módosítása</h2>
  <p>A Szolgáltató az ÁSZF-et módosíthatja; a módosítást legalább 15 nappal a hatálybalépés előtt emailben közli a Megrendelőkkel. A mindenkori változat ezen az oldalon érhető el. Ha a Megrendelő a módosítást nem fogadja el, a hatálybalépésig felmondhat.</p>
</main>

<footer><div class="wrap">© <?= date('Y') ?> my-ai.hu · <a href="/">Főoldal</a> · <a href="/adatkezeles/">Adatkezelési tájékoztató</a> · <button type="button" class="link" onclick="window.myaiConsent && myaiConsent.open()">Süti-beállítások</button></div></footer>
<script src="/ugyintezes/widget.js" data-ugyfel="my-ai" data-szin="#4f46e5" data-felirat="Kérdezz tőlünk" defer></script>
</body>
</html>
