<?php
// Online időpontfoglaló – részletes oldal (my-ai.hu/appok/idopontfoglalo/).
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

page_start('Online időpontfoglaló – my-ai.hu', 'Online időpontfoglaló vállalkozásoknak, egyszeri 30 000 Ft + ÁFA: az ügyfél pár koppintással foglal, csak a szabad időpontok választhatók, email visszaigazolás, admin felület. Saját arculattal, telefonon is.', 'appok');
app_hero('idopontfoglalo', 'Egyszeri 30 000 Ft + ÁFA · vállalkozásoknak',
    'Gumiszerviznek, fodrászatnak, rendelőnek, szervíznek — bárkinek, aki időpontra dolgozik. Az ügyfél pár koppintással foglal, te pedig egy helyen látsz mindent.',
    [[SITE['demoUrl'], 'Élő demó megnyitása →'], ['#kapcsolat', 'Ajánlatot kérek']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Foglalás éjjel-nappal, telefonálgatás nélkül</h2>
      <p class="lead" style="margin-top:12px">A vállalkozásod saját nevével, logójával és színeivel. Minden beállítás az admin felületről módosítható — programozás nélkül.</p>
      <div style="margin-top:22px">
      <ul class="checks feature-list">
        <li>Csak a valóban szabad időpontok foglalhatók — ütközés és dupla foglalás nélkül</li>
        <li>Párhuzamos helyek (pl. 2 emelő, 3 szék) és ebédszünet kezelése</li>
        <li>Automatikus vagy kézi jóváhagyás, email visszaigazolással</li>
        <li>Az ügyfél online lemondhatja és a naptárába mentheti</li>
        <li>Saját mezők az űrlapon, pl. rendszám, autó típusa, gumiméret</li>
        <li>Telefonos foglalások rögzítése és gyors keresés az admin felületen</li>
      </ul>
      </div>
      <div class="uses" aria-label="Kinek ajánljuk">
        <span class="chip">Gumiszerviz</span><span class="chip">Autószerviz</span><span class="chip">Fodrászat</span><span class="chip">Kozmetika</span><span class="chip">Rendelő</span><span class="chip">Tanácsadás</span>
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <h2>Mit nyersz vele?</h2>
      <div class="grid four">
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
  </section>

  <section class="soft">
    <div class="wrap">
      <h2>Hogyan kapod meg?</h2>
      <ol class="steps-list">
        <li><strong>Kipróbálod</strong> az élő demót — ügyfélként és (kérésre) adminként is.</li>
        <li><strong>Beállítjuk a te arculatoddal:</strong> név, logó, színek, szolgáltatások, nyitvatartás, extra mezők (pl. rendszám).</li>
        <li><strong>Megkapod a saját címedet</strong> (a my-ai.hu-n vagy a saját domaineden), amit megoszthatsz a weboldaladon, Facebookon, QR-kódon.</li>
      </ol>
      <div class="card" style="margin-top:24px;max-width:520px">
        <h3>Ár</h3>
        <p style="font-size:1.6rem;font-weight:800;margin:6px 0 2px">30 000 Ft + ÁFA</p>
        <p class="muted" style="margin:0 0 12px">egyszeri díj (bruttó 38 100 Ft)</p>
        <a class="btn btn-primary" href="#kapcsolat">Ajánlatot kérek</a>
      </div>
    </div>
  </section>

<?php
contact_section('Szeretnél saját időpontfoglalót?', 'Írj vagy hívj — rövid határidővel beállítjuk a vállalkozásod arculatával.');
page_end();
