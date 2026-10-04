/*
 * Ügyintézési Segéd – beépíthető chat-buborék (my-ai.hu)
 *
 * Beépítés bármely weboldalba, a </body> elé:
 *   <script src="https://my-ai.hu/ugyintezes/widget.js" data-ugyfel="minta" defer></script>
 *
 * Opcionális beállítások:
 *   data-ugyfel    a tudásbázis neve: ugyfelek/<ugyfel>.json   (alap: minta)
 *   data-szin      a buborék színe, pl. #17695f
 *   data-pozicio   "bal" vagy "jobb" (alap: jobb)
 *   data-felirat   a buborék akadálymentes felirata (alap: Ügyintézési segítség)
 *
 * A chat iframe-ben fut (chat.html), így az oldal stílusai nem zavarják, és
 * nincs szükség CORS-beállításra. Szerver, adatbázis és AI-díj nincs.
 */
(function () {
  var s = document.currentScript;
  if (!s || window.__ugyintezesWidget) return;
  window.__ugyintezesWidget = true;

  var ugyfel = (s.getAttribute('data-ugyfel') || 'minta').toLowerCase();
  if (!/^[a-z0-9-]{1,40}$/.test(ugyfel)) ugyfel = 'minta';
  var sajatSzin = s.getAttribute('data-szin') || '';
  if (!/^#[0-9a-f]{3,8}$/i.test(sajatSzin)) sajatSzin = '';
  var szin = sajatSzin || '#17695f';
  var oldal = s.getAttribute('data-pozicio') === 'bal' ? 'left' : 'right';
  var felirat = s.getAttribute('data-felirat') || 'Ügyintézési segítség';
  var base = new URL('.', s.src);
  var chatUrl = new URL('chat.html?u=' + encodeURIComponent(ugyfel) +
    (sajatSzin ? '&szin=' + encodeURIComponent(sajatSzin) : ''), base).href;

  var css =
    '.ugy-btn{position:fixed;bottom:20px;' + oldal + ':20px;z-index:2147483000;width:58px;height:58px;border-radius:50%;border:0;cursor:pointer;' +
    'background:' + szin + ';color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .15s}' +
    '.ugy-btn:hover{transform:scale(1.06)}.ugy-btn:focus-visible{outline:3px solid ' + szin + ';outline-offset:3px}' +
    '.ugy-btn svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}' +
    '.ugy-box{position:fixed;bottom:90px;' + oldal + ':20px;z-index:2147483000;width:370px;height:560px;max-height:calc(100vh - 110px);' +
    'border-radius:16px;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.28);background:#fff;display:none}' +
    '.ugy-box.open{display:block}.ugy-box iframe{width:100%;height:100%;border:0;display:block}' +
    '@media (max-width:480px){.ugy-box{inset:0;width:auto;height:auto;max-height:none;border-radius:0}.ugy-box.open+.ugy-btn{display:none}}' +
    '@media (prefers-reduced-motion:reduce){.ugy-btn{transition:none}}';

  var iconChat = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 10.5h7M8.5 13.5h4.5"/></svg>';
  var iconClose = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

  function init() {
    var host = document.createElement('div');
    host.setAttribute('data-ugyintezes', ugyfel);
    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
    var style = document.createElement('style');
    style.textContent = css;
    var box = document.createElement('div');
    box.className = 'ugy-box';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', felirat);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ugy-btn';
    btn.setAttribute('aria-label', felirat);
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = iconChat;
    root.appendChild(style);
    root.appendChild(box);
    root.appendChild(btn);
    document.body.appendChild(host);

    var frame = null;
    function setOpen(open) {
      if (open && !frame) {
        frame = document.createElement('iframe');
        frame.src = chatUrl;
        frame.title = felirat;
        box.appendChild(frame);
      }
      box.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.innerHTML = open ? iconClose : iconChat;
    }
    btn.addEventListener('click', function () { setOpen(!box.classList.contains('open')); });
    window.addEventListener('message', function (e) {
      if (e.origin === base.origin && e.data && e.data.ugyintezes === 'close') { setOpen(false); btn.focus(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && box.classList.contains('open')) setOpen(false);
    });
  }

  if (document.body) init();
  else document.addEventListener('DOMContentLoaded', init);
})();
