<?php
// Followup – rövid ismertető (my-ai.hu/appok/followup/). A részletes bemutató, a
// megrendelés és a 45 napos ingyenes próba a followup.my-ai.hu oldalon van.
declare(strict_types=1);

require dirname(__DIR__, 2) . '/_inc/layout.php';

const FOLLOWUP_URL = 'https://followup.my-ai.hu/';
page_start('Followup – számlák, kintlévőségek és érdeklődők egy helyen – my-ai.hu', 'Minden reggel egyetlen lista: mit fizess ki, kit hívj fel és miért. A felszólító leveleket is megírja, te csak jóváhagyod. Próbáld ki 45 napig ingyen.', 'appok');
app_hero('followup', 'Kis- és középvállalkozásoknak · 45 napig ingyen kipróbálható',
    'Számlák a postafiókban, határidők fejben, tartozások a bankszámlakivonaton, ígéretek egy telefonhívásban. A Followup mindezt egy helyre teszi, és minden reggel megmondja, mivel kezdd.',
    [[FOLLOWUP_URL . 'proba.php', 'Kipróbálom 45 napig ingyen'], [FOLLOWUP_URL, 'Részletek a Followup oldalán →']]);
?>
  <section class="soft">
    <div class="wrap">
      <h2>Mit csinál a Followup?</h2>
      <div style="margin-top:22px">
        <ul class="checks feature-list">
          <li><strong>Fizetendő számlák:</strong> a határidő előtt szól, a lejártat pirossal mutatja, a havonta ismétlődőt magától előveszi</li>
          <li><strong>Kintlévőségek:</strong> ki tartozik és mióta, ki ígért fizetést és mikorra — és ha az ígért nap elmúlt, szól, hogy most hívd</li>
          <li><strong>Felszólító levelek:</strong> emlékeztető, felszólítás, utolsó felszólítás a késés szerint — megírja, te átnézed és elküldöd</li>
          <li><strong>NAV- és banki határidők:</strong> járulék, áfa, hitel, lízing összeggel és határidővel a napi listán</li>
          <li><strong>Érdeklődők:</strong> minden megkeresés egy listán, utánkövetéssel; az ügyfél online maga foglal időpontot</li>
          <li><strong>Reggeli email:</strong> a napi teendők minden reggel a postafiókodban, belépés nélkül is</li>
        </ul>
      </div>
      <p class="lead" style="margin-top:18px">Semmi nem megy ki a tudtod nélkül: minden levél csak a jóváhagyásod után indul el.</p>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="grid three">
        <div class="card"><h3>Próbáld ki ingyen</h3><p>45 napig, mintaadatokkal, bankkártya nélkül. Egy perc a regisztráció.</p><p><a class="btn btn-primary" href="<?= e(FOLLOWUP_URL) ?>proba.php">Próbaverzió indítása</a></p></div>
        <div class="card"><h3>Maradhat nálunk</h3><p>Tárhellyel, domainnal, üzemeltetéssel, frissítéssel nem kell bajlódnod — mi gondoskodunk róla.</p></div>
        <div class="card"><h3>Vagy elviheted</h3><p>A saját tárhelyedre, a próbaverzió összes adatával. A telepítést kérésre elvégezzük.</p></div>
      </div>
      <p style="margin-top:22px"><a class="btn btn-ghost" href="<?= e(FOLLOWUP_URL) ?>">Árak, képernyőképek és bemutatókérés a Followup oldalán →</a></p>
    </div>
  </section>

<?php
contact_section('Kérdésed van a Followupról?', 'Írj vagy hívj — megmutatjuk, hogyan működne a te cégednél.');
page_end();
