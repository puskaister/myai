<?php
// Rólunk és impresszum (my-ai.hu/rolunk/): a cég bemutatása és a szolgáltató kötelező adatai (Ekertv. 4. §).
declare(strict_types=1);

require dirname(__DIR__) . '/_inc/layout.php';

$provider = [
    'Cégnév'                  => 'Immobilis Partners Kft.',
    'Székhely'                => '1238 Budapest, Molnár utca 65/b',
    'Cégjegyzékszám'          => 'Cg. 01-09-684414',
    'Nyilvántartó cégbíróság' => 'Fővárosi Törvényszék Cégbírósága',
    'Adószám'                 => '11941736-2-43',
    'E-mail'                  => SITE['email'],
    'Telefon'                 => SITE['phone'],
    'Tárhelyszolgáltató'      => 'DiMa.hu Kft., 4032 Debrecen, Békessy Béla u. 9. C. ép. 3. em. 10.; info@dima.hu; +36 52 322 121; www.dima.hu',
];

page_start('Rólunk és impresszum – my-ai.hu', 'Az Immobilis Partners Kft. 1999 óta működő informatikai szolgáltató. Bemutatkozás, elérhetőségek és a szolgáltató adatai.');
?>
  <style>
    .about { max-width: 780px; }
    .about h2 { margin-top: 36px; }
    .about table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
    .about th, .about td { text-align: left; vertical-align: top; padding: 9px 10px; border-bottom: 1px solid rgba(127, 127, 127, .25); }
    .about th { width: 34%; font-weight: 600; opacity: .75; }
    @media (max-width: 560px) { .about th { width: 40%; } }
  </style>
  <section class="page-head">
    <div class="wrap">
      <span class="eyebrow">Rólunk</span>
      <h1>Kik vagyunk?</h1>
      <p class="lead">Az Immobilis Partners Kft. 1999 óta működő budapesti informatikai szolgáltató. A my-ai.hu a cégünk weboldal- és appfejlesztési szolgáltatása.</p>
    </div>
  </section>

  <section style="padding-top:8px">
    <div class="wrap about">
      <h2>Mivel foglalkozunk?</h2>
      <p>Fő profilunk az informatikai szolgáltatás: kis- és középvállalkozásoknak üzemeltetünk rendszereket, és készítünk egyedi weboldalakat, webes rendszereket és appokat. Több mint huszonöt év alatt megtanultuk, hogy egy vállalkozásnak nem a legbonyolultabb megoldás kell, hanem az, amelyik megbízhatóan működik, és amit szívesen használnak.</p>
      <p>A saját termékeink is ebből születtek: az <a href="/appok/">appjaink</a> (például az online időpontfoglaló), és a <a href="https://followup.hu/hu/">Followup</a>, az egyszerű ügyfél-, számla- és teendőkezelő kisvállalkozóknak.</p>

      <h2>Hogyan dolgozunk?</h2>
      <ul>
        <li><b>A te igényedből indulunk ki,</b> nem sablont adunk el.</li>
        <li><b>Rövid határidővel</b> jutunk el a működő megoldásig.</li>
        <li><b>Az elkészült rendszert sem hagyjuk magára:</b> igény szerint üzemeltetjük és karbantartjuk.</li>
        <li><b>Magyarul, közvetlenül</b> beszélünk, e-mailben és telefonon.</li>
      </ul>

      <h2>Kapcsolat</h2>
      <p>E-mail: <a href="mailto:<?= e(SITE['email']) ?>"><?= e(SITE['email']) ?></a><br>
         Telefon: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', SITE['phone'])) ?>"><?= e(SITE['phone']) ?></a><br>
         LinkedIn: <a href="<?= e(SITE['linkedin']) ?>" rel="noopener" target="_blank">Immobilis Partners Kft.</a></p>

      <h2 id="impresszum">Impresszum</h2>
      <table>
<?php foreach ($provider as $k => $v): ?>
        <tr><th><?= e($k) ?></th><td><?= e($v) ?></td></tr>
<?php endforeach; ?>
      </table>
      <p>Panaszkezelés és vitarendezés: lásd az <a href="/aszf/">Általános Szerződési Feltételeket</a>. Személyes adatok: <a href="/adatkezeles/">Adatkezelési tájékoztató</a>.</p>
    </div>
  </section>
<?php
page_end();
