// Látogatószámláló – követő szkript. Beillesztés:
//   <script src="https://my-ai.hu/stats/t.js" defer></script>
// Méri: oldalmegtekintés, a látható lapon eltöltött idő, kattintások (linkek,
// gombok, data-track elemek). Sütit nem használ; a visszatérő látogató
// felismeréséhez egy véletlen azonosítót tesz a localStorage-ba.
(function () {
  'use strict';
  if (window.__myaiStats || navigator.webdriver) return;
  window.__myaiStats = true;

  var script = document.currentScript;
  var endpoint = (script && script.src ? script.src.replace(/t\.js(\?.*)?$/, '') : '/stats/') + 'track.php';
  var pv = 0, key = '', active = 0, lastTick = Date.now(), lastSent = 0;

  function vid() {
    try {
      var v = localStorage.getItem('myai_vid');
      if (!/^[a-z0-9]{16}$/.test(v || '')) {
        v = '';
        for (var i = 0; i < 16; i++) v += 'abcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(Math.random() * 36));
        localStorage.setItem('myai_vid', v);
      }
      return v;
    } catch (e) { return ''; }
  }

  function send(data, beacon) {
    var body = JSON.stringify(data);
    // text/plain → egyszerű kérés, nincs CORS előkérés; a beacon oldalelhagyáskor is elmegy
    if (beacon && navigator.sendBeacon) {
      navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain' }));
      return Promise.resolve(null);
    }
    return fetch(endpoint, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'text/plain' } })
      .then(function (r) { return r.status === 200 ? r.json() : null; })
      .catch(function () { return null; });
  }

  // Csak a látható, aktív lapon eltöltött időt számoljuk.
  function tick() {
    var now = Date.now();
    if (document.visibilityState === 'visible') active += Math.min(now - lastTick, 30000) / 1000;
    lastTick = now;
  }

  function ping(beacon) {
    tick();
    var a = Math.round(active);
    if (!pv || a === lastSent) return;
    lastSent = a;
    send({ t: 'ping', pv: pv, k: key, a: a }, beacon);
  }

  send({
    t: 'view', v: vid(), h: location.hostname, p: location.pathname + location.search, ti: document.title,
    r: document.referrer, s: screen.width + 'x' + screen.height, l: navigator.language || '',
  }).then(function (res) {
    if (!res || !res.pv) return;
    pv = res.pv;
    key = res.k;
    try { if (res.v) localStorage.setItem('myai_vid', res.v); } catch (e) { /* privát mód */ }
  });

  setInterval(function () { if (document.visibilityState === 'visible') ping(false); }, 15000);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') ping(true);
    else lastTick = Date.now();
  });
  window.addEventListener('pagehide', function () { ping(true); });

  document.addEventListener('click', function (e) {
    if (!pv || !e.target || !e.target.closest) return;
    var el = e.target.closest('a, button, [data-track], input[type=submit], summary');
    if (!el) return;
    var label = el.getAttribute('data-track') || el.getAttribute('aria-label') || el.innerText || el.value || el.title || '';
    send({ t: 'click', pv: pv, k: key, tg: el.tagName.toLowerCase(), lb: label.replace(/\s+/g, ' ').trim().slice(0, 120), hr: el.href || '' }, true);
  }, true);
})();
