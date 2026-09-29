<?php
// my-ai.hu főoldal. Egyetlen, függőség nélküli oldal; a lenti beállításokkal
// szabható. Az üresen hagyott kapcsolati adatok nem jelennek meg.
declare(strict_types=1);

$site = [
    'name'    => 'my-ai.hu',
    'email'   => 'info@my-ai.hu',   // ha üres, a kapcsolat szakasz rejtve marad
    'phone'   => '+36 30 584 5937',
    'demoUrl' => '/foglalas/',
];

$e = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$hasContact = $site['email'] !== '' || $site['phone'] !== '';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>my-ai.hu – Egyedi weboldalak és appok, rövid határidővel</title>
<meta name="description" content="Egyedi webes és alkalmazásfejlesztés rövid határidővel. A vállalkozásodra szabott appok, amelyek telefonon és weben is működnek — App Store nélkül. Kész megoldásunk: online időpontfoglaló.">
<meta property="og:title" content="my-ai.hu – Saját app a vállalkozásodnak">
<meta property="og:description" content="Egyedi weboldalak és appok rövid határidővel — telefonon és weben, a vállalkozásod arculatával.">
<meta property="og:type" content="website">
<meta name="theme-color" content="#4f46e5">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%234f46e5'/%3E%3Crect x='20' y='10' width='24' height='44' rx='6' fill='none' stroke='white' stroke-width='4'/%3E%3Ccircle cx='32' cy='46' r='2.5' fill='white'/%3E%3C/svg%3E">
<style>
  :root {
    --bg: #ffffff;
    --bg-soft: #f5f6fb;
    --card: #ffffff;
    --text: #0f172a;
    --muted: #5b6477;
    --border: #e6e8f0;
    --accent: #4f46e5;
    --accent-2: #0ea5a4;
    --accent-soft: #eef0ff;
    --on-accent: #ffffff;
    --shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 12px 32px rgba(15, 23, 42, .07);
    --radius: 18px;
    color-scheme: light;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0b0e17;
      --bg-soft: #11151f;
      --card: #151a26;
      --text: #eef1f7;
      --muted: #9aa3b5;
      --border: #242a38;
      --accent: #8b87ff;
      --accent-2: #2dd4bf;
      --accent-soft: #1d1f3a;
      --on-accent: #0b0e17;
      --shadow: none;
      color-scheme: dark;
    }
  }

  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 17px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  a { color: inherit; }
  h1, h2, h3 { line-height: 1.15; letter-spacing: -0.02em; margin: 0; }
  p { margin: 0; }
  .wrap { max-width: 1120px; margin: 0 auto; padding: 0 16px; }
  section { padding: 72px 0; }
  .eyebrow { display: inline-block; font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px; }
  .lead { color: var(--muted); font-size: 1.1rem; max-width: 620px; }
  .center { text-align: center; }
  .center .lead { margin: 14px auto 0; }
  h2 { font-size: clamp(1.7rem, 4vw, 2.4rem); }

  /* gombok */
  .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 22px; border-radius: 12px; font-weight: 650; text-decoration: none; border: 1px solid transparent; transition: transform .15s ease, box-shadow .15s ease; }
  .btn:hover { transform: translateY(-1px); }
  .btn:focus-visible, a:focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
  .btn-primary { background: var(--accent); color: var(--on-accent); box-shadow: 0 6px 20px color-mix(in srgb, var(--accent) 35%, transparent); }
  .btn-ghost { border-color: var(--border); background: var(--card); }

  /* fejléc */
  .nav { position: sticky; top: 0; z-index: 20; background: color-mix(in srgb, var(--bg) 85%, transparent); backdrop-filter: saturate(160%) blur(12px); -webkit-backdrop-filter: saturate(160%) blur(12px); border-bottom: 1px solid var(--border); }
  .nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 64px; }
  .logo { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 1.1rem; text-decoration: none; letter-spacing: -0.01em; }
  .logo-mark { width: 30px; height: 30px; border-radius: 9px; background: linear-gradient(135deg, var(--accent), var(--accent-2)); display: grid; place-items: center; }
  .logo-mark::after { content: ""; width: 10px; height: 16px; border: 2px solid #fff; border-radius: 3px; }
  .nav-links { display: flex; gap: 22px; align-items: center; }
  .nav-links a { text-decoration: none; color: var(--muted); font-weight: 550; font-size: .95rem; }
  .nav-links a:hover { color: var(--text); }
  .nav-links .btn { color: var(--on-accent); padding: 9px 16px; font-size: .92rem; }
  @media (max-width: 720px) { .nav-links a:not(.btn) { display: none; } }

  /* hero */
  .hero { padding: 64px 0 40px; background: radial-gradient(1000px 500px at 85% -10%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 70%), radial-gradient(700px 400px at -10% 30%, color-mix(in srgb, var(--accent-2) 12%, transparent), transparent 70%); overflow: hidden; }
  .hero .wrap { display: grid; gap: 48px; align-items: center; grid-template-columns: 1fr; }
  @media (min-width: 900px) { .hero .wrap { grid-template-columns: 1.1fr .9fr; } .hero { padding: 96px 0 72px; } }
  .hero h1 { font-size: clamp(2.2rem, 6vw, 3.6rem); margin-bottom: 18px; }
  .hero h1 .grad { background: linear-gradient(90deg, var(--accent), var(--accent-2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .hero .lead { margin-bottom: 28px; }
  .cta { display: flex; gap: 12px; flex-wrap: wrap; }
  .platforms { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 28px; }
  .chip { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: var(--card); border: 1px solid var(--border); font-size: .88rem; font-weight: 600; color: var(--muted); }
  .chip svg { width: 16px; height: 16px; fill: currentColor; }

  /* telefon makett */
  .phone-stage { display: flex; justify-content: center; }
  .phone { width: 290px; border-radius: 44px; padding: 12px; background: #0f172a; box-shadow: 0 30px 80px rgba(15, 23, 42, .28), inset 0 0 0 2px #1f2937; transform: rotate(-3deg); }
  .screen { border-radius: 34px; overflow: hidden; background: #f5f6f8; color: #111827; position: relative; }
  .notch { position: absolute; top: 8px; left: 50%; transform: translateX(-50%); width: 90px; height: 24px; border-radius: 14px; background: #0f172a; z-index: 2; }
  .app-top { background: #e4572e; color: #fff; padding: 44px 16px 14px; }
  .app-top b { display: block; font-size: 1rem; }
  .app-top span { font-size: .72rem; opacity: .85; }
  .app-body { padding: 14px; display: grid; gap: 10px; font-size: .78rem; }
  .app-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px; }
  .app-card .t { font-weight: 700; font-size: .82rem; margin-bottom: 6px; }
  .mini-cal { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; }
  .mini-cal i { font-style: normal; font-size: .62rem; color: #6b7280; }
  .mini-cal s { text-decoration: none; padding: 5px 0; border-radius: 7px; border: 1px solid #e5e7eb; font-weight: 600; font-size: .7rem; }
  .mini-cal s.off { border-color: transparent; color: #c0c4cc; font-weight: 400; }
  .mini-cal s.sel { background: #e4572e; border-color: #e4572e; color: #fff; }
  .mini-slots { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 8px; }
  .mini-slots s { text-decoration: none; text-align: center; padding: 6px 0; border: 1px solid #e5e7eb; border-radius: 7px; font-weight: 600; font-size: .7rem; }
  .mini-slots s.sel { background: #e4572e; border-color: #e4572e; color: #fff; }
  .mini-btn { background: #e4572e; color: #fff; text-align: center; padding: 10px; border-radius: 10px; font-weight: 700; font-size: .8rem; }

  /* sávok, kártyák */
  .soft { background: var(--bg-soft); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
  .grid { display: grid; gap: 18px; margin-top: 40px; grid-template-columns: 1fr; }
  @media (min-width: 640px) { .grid.two { grid-template-columns: 1fr 1fr; } .grid.three { grid-template-columns: 1fr 1fr; } }
  @media (min-width: 960px) { .grid.three { grid-template-columns: repeat(3, 1fr); } }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); }
  .card h3 { font-size: 1.12rem; margin-bottom: 8px; }
  .card p { color: var(--muted); font-size: .98rem; }
  .icon { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); margin-bottom: 14px; }
  .icon svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

  /* kiemelt app */
  .product { display: grid; gap: 32px; align-items: center; grid-template-columns: 1fr; margin-top: 40px; }
  @media (min-width: 900px) { .product { grid-template-columns: 1fr 1fr; } }
  .product-card { background: var(--card); border: 1px solid var(--border); border-radius: 24px; padding: 28px; box-shadow: var(--shadow); }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; background: color-mix(in srgb, var(--accent-2) 16%, transparent); color: var(--accent-2); font-weight: 700; font-size: .78rem; margin-bottom: 12px; }
  .product-card h3 { font-size: 1.6rem; margin-bottom: 10px; }
  .product-card > p { color: var(--muted); margin-bottom: 20px; }
  .checks { list-style: none; padding: 0; margin: 0 0 24px; display: grid; gap: 10px; }
  .checks li { display: flex; gap: 10px; align-items: flex-start; }
  .checks li::before { content: ""; flex: 0 0 20px; height: 20px; margin-top: 3px; border-radius: 50%; background: var(--accent) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M5 10.5l3 3 7-7' fill='none' stroke='white' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / 14px no-repeat; }
  .uses { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }

  /* lépések */
  .steps { counter-reset: step; }
  .step { position: relative; padding-top: 56px; }
  .step::before { counter-increment: step; content: counter(step); position: absolute; top: 24px; left: 24px; width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #fff; font-weight: 800; display: grid; place-items: center; }
  .step { padding: 76px 24px 24px; }

  /* kapcsolat */
  .contact { background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent-2) 80%, var(--accent))); color: #fff; border-radius: 28px; padding: 48px 24px; text-align: center; }
  .contact h2 { color: #fff; }
  .contact p { opacity: .9; margin: 12px auto 26px; max-width: 520px; }
  .contact .btn { background: #fff; color: #1e1b4b; }
  .contact .btn-ghost { background: transparent; color: #fff; border-color: rgba(255, 255, 255, .5); }

  footer { padding: 36px 0 calc(env(safe-area-inset-bottom) + 36px); color: var(--muted); font-size: .9rem; border-top: 1px solid var(--border); }
  footer .wrap { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  footer a { color: var(--muted); }
  .footer-links { display: flex; gap: 16px; flex-wrap: wrap; }
  .footer-btn { background: none; border: 0; padding: 0; font: inherit; color: var(--muted); text-decoration: underline; cursor: pointer; }

  @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .btn { transition: none; } .phone { transform: none; } }
</style>
<script src="/stats/consent.js" data-privacy="/adatkezeles/" defer></script>
<script src="/stats/t.js" defer></script>
</head>
<body>

<header class="nav">
  <div class="wrap">
    <a class="logo" href="/"><span class="logo-mark" aria-hidden="true"></span><?= $e($site['name']) ?></a>
    <nav class="nav-links" aria-label="Fő navigáció">
      <a href="#fejlesztes">Egyedi fejlesztés</a>
      <a href="#appok">Appok</a>
      <a href="#hogyan">Hogyan működik</a>
      <?php if ($hasContact): ?><a href="#kapcsolat">Kapcsolat</a><?php endif; ?>
      <a class="btn btn-primary" href="<?= $e($site['demoUrl']) ?>">Demó</a>
    </nav>
  </div>
</header>

<main>
  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow">Egyedi web- és appfejlesztés</span>
        <h1>Saját app a vállalkozásodnak, <span class="grad">telefonon és weben.</span></h1>
        <p class="lead">Egyedi webes és alkalmazásigényeket fejlesztünk, rövid határidővel, a te arculatoddal és a te működésedre szabva. Az ügyfeleid böngészőből nyitják meg, vagy egy koppintással a telefonjuk kezdőképernyőjére teszik, App Store és letöltés nélkül.</p>
        <div class="cta">
          <a class="btn btn-primary" href="<?= $e($site['demoUrl']) ?>">Próbáld ki az időpontfoglalót →</a>
          <a class="btn btn-ghost" href="#fejlesztes">Egyedi fejlesztés</a>
        </div>
        <div class="platforms" aria-label="Támogatott eszközök">
          <span class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.4 12.6c0-2.4 2-3.6 2.1-3.7-1.2-1.7-3-1.9-3.6-2-1.5-.2-3 .9-3.8.9-.8 0-2-.9-3.3-.8-1.7 0-3.3 1-4.2 2.5-1.8 3.1-.5 7.7 1.3 10.2.9 1.2 1.9 2.6 3.2 2.6 1.3-.1 1.8-.8 3.3-.8s2 .8 3.3.8c1.4 0 2.3-1.3 3.1-2.5 1-1.4 1.4-2.8 1.4-2.9 0 0-2.8-1.1-2.8-4.3zM14 5.3c.7-.8 1.2-2 1-3.2-1 .1-2.3.7-3 1.5-.7.8-1.2 2-1.1 3.1 1.2.1 2.4-.6 3.1-1.4z"/></svg>iPhone és iPad</span>
          <span class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.6 9.5l1.7-3a.4.4 0 00-.7-.4l-1.8 3A10.6 10.6 0 0012 8.2c-1.7 0-3.3.3-4.8 1L5.4 6.1a.4.4 0 00-.7.4l1.7 3C3.6 11 1.7 13.8 1.5 17h21c-.2-3.2-2.1-6-4.9-7.5zM7 14.4a1 1 0 110-2 1 1 0 010 2zm10 0a1 1 0 110-2 1 1 0 010 2z"/></svg>Android</span>
          <span class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm6.9 6h-3a15.7 15.7 0 00-1.3-4 8 8 0 014.3 4zM12 4c.8 1.2 1.5 2.5 1.9 4h-3.8c.4-1.5 1.1-2.8 1.9-4zM4.3 14a8 8 0 010-4h3.4a16.5 16.5 0 000 4H4.3zm.8 2h3a15.7 15.7 0 001.3 4 8 8 0 01-4.3-4zm3-8h-3a8 8 0 014.3-4c-.6 1.2-1 2.6-1.3 4zM12 20c-.8-1.2-1.5-2.5-1.9-4h3.8c-.4 1.5-1.1 2.8-1.9 4zm2.3-6H9.7a14.7 14.7 0 010-4h4.6a14.7 14.7 0 010 4zm.3 6c.6-1.2 1-2.6 1.3-4h3a8 8 0 01-4.3 4zm1.7-6a16.5 16.5 0 000-4h3.4a8 8 0 010 4h-3.4z"/></svg>Weben, bármely böngészőben</span>
        </div>
      </div>

      <div class="phone-stage" aria-hidden="true">
        <div class="phone">
          <div class="screen">
            <div class="notch"></div>
            <div class="app-top"><b>Minta Gumiszerviz</b><span>Online időpontfoglalás</span></div>
            <div class="app-body">
              <div class="app-card">
                <div class="t">Szezonális gumicsere · 30 perc</div>
                <div class="mini-cal">
                  <i>H</i><i>K</i><i>Sze</i><i>Cs</i><i>P</i><i>Szo</i><i>V</i>
                  <s class="off">6</s><s>7</s><s>8</s><s class="sel">9</s><s>10</s><s>11</s><s class="off">12</s>
                  <s>13</s><s>14</s><s class="off">15</s><s>16</s><s>17</s><s>18</s><s class="off">19</s>
                </div>
                <div class="mini-slots"><s>08:00</s><s>08:30</s><s class="sel">09:30</s><s>10:00</s><s>11:00</s><s>13:30</s><s>14:00</s><s>15:30</s></div>
              </div>
              <div class="app-card"><div class="t">Rendszám</div><div style="color:#6b7280">ABC-123</div></div>
              <div class="mini-btn">Időpont lefoglalása</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="fejlesztes">
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">Egyedi fejlesztés</span>
        <h2>Amire a vállalkozásodnak szüksége van — rövid határidővel</h2>
        <p class="lead">Nem sablont adunk el: a te igényedből indulunk ki. Legyen szó weboldalról, belső rendszerről vagy ügyfeleknek szóló appról, gyorsan eljutunk a működő megoldásig.</p>
      </div>
      <div class="grid three">
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 8h18M8 21h8M12 18v3"/></svg></div>
          <h3>Weboldalak és webes rendszerek</h3>
          <p>Bemutatkozó oldalak, webshopok, admin felületek, ügyfélkapuk — mobilon és számítógépen is kényelmesen használhatóan.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/></svg></div>
          <h3>Appok telefonra és webre</h3>
          <p>Egy fejlesztés, ami iPhone-on, Androidon és böngészőben is fut: foglalás, rendelés, nyilvántartás, ügyféltájékoztatás.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg></div>
          <h3>Rövid határidő</h3>
          <p>Gyors egyeztetés, hamar kipróbálható első változat, és folyamatos finomítás a visszajelzéseid alapján — hónapokig tartó várakozás nélkül.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="appok" class="soft">
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">Első appunk</span>
        <h2>Online időpontfoglaló</h2>
        <p class="lead">Gumiszerviznek, fodrászatnak, rendelőnek, szervíznek — bárkinek, aki időpontra dolgozik. Az ügyfél pár koppintással foglal, te pedig egy helyen látsz mindent.</p>
      </div>

      <div class="product">
        <div class="product-card">
          <span class="badge">Elérhető · élő demó</span>
          <h3>Foglalás éjjel-nappal, telefonálgatás nélkül</h3>
          <p>A vállalkozásod saját nevével, logójával és színeivel. Minden beállítás az admin felületről módosítható — programozás nélkül.</p>
          <ul class="checks">
            <li>Csak a valóban szabad időpontok foglalhatók — ütközés és dupla foglalás nélkül</li>
            <li>Párhuzamos helyek (pl. 2 emelő, 3 szék) és ebédszünet kezelése</li>
            <li>Automatikus vagy kézi jóváhagyás, email visszaigazolással</li>
            <li>Az ügyfél online lemondhatja és a naptárába mentheti</li>
            <li>Saját mezők az űrlapon, pl. rendszám, autó típusa, gumiméret</li>
            <li>Telefonos foglalások rögzítése és gyors keresés az admin felületen</li>
          </ul>
          <div class="cta">
            <a class="btn btn-primary" href="<?= $e($site['demoUrl']) ?>">Élő demó megnyitása</a>
          </div>
          <div class="uses" aria-label="Kinek ajánljuk">
            <span class="chip">Gumiszerviz</span><span class="chip">Autószerviz</span><span class="chip">Fodrászat</span><span class="chip">Kozmetika</span><span class="chip">Rendelő</span><span class="chip">Tanácsadás</span>
          </div>
        </div>

        <div class="grid two" style="margin-top:0">
          <div class="card">
            <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></div>
            <h3>Naptár és szabad helyek</h3>
            <p>Nyitvatartás, szabadságok, ünnepnapok — a rendszer mindig csak azt kínálja, ami tényleg szabad.</p>
          </div>
          <div class="card">
            <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/></svg></div>
            <h3>Automatikus értesítések</h3>
            <p>Visszaigazolás az ügyfélnek, értesítés neked minden új foglalásról és lemondásról.</p>
          </div>
          <div class="card">
            <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <h3>Kevesebb kimaradás</h3>
            <p>Az ügyfél a naptárába menti az időpontot, és ha nem jön, egy kattintással lemondja — így az időpont újra foglalható.</p>
          </div>
          <div class="card">
            <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg></div>
            <h3>Biztonságos</h3>
            <p>Minden cég adatai külön kezelve, védett admin belépés, robotok elleni védelem.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="hogyan">
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">Hogyan működik</span>
        <h2>Három lépésben a saját megoldásodig</h2>
      </div>
      <div class="grid three steps">
        <div class="card step">
          <h3>Egyeztetünk</h3>
          <p>Elmondod, hogyan dolgozol: milyen szolgáltatásaid vannak, mikor vagy nyitva, mire van szükséged.</p>
        </div>
        <div class="card step">
          <h3>Beállítjuk a te arculatoddal</h3>
          <p>Rövid határidővel elkészül a kipróbálható változat a logóddal, színeiddel és tartalmaiddal — és egy saját cím, amit megoszthatsz az ügyfeleiddel.</p>
        </div>
        <div class="card step">
          <h3>Az ügyfeleid használják</h3>
          <p>Weboldalról, Facebookról, QR-kódról nyitják meg, és ha szeretnék, a telefonjuk kezdőképernyőjére teszik.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="soft">
    <div class="wrap">
      <div class="center">
        <span class="eyebrow">Miért így?</span>
        <h2>App, ami nem kér letöltést</h2>
        <p class="lead">A mai böngészők tudják azt, amit régen csak az App Store-os appok: saját ikon, teljes képernyő, gyors indulás.</p>
      </div>
      <div class="grid three">
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/></svg></div>
          <h3>Egy koppintás</h3>
          <p>Nincs regisztráció, nincs letöltés, nincs tárhely-gond a telefonon. A link azonnal működik.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg></div>
          <h3>Mindig friss</h3>
          <p>A módosítások azonnal mindenkinél megjelennek — nincs frissítés-jóváhagyásra várás.</p>
        </div>
        <div class="card">
          <div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="14" height="11" rx="2"/><rect x="16" y="8" width="6" height="12" rx="1.5"/><path d="M6 19h6"/></svg></div>
          <h3>Minden eszközön</h3>
          <p>Ugyanaz az app iPhone-on, Androidon, tableten és számítógépen — egyformán jól.</p>
        </div>
      </div>
    </div>
  </section>

  <?php if ($hasContact): ?>
  <section id="kapcsolat">
    <div class="wrap">
      <div class="contact">
        <h2>Egyedi weboldalt vagy appot szeretnél?</h2>
        <p>Írj vagy hívj — megbeszéljük, mire van szüksége a vállalkozásodnak, és rövid határidővel elkészítjük.</p>
        <div class="cta" style="justify-content:center">
          <?php if ($site['email'] !== ''): ?><a class="btn" href="mailto:<?= $e($site['email']) ?>"><?= $e($site['email']) ?></a><?php endif; ?>
          <?php if ($site['phone'] !== ''): ?><a class="btn btn-ghost" href="tel:<?= $e(preg_replace('/[^0-9+]/', '', $site['phone'])) ?>"><?= $e($site['phone']) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>

<footer>
  <div class="wrap">
    <span>© <?= date('Y') ?> <?= $e($site['name']) ?></span>
    <span class="footer-links">
      <a href="<?= $e($site['demoUrl']) ?>">Időpontfoglaló demó</a>
      <a href="/adatkezeles/">Adatkezelési tájékoztató</a>
      <button type="button" class="footer-btn" onclick="window.myaiConsent && myaiConsent.open()">Süti-beállítások</button>
    </span>
  </div>
</footer>

</body>
</html>
