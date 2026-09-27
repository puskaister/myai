<?php
// Beágyazható widget: egy <script> sorral bármely weboldal sarkába tesz egy
// chat-buborékot, ami iframe-ben nyitja meg a chatet.
// Használat: <script src="https://…/widget.php" async></script>
declare(strict_types=1);

require __DIR__ . '/api/lib/core.php';

$s = app_installed() ? get_settings() : default_settings();
$primary = $s['theme']['primary'];
$config = [
    'url'     => app_base_url() . '/?embed=1',
    'primary' => $primary,
    'on'      => on_primary($primary),
    'label'   => $s['bot']['name'],
];

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=300');
?>
(function () {
  'use strict';
  if (window.__myaiChat) return;
  window.__myaiChat = true;
  var C = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;

  var css = '.myai-chat-btn{position:fixed;right:20px;bottom:20px;z-index:2147483000;width:60px;height:60px;border-radius:50%;border:0;cursor:pointer;'
    + 'background:' + C.primary + ';color:' + C.on + ';box-shadow:0 8px 24px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .15s}'
    + '.myai-chat-btn:hover{transform:scale(1.06)}.myai-chat-btn svg{width:28px;height:28px;fill:currentColor}'
    + '.myai-chat-frame{position:fixed;right:20px;bottom:92px;z-index:2147483000;width:380px;height:600px;max-height:calc(100vh - 112px);border:0;border-radius:18px;'
    + 'box-shadow:0 16px 48px rgba(0,0,0,.28);background:#fff;display:none}'
    + '.myai-chat-frame.open{display:block}'
    + '@media (max-width:520px){.myai-chat-frame{right:0;bottom:0;width:100%;height:100%;max-height:none;border-radius:0}.myai-chat-open .myai-chat-btn{display:none}}';
  var style = document.createElement('style');
  style.textContent = css;
  document.head.appendChild(style);

  var btn = document.createElement('button');
  btn.className = 'myai-chat-btn';
  btn.type = 'button';
  btn.setAttribute('aria-label', 'Chat megnyitása: ' + C.label);
  btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.5 3 2 6.8 2 11.5c0 2.4 1.2 4.6 3.1 6.1L4 21l4.1-1.9c1.2.4 2.5.6 3.9.6 5.5 0 10-3.8 10-8.5S17.5 3 12 3z"/></svg>';

  var frame = null;
  function toggle(open) {
    if (!frame) {
      frame = document.createElement('iframe');
      frame.className = 'myai-chat-frame';
      frame.title = C.label;
      frame.src = C.url + '&source=' + encodeURIComponent(location.href);
      frame.allow = 'clipboard-write';
      document.body.appendChild(frame);
    }
    var isOpen = open === undefined ? !frame.classList.contains('open') : open;
    frame.classList.toggle('open', isOpen);
    document.documentElement.classList.toggle('myai-chat-open', isOpen);
    btn.setAttribute('aria-expanded', String(isOpen));
  }
  btn.addEventListener('click', function () { toggle(); });
  window.addEventListener('message', function (e) {
    if (e.data && e.data.type === 'myai-chat-close' && frame && e.source === frame.contentWindow) toggle(false);
  });

  if (document.body) document.body.appendChild(btn);
  else document.addEventListener('DOMContentLoaded', function () { document.body.appendChild(btn); });
})();
