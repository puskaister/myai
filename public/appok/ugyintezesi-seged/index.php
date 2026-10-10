<?php
// Ügyintézési Segéd – részletes oldal (my-ai.hu/appok/ugyintezesi-seged/).
// Fizetős szolgáltatás (havidíj); a megrendelés az /ugyintezes/ app API-jára megy,
// az előfizetéseket az /ugyintezes/admin/ felületen kezeled. Az élő demó: /ugyintezes/.
// A beépítés módja szándékosan nem szerepel nyilvánosan — a beépítést mi végezzük.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

page_start('Ügyintézési Segéd – ügyfélszolgálati chatbot a weboldaladra – my-ai.hu', 'Chatbot a weboldaladra, ami a te kérdéseidből és válaszaidból felel — éjjel-nappal, AI-díj nélkül. 5 000 Ft + ÁFA / hó, a beállítást és a beépítést mi végezzük.', 'appok');
app_hero('ugyintezesi-seged', '5 000 Ft + ÁFA / hó · nincs AI-díj',
    'Éjjel-nappal válaszol az érdeklődők kérdéseire a weboldaladon: árajánlat, határidő, szolgáltatások, elérhetőség. Kulcsszavak alapján, a te válaszaidból felel — pontosan azt mondja, amit te írtál bele.',
    [['#megrendeles', 'Megrendelem →'], ['/ugyintezes/', 'Demó kipróbálása']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Mit tud?</h2>
      <div style="margin-top:22px">
        <ul class="checks feature-list">
          <li>Lépésenkénti, számozott válaszok és felsorolások</li>
          <li>Ékezet nélkül és szótővel is érti a kérdést („mennyibe kerul”, „kell app store?”)</li>
          <li>Gyorsgombok a leggyakoribb témákhoz, kapcsolódó témák ajánlása</li>
          <li>Ha nem érti a kérdést, felajánlja a témákat és az élő ügyfélszolgálat elérhetőségét</li>
          <li>Tudásbázis-szerkesztő: témák, kulcsszavak és válaszok programozás nélkül</li>
          <li>Bármely weboldalon működik (WordPress, Wix, Shopify, saját oldal) — a beépítést mi végezzük</li>
        </ul>
      </div>
      <div class="uses" aria-label="Kinek ajánljuk">
        <span class="chip">Ügyfélszolgálat</span><span class="chip">Önkormányzat</span><span class="chip">Közüzem</span><span class="chip">Iroda</span><span class="chip">Biztosító</span><span class="chip">Iskola</span>
      </div>
    </div>
  </section>

  <section id="hogyan-mukodik">
    <div class="wrap">
      <h2>Hogyan működik?</h2>
      <div class="grid three">
        <div class="card"><h3>Megadod a témákat</h3><p>Minden témához pár kulcsszó és a válasz: lépések, szükséges iratok, határidő, díj, elérhetőség.</p></div>
        <div class="card"><h3>Az ügyfél kérdez</h3><p>A bot a kérdésben lévő kulcsszavak alapján kiválasztja a legjobban illő útmutatót.</p></div>
        <div class="card"><h3>Pontos választ kap</h3><p>Mindig azt mondja, amit te írtál bele — nem talál ki semmit, és nincs AI-díj.</p></div>
      </div>
      <p class="meta" style="margin-top:18px">A demóban a my-ai.hu saját kérdései és válaszai szerepelnek. Küldd el a sajátjaidat, és beállítjuk a weboldaladra.</p>
    </div>
  </section>

  <section class="soft" id="megrendeles">
    <div class="wrap">
      <div class="product">
        <div class="product-card">
          <span class="badge">Havidíjas szolgáltatás</span>
          <h3><span data-price="net">5 000 Ft</span> + ÁFA / hó</h3>
          <p>Bruttó <span data-price="gross">6 350 Ft</span> havonta, díjbekérő alapján, átutalással. Havonta lemondható.</p>
          <ul class="checks">
            <li>A tudásbázist a te kérdéseidből és válaszaidból mi állítjuk össze</li>
            <li>A chat-buborékot mi építjük be a weboldaladba</li>
            <li>Saját név és szín, a weboldalad stílusához igazítva</li>
            <li>A témák és válaszok frissítése kérésre</li>
            <li>Nincs AI-díj, nincs forgalomarányos költség</li>
          </ul>
          <p class="meta">A chat az első befizetés után indul. Lejárat előtt emailben emlékeztetünk.</p>
        </div>

        <form class="card form" id="order-form" novalidate>
          <h3 style="margin:0">Megrendelés</h3>
          <div class="grid two" style="margin-top:0;gap:12px">
            <label>Cég / vállalkozás neve *<input name="company" required maxlength="150" autocomplete="organization"></label>
            <label>Kapcsolattartó neve *<input name="contact_name" required maxlength="120" autocomplete="name"></label>
            <label>Email *<input name="email" type="email" required maxlength="190" autocomplete="email"></label>
            <label>Telefon<input name="phone" type="tel" maxlength="60" autocomplete="tel"></label>
          </div>
          <label>Weboldal címe (ahova a chat kerül)<input name="website" maxlength="255" placeholder="https://"></label>
          <div class="grid two" style="margin-top:0;gap:12px">
            <label>Számlázási név<input name="billing_name" maxlength="150"></label>
            <label>Adószám<input name="tax_number" maxlength="40"></label>
          </div>
          <label>Számlázási cím<input name="billing_address" maxlength="255" autocomplete="street-address"></label>
          <label>Miben segítsen a chat? (nem kötelező)<textarea name="message" rows="3" maxlength="3000" placeholder="pl. a leggyakoribb kérdések: árak, nyitvatartás, határidők…"></textarea></label>
          <input name="website_url_hp" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
          <label class="check" style="display:flex;gap:10px;align-items:flex-start;font-weight:500">
            <input type="checkbox" name="accept" style="width:20px;height:20px;margin-top:3px">
            <span>Megrendelem az Ügyintézési Segéd havidíjas szolgáltatást (<span data-price="net">5 000 Ft</span> + ÁFA / hó, díjbekérő alapján, átutalással, havonta lemondható). Az <a href="/aszf/" target="_blank">ÁSZF-et</a> elfogadom, az <a href="/adatkezeles/" target="_blank">adatkezelési tájékoztatót</a> megismertem.</span>
          </label>
          <p class="order-msg" aria-live="polite" style="margin:0"></p>
          <button class="btn btn-primary" type="submit">Megrendelés elküldése</button>
        </form>
      </div>
    </div>
  </section>

  <script>
    // Megrendelés az /ugyintezes/ app API-jára; az aktuális díj is onnan jön.
    (function () {
      var form = document.getElementById('order-form');
      var msg = form.querySelector('.order-msg');
      fetch('/ugyintezes/api/?r=price').then(function (r) { return r.ok ? r.json() : null; }).then(function (p) {
        if (!p) return;
        var fmt = function (n) { return Number(n).toLocaleString('hu-HU') + ' Ft'; };
        document.querySelectorAll('[data-price=net]').forEach(function (e) { e.textContent = fmt(p.net); });
        document.querySelectorAll('[data-price=gross]').forEach(function (e) { e.textContent = fmt(p.gross); });
      }).catch(function () {});
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var data = {};
        new FormData(form).forEach(function (v, k) { data[k] = v; });
        data.accept = form.accept.checked;
        msg.className = 'order-msg'; msg.textContent = 'Küldés…';
        var btn = form.querySelector('button[type=submit]'); btn.disabled = true;
        fetch('/ugyintezes/api/?r=order', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' }, body: JSON.stringify(data) })
          .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            btn.disabled = false;
            if (res.ok) {
              form.reset();
              msg.className = 'order-msg ok';
              msg.textContent = 'Köszönjük, megkaptuk a megrendelést! Visszaigazolást küldtünk emailben, és hamarosan jelentkezünk a részletekkel.';
            } else {
              msg.className = 'order-msg alert';
              msg.textContent = (res.j && res.j.error) || 'Nem sikerült elküldeni. Kérjük, próbáld újra, vagy írj az info@my-ai.hu címre.';
            }
          })
          .catch(function () { btn.disabled = false; msg.className = 'order-msg alert'; msg.textContent = 'Nincs kapcsolat. Kérjük, próbáld újra, vagy írj az info@my-ai.hu címre.'; });
      });
    })();
  </script>


<?php
contact_section('Kérdésed van a szolgáltatással kapcsolatban?', 'Írj vagy hívj — segítünk összeállítani a témákat, és megmutatjuk, hogyan működne a te weboldaladon.');
page_end();
