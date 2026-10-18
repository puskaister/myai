/*
 * Időpontfoglaló – beépítés bármely weboldalba (WordPress, Wix, saját oldal…).
 * A pontos kódot az admin felület Beállítások fülén találod.
 *
 * 1) Lebegő gomb a jobb alsó sarokban, kattintásra felugró foglalóval
 *    (a </body> elé):
 *      <script src="https://a-domained.hu/foglalas/widget.js" defer></script>
 *
 * 2) Az oldalba ágyazva, egy adott helyen (ahova a foglalót szeretnéd):
 *      <div id="idopontfoglalo"></div>
 *      <script src="https://a-domained.hu/foglalas/widget.js" data-hely="#idopontfoglalo" defer></script>
 *
 * Beállítások: data-szoveg (a gomb felirata), data-szin (a gomb színe, pl. #e2552e),
 * data-pozicio ("bal" vagy "jobb"), data-hely (CSS-szelektor: beágyazás gomb helyett).
 * Több gomb is nyithatja: bármely elemre tett data-idopontfoglalo attribútum.
 */
(function () {
  var s = document.currentScript;
  if (!s || window.__idopontWidget) return;
  window.__idopontWidget = true;

  var base = new URL('.', s.src);
  var url = new URL('./?beagyazva=1', base).href;
  var szin = s.getAttribute('data-szin') || '';
  if (!/^#[0-9a-f]{3,8}$/i.test(szin)) szin = '#111827';
  var szoveg = s.getAttribute('data-szoveg') || 'Időpontfoglalás';
  var oldal = s.getAttribute('data-pozicio') === 'bal' ? 'left' : 'right';
  var hely = s.getAttribute('data-hely');

  function frame(extra) {
    var f = document.createElement('iframe');
    f.src = url;
    f.title = szoveg;
    f.setAttribute('loading', 'lazy');
    f.setAttribute('allow', 'clipboard-write');
    f.style.cssText = 'width:100%;border:0;display:block;' + (extra || '');
    return f;
  }

  // A foglaló jelzi a magasságát, így beágyazva nem kell görgetősáv.
  window.addEventListener('message', function (e) {
    if (e.origin !== base.origin || !e.data || e.data.idopont !== 'height') return;
    document.querySelectorAll('iframe.idopont-inline').forEach(function (f) {
      if (f.contentWindow === e.source) f.style.height = Math.max(420, Number(e.data.h) || 0) + 'px';
    });
  });

  function start() {
    // 2) beágyazás
    if (hely) {
      var hova = document.querySelector(hely);
      if (!hova) return;
      var f = frame('height:720px;');
      f.className = 'idopont-inline';
      hova.appendChild(f);
      return;
    }

    // 1) lebegő gomb + felugró ablak (shadow DOM: az oldal stílusai nem zavarják)
    var host = document.createElement('div');
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var css = document.createElement('style');
    css.textContent =
      '.btn{position:fixed;bottom:20px;' + oldal + ':20px;z-index:2147482000;display:flex;align-items:center;gap:8px;border:0;cursor:pointer;' +
      'background:' + szin + ';color:#fff;font:600 15px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:14px 18px;border-radius:999px;box-shadow:0 8px 24px rgba(0,0,0,.25)}' +
      '.btn svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}' +
      '.back{position:fixed;inset:0;z-index:2147482001;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:20px}' +
      '.back.open{display:flex}.box{position:relative;width:100%;max-width:560px;height:min(820px,100%);background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.35)}' +
      '.box iframe{width:100%;height:100%;border:0;display:block}' +
      '.x{position:absolute;top:10px;right:10px;z-index:1;width:36px;height:36px;border-radius:50%;border:0;background:rgba(0,0,0,.55);color:#fff;font-size:22px;line-height:1;cursor:pointer}' +
      '@media (max-width:600px){.back{padding:0}.box{max-width:none;height:100%;border-radius:0}}';
    var btn = document.createElement('button');
    btn.className = 'btn';
    btn.type = 'button';
    btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>';
    btn.appendChild(document.createTextNode(szoveg));
    var back = document.createElement('div');
    back.className = 'back';
    back.setAttribute('role', 'dialog');
    back.setAttribute('aria-modal', 'true');
    back.setAttribute('aria-label', szoveg);
    var box = document.createElement('div');
    box.className = 'box';
    var x = document.createElement('button');
    x.className = 'x';
    x.type = 'button';
    x.setAttribute('aria-label', 'Bezárás');
    x.textContent = '×';
    box.appendChild(x);
    back.appendChild(box);
    root.appendChild(css);
    root.appendChild(btn);
    root.appendChild(back);
    document.body.appendChild(host);

    var loaded = false;
    function open() {
      if (!loaded) { box.appendChild(frame()); loaded = true; }
      back.classList.add('open');
      x.focus();
    }
    function close() { back.classList.remove('open'); btn.focus(); }
    btn.addEventListener('click', open);
    x.addEventListener('click', close);
    back.addEventListener('click', function (e) { if (e.target === back) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && back.classList.contains('open')) close(); });
    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-idopontfoglalo]');
      if (t) { e.preventDefault(); open(); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
