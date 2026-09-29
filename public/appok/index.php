<?php
// Appok (my-ai.hu/appok/): minden app kártyán — kép, cím, pár szó, Tovább.
declare(strict_types=1);

require dirname(__DIR__) . '/_inc/layout.php';

page_start('Appok – my-ai.hu', 'Kész appok vállalkozásoknak és magánszemélyeknek: online időpontfoglaló, Évfordulók emlékeztető, látogatószámláló. Telefonon és weben is működnek.', 'appok');
?>
  <section class="page-head">
    <div class="wrap">
      <span class="eyebrow">Appjaink</span>
      <h1>Appok, amik telefonon és weben is működnek</h1>
      <p class="lead">Kész megoldások, a te arculatodra szabva. Válassz egyet, és nézd meg részletesen — vagy kérj egyedi fejlesztést.</p>
    </div>
  </section>

  <section style="padding-top:8px">
    <div class="wrap">
<?php apps_grid(); ?>
    </div>
  </section>

<?php
contact_section('Nem találod, amit keresel?', 'Egyedi appot is készítünk, rövid határidővel. Írj vagy hívj, és megbeszéljük.');
page_end();
