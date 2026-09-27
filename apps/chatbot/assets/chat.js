// Látogatói chat: az első üzenetnél indul a beszélgetés (addig csak a köszöntés
// látszik), az azonosító a böngészőben marad, így frissítés után folytatható.
(function () {
  'use strict';

  const BOOT = window.BOOT;
  const msgs = document.getElementById('msgs');
  const form = document.getElementById('composer');
  const text = document.getElementById('text');
  const progressBar = document.getElementById('progress');
  const newBtn = document.getElementById('new-chat');
  const closeBtn = document.getElementById('close-chat');
  const storeKey = 'chatbot_token:' + location.pathname;
  let token = null;
  let busy = false;

  const store = {
    get() { try { return localStorage.getItem(storeKey); } catch (e) { return null; } },
    set(v) { try { if (v) localStorage.setItem(storeKey, v); else localStorage.removeItem(storeKey); } catch (e) { /* privát mód */ } },
  };

  async function api(route, params, body) {
    const qs = new URLSearchParams(Object.assign({ r: route }, params || {}));
    const opts = { headers: { 'X-Requested-With': 'fetch' } };
    if (body) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    let res, data;
    try {
      res = await fetch('api/?' + qs.toString(), opts);
      data = await res.json();
    } catch (e) {
      throw Object.assign(new Error('Nem sikerült kapcsolódni. Ellenőrizd az internetkapcsolatot.'), { status: 0 });
    }
    if (!res.ok) throw Object.assign(new Error(data.error || 'Hiba történt.'), { status: res.status });
    return data;
  }

  function add(role, content) {
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    div.textContent = content;
    msgs.append(div);
    msgs.scrollTop = msgs.scrollHeight;
    return div;
  }

  function typing(on) {
    const existing = msgs.querySelector('.typing');
    if (!on) { if (existing) existing.remove(); return; }
    if (existing) return;
    const t = document.createElement('div');
    t.className = 'typing';
    t.setAttribute('aria-label', 'Válasz írása folyamatban');
    t.innerHTML = '<span></span><span></span><span></span>';
    msgs.append(t);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function setProgress(p) {
    if (!p || !p.total) { progressBar.hidden = true; return; }
    progressBar.hidden = false;
    progressBar.firstElementChild.style.width = Math.round((p.answered / p.total) * 100) + '%';
    progressBar.title = p.answered + ' / ' + p.total + ' adat megadva';
  }

  function reset() {
    token = null;
    store.set(null);
    msgs.replaceChildren();
    add('bot', BOOT.settings.bot.greeting);
    setProgress({ answered: 0, total: BOOT.settings.questions_total });
    newBtn.hidden = true;
    text.focus();
  }

  async function resume(saved) {
    try {
      const data = await api('conversation', { token: saved });
      token = saved;
      add('bot', data.greeting);
      data.messages.forEach((m) => add(m.role === 'user' ? 'user' : 'bot', m.content));
      setProgress(data.progress);
      newBtn.hidden = data.messages.length === 0;
    } catch (e) {
      reset();
    }
  }

  async function send(content) {
    if (busy || !content.trim()) return;
    busy = true;
    add('user', content);
    text.value = '';
    autosize();
    typing(true);
    try {
      if (!token) {
        const started = await api('start', {}, { source: BOOT.source || (BOOT.embed ? '' : location.href) });
        token = started.token;
        store.set(token);
      }
      const data = await api('message', {}, { token, text: content });
      typing(false);
      add('bot', data.reply);
      setProgress(data.progress);
      newBtn.hidden = false;
    } catch (e) {
      typing(false);
      add('err', e.message);
      if (e.status === 404) { token = null; store.set(null); }
    } finally {
      busy = false;
      text.focus();
    }
  }

  function autosize() {
    text.style.height = 'auto';
    text.style.height = Math.min(text.scrollHeight, 140) + 'px';
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); send(text.value); });
  text.addEventListener('input', autosize);
  text.addEventListener('keydown', (e) => {
    // Enter küld, Shift+Enter új sor (mobilon a billentyűzet küldés gombja is működik)
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(text.value); }
  });
  newBtn.addEventListener('click', () => { if (confirm('Új beszélgetést kezdesz? Az eddigi itt nem lesz látható.')) reset(); });
  if (closeBtn) closeBtn.addEventListener('click', () => window.parent.postMessage({ type: 'myai-chat-close' }, '*'));

  if (!BOOT.embed && 'serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});

  const saved = store.get();
  if (saved) resume(saved);
  else reset();
})();
