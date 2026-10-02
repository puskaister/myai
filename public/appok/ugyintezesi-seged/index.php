<?php
// Ügyintézési Segéd – részletes oldal (my-ai.hu/appok/ugyintezesi-seged/).
// Az élő demó a /ugyintezes/ címen fut: egyetlen statikus HTML oldal,
// szerver, adatbázis és AI-szolgáltatás nélkül (nincs futási költsége).
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

page_start('Ügyintézési Segéd – ingyenes ügyfélszolgálati chatbot – my-ai.hu', 'Ügyfélszolgálati chatbot ügyintézési útmutató válaszokkal: időpontfoglalás, szükséges dokumentumok, határidők, díjak. Szabályalapú, nincs AI-díj és nincs havi költség.', 'appok');
app_hero('ugyintezesi-seged', 'Ingyenes · nincs futási költség',
    'Az ügyfelek kérdésére lépésről lépésre elmondja, mit, hol és hogyan kell intézni. Kulcsszavak alapján válaszol a saját útmutatóidból, így nincs AI-díj és nincs havi költség.',
    [['/ugyintezes/', 'Demó kipróbálása →'], ['#hogyan-mukodik', 'Hogyan működik?']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Mit tud?</h2>
      <div style="margin-top:22px">
        <ul class="checks feature-list">
          <li>Lépésenkénti, számozott ügyintézési útmutatók és dokumentumlisták</li>
          <li>Ékezet nélkül és szótővel is érti a kérdést („koltozes”, „mit vigyek”)</li>
          <li>Gyorsgombok a leggyakoribb témákhoz, kapcsolódó témák ajánlása</li>
          <li>Ha nem érti a kérdést, felajánlja a témákat és az élő ügyfélszolgálat elérhetőségét</li>
          <li>Tudásbázis-szerkesztő: témák, kulcsszavak és válaszok programozás nélkül</li>
          <li>Egyetlen fájl: bármely tárhelyen fut, szerver és adatbázis nélkül</li>
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
        <div class="card"><h3>Pontos választ kap</h3><p>Mindig azt mondja, amit te írtál bele — nem talál ki semmit, és nem kerül pénzbe.</p></div>
      </div>
      <p class="meta" style="margin-top:18px">A demó mintaválaszai helyőrzőket tartalmaznak ([telefonszám], [link]). Küldd el a saját útmutatóidat, és beépítjük, vagy a weboldaladra illesztjük.</p>
    </div>
  </section>

<?php
contact_section('Saját útmutatóiddal szeretnéd használni?', 'Írj vagy hívj — betöltjük a tudásbázist, és beillesztjük a weboldaladra.');
page_end();
