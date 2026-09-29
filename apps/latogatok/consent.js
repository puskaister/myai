// Süti- / hozzájárulás-sáv a látogatószámlálóhoz. Beillesztés (a t.js elé):
//   <script src="https://my-ai.hu/stats/consent.js" data-privacy="/adatkezeles" defer></script>
// A döntést a localStorage-ban tárolja ('myai_consent' = yes | no), és egy
// 'myai-consent' eseménnyel szól a t.js-nek. Módosítás bármikor:
//   <button onclick="myaiConsent.open()">Süti-beállítások</button>
(function () {
  'use strict';
  if (window.myaiConsent) return;

  var script = document.currentScript;
  var privacyUrl = (script && script.getAttribute('data-privacy')) || '/adatkezeles';
  var KEY = 'myai_consent';
  var bar = null;

  function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }

  function decide(value) {
    try {
      localStorage.setItem(KEY, value);
      if (value === 'no') localStorage.removeItem('myai_vid'); // visszavonáskor az azonosító is törlődik
    } catch (e) { /* privát mód: a döntés csak erre az oldalbetöltésre él */ }
    close();
    window.dispatchEvent(new CustomEvent('myai-consent', { detail: value }));
  }

  function close() {
    if (bar) { bar.remove(); bar = null; }
  }

  function open() {
    if (bar) return;
    if (!document.getElementById('myai-consent-css')) {
      var css = document.createElement('style');
      css.id = 'myai-consent-css';
      css.textContent =
        '.myai-consent{position:fixed;left:16px;right:16px;bottom:16px;z-index:2147483001;max-width:720px;margin:0 auto;'
        + 'background:#fff;color:#0f172a;border:1px solid #e6e8f0;border-radius:16px;padding:18px 18px 16px;'
        + 'box-shadow:0 12px 40px rgba(15,23,42,.18);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
        + '.myai-consent p{margin:0 0 14px}.myai-consent strong{display:block;font-size:16px;margin-bottom:4px}'
        + '.myai-consent a{color:#4f46e5}'
        + '.myai-consent .b{display:flex;gap:10px;flex-wrap:wrap}'
        + '.myai-consent button{flex:1 1 160px;min-height:44px;border-radius:10px;font:inherit;font-weight:650;cursor:pointer;border:1px solid #4f46e5}'
        + '.myai-consent .y{background:#4f46e5;color:#fff}.myai-consent .n{background:#fff;color:#4f46e5}'
        + '.myai-consent button:focus-visible{outline:3px solid #4f46e5;outline-offset:2px}'
        + '@media (prefers-color-scheme:dark){.myai-consent{background:#151a26;color:#eef1f7;border-color:#242a38}'
        + '.myai-consent a{color:#a5a2ff}.myai-consent .n{background:#151a26;color:#c7c5ff;border-color:#8b87ff}'
        + '.myai-consent .y{background:#8b87ff;color:#0b0e17;border-color:#8b87ff}}';
      document.head.appendChild(css);
    }
    bar = document.createElement('div');
    bar.className = 'myai-consent';
    bar.setAttribute('role', 'region');
    bar.setAttribute('aria-label', 'Adatkezelési hozzájárulás');
    bar.innerHTML =
      '<p><strong>Látogatottsági statisztika</strong>'
      + 'Hogy lássuk, mi hasznos az oldalon, mérnénk a látogatást: IP-cím, böngésző, meglátogatott oldalak, eltöltött idő és kattintások. '
      + 'Ehhez egy azonosítót tárolunk a böngésződben. Ehhez a hozzájárulásodat kérjük — nélküle is teljes értékűen használhatod az oldalt. '
      + '<a href="' + privacyUrl.replace(/"/g, '&quot;') + '">Adatkezelési tájékoztató</a></p>'
      + '<div class="b"><button type="button" class="n">Nem fogadom el</button><button type="button" class="y">Elfogadom</button></div>';
    bar.querySelector('.y').addEventListener('click', function () { decide('yes'); });
    bar.querySelector('.n').addEventListener('click', function () { decide('no'); });
    document.body.appendChild(bar);
  }

  window.myaiConsent = { open: open, get: get };

  function init() { if (get() === null) open(); }
  if (document.body) init();
  else document.addEventListener('DOMContentLoaded', init);
})();
