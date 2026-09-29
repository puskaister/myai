<?php
// my-ai.hu főoldal. A közös fejléc, menü, lábléc és stílus: _inc/layout.php és
// assets/site.css; az appok adatai: _inc/apps.php.
declare(strict_types=1);

require __DIR__ . '/_inc/layout.php';

page_start(
    'my-ai.hu – Egyedi weboldalak és appok, rövid határidővel',
    'Egyedi webes és alkalmazásfejlesztés rövid határidővel. A vállalkozásodra szabott appok, amelyek telefonon és weben is működnek — App Store nélkül. Kész appjaink: online időpontfoglaló, Évfordulók, látogatószámláló.',
    '',
    ['title' => 'my-ai.hu – Saját app a vállalkozásodnak', 'description' => 'Egyedi weboldalak és appok rövid határidővel — telefonon és weben, a vállalkozásod arculatával.']
);
?>
  <section class="hero">
    <div class="wrap">
      <div>
        <span class="eyebrow">Egyedi web- és appfejlesztés</span>
        <h1>Saját app a vállalkozásodnak, <span class="grad">telefonon és weben.</span></h1>
        <p class="lead">Egyedi webes és alkalmazásigényeket fejlesztünk, rövid határidővel, a te arculatoddal és a te működésedre szabva. Az ügyfeleid böngészőből nyitják meg, vagy egy koppintással a telefonjuk kezdőképernyőjére teszik, App Store és letöltés nélkül.</p>
        <div class="cta">
          <a class="btn btn-primary" href="<?= e(SITE['demoUrl']) ?>">Próbáld ki az időpontfoglalót →</a>
          <a class="btn btn-ghost" href="#fejlesztes">Egyedi fejlesztés</a>
        </div>
        <div class="platforms" aria-label="Támogatott eszközök">
          <span class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.4 12.6c0-2.4 2-3.6 2.1-3.7-1.2-1.7-3-1.9-3.6-2-1.5-.2-3 .9-3.8.9-.8 0-2-.9-3.3-.8-1.7 0-3.3 1-4.2 2.5-1.8 3.1-.5 7.7 1.3 10.2.9 1.2 1.9 2.6 3.2 2.6 1.3-.1 1.8-.8 3.3-.8s2 .8 3.3.8c1.4 0 2.3-1.3 3.1-2.5 1-1.4 1.4-2.8 1.4-2.9 0 0-2.8-1.1-2.8-4.3zM14 5.3c.7-.8 1.2-2 1-3.2-1 .1-2.3.7-3 1.5-.7.8-1.2 2-1.1 3.1 1.2.1 2.4-.6 3.1-1.4z"/></svg>iPhone és iPad</span>
          <span class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.6 9.5l1.7-3a.4.4 0 00-.7-.4l-1.8 3A10.6 10.6 0 0012 8.2c-1.7 0-3.3.3-4.8 1L5.4 6.1a.4.4 0 00-.7.4l1.7 3C3.6 11 1.7 13.8 1.5 17h21c-.2-3.2-2.1-6-4.9-7.5zM7 14.4a1 1 0 110-2 1 1 0 010 2zm10 0a1 1 0 110-2 1 1 0 010 2z"/></svg>Android</span>
          <span class="chip"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm6.9 6h-3a15.7 15.7 0 00-1.3-4 8 8 0 014.3 4zM12 4c.8 1.2 1.5 2.5 1.9 4h-3.8c.4-1.5 1.1-2.8 1.9-4zM4.3 14a8 8 0 010-4h3.4a16.5 16.5 0 000 4H4.3zm.8 2h3a15.7 15.7 0 001.3 4 8 8 0 01-4.3-4zm3-8h-3a8 8 0 014.3-4c-.6 1.2-1 2.6-1.3 4zM12 20c-.8-1.2-1.5-2.5-1.9-4h3.8c-.4 1.5-1.1 2.8-1.9 4zm2.3-6H9.7a14.7 14.7 0 010-4h4.6a14.7 14.7 0 010 4zm.3 6c.6-1.2 1-2.6 1.3-4h3a8 8 0 01-4.3 4zm1.7-6a16.5 16.5 0 000-4h3.4a8 8 0 010 4h-3.4z"/></svg>Weben, bármely böngészőben</span>
        </div>
      </div>

      <?php mockup_booking(); ?>
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
        <span class="eyebrow">Appjaink</span>
        <h2>Kész appok, a te arculatodra szabva</h2>
        <p class="lead">Telefonon és weben is működnek, App Store nélkül. Nézd meg részletesen, vagy próbáld ki az élő demót.</p>
      </div>
<?php apps_grid(); ?>
      <div class="center" style="margin-top:28px"><a class="btn btn-ghost" href="/appok/">Összes app →</a></div>
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

<?php
contact_section();
page_end();
