// Bevásárlólista app: listák, tételek kategóriák szerint, szóbeli bevitel (magyar),
// közös használat meghívó linkkel, automatikus frissítés, offline sor.
(function () {
  'use strict';

  const BOOT = window.BOOT;
  const root = document.getElementById('app');
  const who = document.getElementById('who');
  const menuBtn = document.getElementById('menu-btn');
  const CAT = Object.fromEntries(BOOT.categories.map((c) => [c.key, c]));
  const LS = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* privát mód */ } },
  };
  const state = { cfg: null, user: null, lists: [], listId: LS.get('bev_list', 0), data: null, suggestions: [], showBasket: false, queue: LS.get('bev_queue', []), offline: false };
  let pollTimer = null;

  // ---------------------------------------------------------------- segédek
  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else if (k === 'class') node.className = v;
      else if (k === 'value') node.value = v;
      else if (k === 'checked') node.checked = !!v;
      else node.setAttribute(k, v === true ? '' : v);
    }
    for (const c of children.flat(Infinity)) {
      if (c === null || c === undefined || c === false) continue;
      node.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return node;
  }

  // Gyerekek cseréje: listákat kilapít, az üres (null/false) elemeket kihagyja —
  // a böngésző saját replaceChildren()-je ezeket "null" / "[object …]" szövegként írná ki.
  function fill(node, ...kids) {
    node.replaceChildren(...kids.flat(Infinity).filter((k) => k !== null && k !== undefined && k !== false));
  }

  async function api(route, params, body) {
    const qs = new URLSearchParams(Object.assign({ r: route }, params || {}));
    const opts = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
    if (body) { opts.method = 'POST'; opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    let res, data;
    try {
      res = await fetch('api/?' + qs.toString(), opts);
      data = await res.json();
    } catch (e) { throw Object.assign(new Error('Nincs internetkapcsolat.'), { status: 0 }); }
    if (res.status === 401 && !['login', 'me', 'register'].includes(route)) { showLogin(); throw Object.assign(new Error(''), { silent: true }); }
    if (!res.ok) throw Object.assign(new Error(data.error || 'Hiba történt.'), { status: res.status });
    return data;
  }

  function toast(msg, isError, action) {
    document.querySelectorAll('.toast').forEach((t) => t.remove());
    const t = el('div', { class: 'toast' + (isError ? ' err' : ''), role: 'status' }, msg,
      action ? el('button', { class: 'undo', type: 'button', onclick: () => { t.remove(); action[1](); } }, action[0]) : null);
    document.body.append(t);
    setTimeout(() => t.remove(), action ? 6000 : 3500);
  }
  const fail = (e) => { if (!e.silent) toast(e.message, true); };
  const field = (label, control, hint) => el('label', {}, label, control, hint ? el('small', {}, hint) : null);

  function modal(title, body) {
    const back = el('div', { class: 'modal-back', onclick: (e) => { if (e.target === back) close(); } });
    const close = () => { back.remove(); document.removeEventListener('keydown', esc); if (back._onclose) back._onclose(); };
    const esc = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', esc);
    back.append(el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      el('div', { class: 'row between', style: 'margin-bottom:10px' }, el('h2', { style: 'margin:0' }, title), el('button', { class: 'icon-btn', type: 'button', onclick: close, 'aria-label': 'Bezárás' }, '✕')),
      body));
    document.body.append(back);
    close.back = back;
    return close;
  }

  // ---------------------------------------------------------------- belépés
  function narrow(title, ...content) {
    menuBtn.hidden = true;
    stopPolling();
    fill(root, el('div', { class: 'narrow', style: 'margin-top:12px' }, el('div', { class: 'card' }, el('h1', {}, title), ...content)));
  }
  const joinToken = () => new URLSearchParams(location.search).get('join');
  function joinNote() {
    return joinToken() ? el('p', { class: 'note' }, 'Meghívót kaptál egy közös bevásárlólistára. Lépj be (vagy hozz létre fiókot), és azonnal csatlakozol.') : null;
  }
  function showLogin() {
    who.textContent = 'Mondd be, és felírjuk';
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { const r = await api('login', {}, { email: f.email.value, password: f.password.value }); state.user = r.user; openMain(); } catch (err) { fail(err); }
    } },
    joinNote(),
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    field('Jelszó', el('input', { name: 'password', type: 'password', required: true, autocomplete: 'current-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Belépés'),
    el('div', { class: 'row between' },
      el('button', { class: 'link-btn', type: 'button', onclick: showForgot }, 'Elfelejtett jelszó?'),
      state.cfg && state.cfg.allow_register ? el('button', { class: 'link-btn', type: 'button', onclick: showRegister }, 'Új fiók') : null));
    narrow('Belépés', f);
  }
  function showRegister() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { const r = await api('register', {}, { name: f.name.value, email: f.email.value, password: f.password.value }); state.user = r.user; openMain(); } catch (err) { fail(err); }
    } },
    joinNote(),
    field('Neved', el('input', { name: 'name', required: true, autocomplete: 'name' })),
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'email' })),
    field('Jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Fiók létrehozása'),
    el('button', { class: 'link-btn', type: 'button', onclick: showLogin }, 'Már van fiókom'));
    narrow('Új fiók', f);
  }
  function showForgot() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try {
        await api('forgot', {}, { email: f.email.value });
        fill(f, el('p', { class: 'ok' }, 'Ha ezzel az email címmel van fiók, elküldtük a jelszó-visszaállító linket (1 óráig érvényes).'),
          el('button', { class: 'btn ghost block', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
      } catch (err) { fail(err); }
    } },
    field('Email', el('input', { name: 'email', type: 'email', required: true })),
    el('button', { class: 'btn block', type: 'submit' }, 'Link küldése'),
    el('button', { class: 'link-btn', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
    narrow('Elfelejtett jelszó', f);
  }
  function showReset(token) {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      if (f.password.value !== f.password2.value) return toast('A két jelszó nem egyezik.', true);
      try {
        await api('reset', {}, { token, password: f.password.value });
        history.replaceState(null, '', location.pathname);
        toast('Az új jelszó beállítva.');
        showLogin();
      } catch (err) { fail(err); }
    } },
    field('Új jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    field('Még egyszer', el('input', { name: 'password2', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Jelszó beállítása'));
    narrow('Új jelszó', f);
  }

  // ---------------------------------------------------------------- adatok, szinkron
  async function openMain() {
    LS.set('bev_user', state.user); // offline indításhoz
    who.textContent = state.user.name;
    menuBtn.hidden = false;
    const token = joinToken();
    if (token) {
      try {
        const r = await api('join', {}, { token });
        state.listId = r.id;
        LS.set('bev_list', r.id);
        toast('Csatlakoztál: ' + r.name);
      } catch (err) { fail(err); }
      history.replaceState(null, '', location.pathname);
    }
    await loadLists();
    await loadList(true);
    render();
    startPolling();
    flushQueue();
  }

  async function loadLists() {
    try {
      state.lists = (await api('lists')).lists;
      if (!state.lists.some((l) => l.id === state.listId)) state.listId = state.lists.length ? state.lists[0].id : 0;
      LS.set('bev_list', state.listId);
    } catch (e) { fail(e); }
  }

  async function loadList(full) {
    if (!state.listId) { state.data = null; return; }
    try {
      const params = { id: state.listId };
      if (!full && state.data) params.v = state.data.list.version;
      const d = await api('list', params);
      if (!d.unchanged) { state.data = d; LS.set('bev_cache_' + state.listId, d); }
      setOffline(false);
      const s = await api('suggestions', { list_id: state.listId });
      state.suggestions = s.suggestions;
    } catch (e) {
      if (e.status === 0) {
        setOffline(true);
        if (!state.data) state.data = LS.get('bev_cache_' + state.listId, null); // offline: az utolsó ismert állapot
      } else if (e.status === 404) {
        state.listId = 0; await loadLists(); if (state.listId) return loadList(true);
      } else fail(e);
    }
  }

  function setOffline(off) {
    if (state.offline === off) return;
    state.offline = off;
    const s = document.querySelector('.sync');
    if (s) s.replaceWith(syncLine());
  }

  // Automatikus frissítés, amíg az app látható (a társaid módosításai is megjelennek).
  function startPolling() {
    stopPolling();
    pollTimer = setInterval(async () => {
      if (document.visibilityState !== 'visible' || document.querySelector('.modal-back')) return;
      if (state.queue.length) await flushQueue();
      const before = state.data && state.data.list.version;
      await loadList(false);
      if (state.data && state.data.list.version !== before) render();
    }, 4000);
  }
  function stopPolling() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && state.user && state.listId) loadList(false).then(render); });
  window.addEventListener('online', () => flushQueue());

  // Módosítás küldése; ha nincs net, sorba tesszük és később elküldjük.
  async function mutate(route, body, optimistic) {
    try {
      const d = await api(route, {}, body);
      if (d.items) { state.data = { list: d.list, items: d.items, members: d.members }; LS.set('bev_cache_' + state.listId, state.data); }
      setOffline(false);
      return d;
    } catch (e) {
      if (e.status !== 0) throw e;
      state.queue.push({ route, body });
      LS.set('bev_queue', state.queue);
      if (optimistic) optimistic();
      setOffline(true);
      return null;
    }
  }

  async function flushQueue() {
    if (!state.queue.length) return;
    while (state.queue.length) {
      const op = state.queue[0];
      try {
        await api(op.route, {}, op.body);
      } catch (e) {
        if (e.status === 0) return; // még mindig nincs net
        // a szerver elutasította (pl. közben törölt tétel) — eldobjuk, és megyünk tovább
      }
      state.queue.shift();
      LS.set('bev_queue', state.queue);
    }
    setOffline(false);
    await loadList(true);
    render();
    toast('Szinkronizálva.');
  }

  // ---------------------------------------------------------------- tételek
  async function addText(text) {
    const parsed = window.parseShopping(text, BOOT.words);
    if (!parsed.length) return toast('Nem találtam felvehető tételt.', true);
    await addItems(parsed);
  }

  async function addItems(items) {
    try {
      const d = await mutate('items/add', { list_id: state.listId, items }, () => {
        items.forEach((i, n) => state.data.items.push({ id: 'tmp' + Date.now() + n, name: i.name, qty: i.qty, category: 'other', checked: false, note: '' }));
      });
      render();
      const names = items.map((i) => i.name + (i.qty ? ' (' + i.qty + ')' : '')).join(', ');
      if (d && d.ids) {
        toast((items.length === 1 ? 'Felvéve: ' : items.length + ' tétel felvéve: ') + names, false,
          ['Visszavonás', async () => { for (const id of d.ids) await mutate('items/delete', { id }); await loadList(true); render(); }]);
      } else toast('Offline mentve: ' + names);
    } catch (e) { fail(e); }
  }

  async function toggle(item) {
    item.checked = !item.checked;
    render();
    try { await mutate('items/update', { id: item.id, checked: item.checked }); render(); } catch (e) { fail(e); item.checked = !item.checked; render(); }
  }

  function editItem(item) {
    const f = el('form', { class: 'form' },
      field('Mit?', el('input', { name: 'name', required: true, maxlength: 120, value: item.name })),
      el('div', { class: 'grid2' },
        field('Mennyiség', el('input', { name: 'qty', maxlength: 40, value: item.qty || '', placeholder: 'pl. 2 kg, 1 csomag' })),
        field('Kategória', el('select', { name: 'category' }, BOOT.categories.map((c) => el('option', { value: c.key, selected: item.category === c.key }, c.icon + ' ' + c.label))))),
      field('Megjegyzés', el('input', { name: 'note', maxlength: 200, value: item.note || '', placeholder: 'pl. laktózmentes, akciós' })),
      el('div', { class: 'row between' },
        el('button', { class: 'btn ghost', type: 'button', style: 'color:var(--danger)', onclick: async () => {
          close();
          state.data.items = state.data.items.filter((i) => i.id !== item.id);
          render();
          try { await mutate('items/delete', { id: item.id }); } catch (e) { fail(e); }
        } }, 'Törlés'),
        el('button', { class: 'btn', type: 'submit' }, 'Mentés')));
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await mutate('items/update', { id: item.id, name: f.name.value, qty: f.qty.value, category: f.category.value, note: f.note.value }, () => {
          Object.assign(item, { name: f.name.value, qty: f.qty.value, note: f.note.value });
        });
        close();
        render();
      } catch (err) { fail(err); }
    });
    const close = modal('Tétel', f);
  }

  // ---------------------------------------------------------------- szóbeli bevitel
  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

  // Ha a beépített hangfelismerés itt nem használható: útmutató a billentyűzet
  // diktálás-gombjához (az mindenhol működik, a szöveget ugyanúgy tételekre bontjuk).
  function dictationHelp(reason) {
    const steps = isIOS
      ? [el('li', {}, 'Koppints a „Mit kell venni?” mezőbe.'),
        el('li', {}, 'A billentyűzet jobb alsó sarkában koppints a 🎤 mikrofonra, és mondd be: „kenyér, tej meg két kiló alma”.'),
        el('li', {}, 'Koppints a „Kész” / ↵ gombra — a tételek egyenként felkerülnek a listára.'),
        el('li', { class: 'muted' }, 'Ha nincs mikrofon a billentyűzeten: Beállítások → Általános → Billentyűzet → „Diktálás engedélyezése”.')]
      : [el('li', {}, 'Koppints a „Mit kell venni?” mezőbe.'),
        el('li', {}, 'A billentyűzeten koppints a 🎤 mikrofonra, és mondd be a tételeket.'),
        el('li', {}, 'Koppints a ↵ gombra — a tételek egyenként felkerülnek a listára.')];
    const close = modal('Szóbeli bevitel', el('div', {},
      el('p', {}, reason),
      el('ol', { style: 'padding-left:20px;margin:10px 0 14px' }, steps),
      isIOS && isStandalone ? el('p', { class: 'muted', style: 'font-size:.9rem' }, 'Tipp: Safariban (nem a kezdőképernyős appból) megnyitva a zöld 🎤 gomb is működik.') : null,
      el('button', { class: 'btn block', type: 'button', onclick: () => { close(); document.getElementById('add-input')?.focus(); } }, 'Rendben, diktálok')));
  }

  function startVoice() {
    if (!Recognition) {
      return dictationHelp('Ez a böngésző nem támogatja a beépített hangfelismerést, de a billentyűzet diktálás-gombjával ugyanígy bemondhatod a tételeket:');
    }
    // A kezdőképernyőre telepített iPhone-appokban az Apple nem engedi a beépített hangfelismerést.
    if (isIOS && isStandalone) {
      return dictationHelp('iPhone-on a kezdőképernyőre telepített appban az Apple nem engedi a beépített hangfelismerést — a billentyűzet diktálás-gombja viszont működik:');
    }
    const rec = new Recognition();
    rec.lang = 'hu-HU';
    rec.interimResults = true;
    rec.continuous = false;
    rec.maxAlternatives = 1;
    let finalText = '';
    let cancelled = false;
    const heard = el('div', { class: 'heard', 'aria-live': 'polite' }, '');
    const body = el('div', { class: 'listen' },
      el('div', { class: 'ring', 'aria-hidden': 'true' }, micIcon()),
      el('div', { class: 'hint' }, 'Mondd, mit kell venni — pl. „kenyér, tej meg két kiló alma”'),
      heard,
      el('div', { class: 'row', style: 'justify-content:center' },
        el('button', { class: 'btn ghost', type: 'button', onclick: () => { cancelled = true; rec.abort(); close(); } }, 'Mégse'),
        el('button', { class: 'btn', type: 'button', onclick: () => rec.stop() }, 'Kész')));
    const close = modal('Figyelek…', body);
    close.back._onclose = () => { cancelled = true; try { rec.abort(); } catch (e) { /* már leállt */ } };

    rec.onresult = (ev) => {
      let interim = '';
      finalText = '';
      for (let i = 0; i < ev.results.length; i++) {
        if (ev.results[i].isFinal) finalText += ev.results[i][0].transcript;
        else interim += ev.results[i][0].transcript;
      }
      heard.textContent = (finalText + ' ' + interim).trim();
    };
    rec.onerror = (ev) => {
      if (cancelled) return;
      cancelled = true;
      close();
      if (ev.error === 'service-not-allowed') {
        return dictationHelp(isIOS
          ? 'A Safari hangfelismeréséhez be kell kapcsolni a Siri / Diktálás funkciót (Beállítások → Általános → Billentyűzet → „Diktálás engedélyezése”). Addig a billentyűzet diktálás-gombjával is bemondhatod a tételeket:'
          : 'A böngésző itt nem engedi a beépített hangfelismerést, de a billentyűzet diktálás-gombjával ugyanígy bemondhatod a tételeket:');
      }
      if (ev.error === 'not-allowed') {
        return toast(isIOS ? 'A mikrofon le van tiltva: Beállítások → Safari → Mikrofon → „Engedélyezés”, majd próbáld újra.' : 'A mikrofon le van tiltva — a címsor melletti lakat ikonnál engedélyezd, majd próbáld újra.', true);
      }
      const msg = { 'no-speech': 'Nem hallottam semmit — próbáld újra.', network: 'A hangfelismeréshez internet kell.', 'audio-capture': 'Nem találok mikrofont.', aborted: '' }[ev.error] ?? 'A hangfelismerés nem sikerült (' + ev.error + ').';
      if (msg) toast(msg, true);
    };
    rec.onend = () => {
      if (cancelled) return;
      close();
      const text = (finalText || heard.textContent).trim();
      if (text) addText(text);
    };
    try { rec.start(); } catch (e) { close(); toast('A hangfelismerés nem indult el.', true); }
  }

  function micIcon() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = '<path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V21h2v-2.08A7 7 0 0 0 19 12h-2z"/>';
    return svg;
  }

  // ---------------------------------------------------------------- nézet
  function syncLine() {
    const q = state.queue.length;
    return el('div', { class: 'sync' + (state.offline ? ' off' : '') },
      state.offline ? '⚠︎ Nincs internet' + (q ? ' — ' + q + ' módosítás vár küldésre' : ' — a lista az utolsó ismert állapot') : '');
  }

  function render() {
    if (!state.user) return;
    if (!state.listId || !state.data) {
      fill(root, el('div', { class: 'empty' }, el('div', { class: 'big' }, '🛒'), el('h2', {}, 'Nincs még listád'),
        el('button', { class: 'btn', type: 'button', onclick: () => newList() }, '+ Új lista')));
      return;
    }
    const d = state.data;
    const focused = document.activeElement && document.activeElement.id === 'add-input';
    const oldInput = document.getElementById('add-input');
    const draft = oldInput ? oldInput.value : '';

    const listSel = el('select', { 'aria-label': 'Lista kiválasztása', onchange: async (e) => {
      if (e.target.value === '__new') { e.target.value = String(state.listId); return newList(); }
      state.listId = Number(e.target.value); LS.set('bev_list', state.listId); state.data = null; await loadList(true); render();
    } },
    state.lists.map((l) => el('option', { value: l.id, selected: l.id === state.listId }, l.name + (l.members > 1 ? ' 👥' : ''))),
    el('option', { value: '__new' }, '+ Új lista…'));

    const input = el('input', { id: 'add-input', type: 'text', enterkeyhint: 'done', autocomplete: 'off', placeholder: 'Mit kell venni? pl. tej, 2 kg alma', value: draft, 'aria-label': 'Új tétel' });
    const addForm = el('form', { class: 'add-bar', onsubmit: (e) => { e.preventDefault(); const t = input.value.trim(); if (!t) return; input.value = ''; addText(t); input.focus(); } },
      input,
      el('button', { class: 'btn mic', type: 'button', 'aria-label': 'Szóbeli bevitel', title: 'Mondd be', onclick: startVoice }, micIcon()),
      el('button', { class: 'btn ghost', type: 'submit', 'aria-label': 'Hozzáadás' }, '+'));

    const open = d.items.filter((i) => !i.checked);
    const done = d.items.filter((i) => i.checked);
    const row = (i) => el('div', { class: 'item-row' + (i.checked ? ' done' : '') },
      el('button', { class: 'tick', type: 'button', 'aria-label': (i.checked ? 'Visszarakás: ' : 'Megvettem: ') + i.name, 'aria-pressed': String(i.checked), onclick: () => toggle(i) }, el('span', {}, '✓')),
      el('button', { class: 'item-main', type: 'button', onclick: () => editItem(i) },
        el('span', { class: 'n' }, i.name), i.note ? el('span', { class: 'note' }, i.note) : null),
      i.qty ? el('span', { class: 'qty' }, i.qty) : null);

    const groups = BOOT.categories.map((c) => ({ c, items: open.filter((i) => (CAT[i.category] ? i.category : 'other') === c.key) })).filter((g) => g.items.length);
    const chips = state.suggestions.slice(0, 10);

    fill(root, 
      el('div', { class: 'list-bar' }, listSel,
        el('button', { class: 'btn ghost small', type: 'button', onclick: () => shareList(), 'aria-label': 'Megosztás' }, d.members.length > 1 ? '👥 ' + d.members.length : 'Megosztás')),
      addForm,
      chips.length ? el('div', { class: 'chips', 'aria-label': 'Gyakran vett tételek' }, chips.map((s) =>
        el('button', { class: 'chip-btn', type: 'button', onclick: () => addItems([{ name: s.name, qty: s.qty }]) }, '+ ' + s.name))) : null,
      syncLine(),
      open.length ? groups.map((g) => [el('div', { class: 'cat-h' }, el('span', { class: 'ic', 'aria-hidden': 'true' }, g.c.icon), g.c.label), g.items.map(row)])
        : el('div', { class: 'empty', style: 'padding:24px 16px' }, el('div', { class: 'big', 'aria-hidden': 'true' }, done.length ? '✅' : '📝'),
          el('p', {}, done.length ? 'Minden megvan!' : 'Üres a lista. Írd be, vagy nyomd meg a mikrofont, és mondd be, mit kell venni.')),
      done.length ? [
        el('div', { class: 'basket-h' },
          el('button', { class: 'link-btn', type: 'button', onclick: () => { state.showBasket = !state.showBasket; render(); }, 'aria-expanded': String(state.showBasket) }, (state.showBasket ? '▾' : '▸') + ' Kosárban (' + done.length + ')'),
          el('button', { class: 'link-btn', type: 'button', onclick: clearChecked }, 'Kosárban lévők törlése')),
        state.showBasket ? done.map(row) : null] : null);

    if (focused) input.focus();
  }

  async function clearChecked() {
    const n = state.data.items.filter((i) => i.checked).length;
    if (!n || !confirm('Törlöd a ' + n + ' kipipált tételt a listáról?')) return;
    try {
      await mutate('items/clear', { list_id: state.listId }, () => { state.data.items = state.data.items.filter((i) => !i.checked); });
      render();
    } catch (e) { fail(e); }
  }

  async function newList() {
    const name = prompt('Az új lista neve:', 'Bevásárlás');
    if (!name || !name.trim()) return;
    try {
      const r = await api('lists/save', {}, { name: name.trim() });
      state.listId = r.id; LS.set('bev_list', r.id);
      await loadLists(); await loadList(true); render();
    } catch (e) { fail(e); }
  }

  async function shareList() {
    let url;
    try { url = (await api('lists/invite', {}, { id: state.listId })).url; } catch (e) { return fail(e); }
    const d = state.data;
    const isOwner = d.list.owner_id === state.user.id;
    const box = el('div', { class: 'form' },
      el('p', { class: 'muted', style: 'margin:0' }, 'Küldd el ezt a linket annak, akivel közösen vásárolsz. Megnyitás és belépés után ugyanezt a listát látja, és a változások pár másodpercen belül mindenkinél megjelennek.'),
      el('div', { class: 'snippet' }, url),
      el('div', { class: 'row' },
        navigator.share ? el('button', { class: 'btn', type: 'button', onclick: () => navigator.share({ title: d.list.name, text: 'Csatlakozz a „' + d.list.name + '” bevásárlólistához:', url }).catch(() => {}) }, 'Küldés…') : null,
        el('button', { class: 'btn ghost', type: 'button', onclick: () => navigator.clipboard?.writeText(url).then(() => toast('Link kimásolva'), () => toast('Jelöld ki és másold ki kézzel.', true)) }, 'Link másolása')),
      el('h3', { style: 'margin:10px 0 0' }, 'Tagok'),
      el('div', { class: 'list' }, d.members.map((m) => el('div', { class: 'item', style: 'grid-template-columns:1fr auto;cursor:default' },
        el('div', { class: 'who' }, m.name), el('span', { class: 'muted' }, m.role === 'owner' ? 'tulajdonos' : 'tag')))),
      isOwner ? el('button', { class: 'link-btn', type: 'button', onclick: async () => {
        if (!confirm('Új linket készítesz? A régi link ezután nem működik (a már csatlakozott tagok maradnak).')) return;
        try { await api('lists/invite', {}, { id: state.listId, reset: true }); close(); shareList(); } catch (e) { fail(e); }
      } }, 'Új meghívó link') : null);
    const close = modal('Közös lista: ' + d.list.name, box);
  }

  // ---------------------------------------------------------------- menü, beállítások
  menuBtn.addEventListener('click', () => {
    const d = state.data;
    const isOwner = d && d.list.owner_id === state.user.id;
    const btn = (label, fn, cls) => el('button', { class: 'sheet-btn' + (cls ? ' ' + cls : ''), type: 'button', onclick: () => { close(); fn(); } }, label);
    const close = modal('Menü', el('div', {},
      d ? btn('👥  Megosztás, tagok', shareList) : null,
      d ? btn('✏️  Lista átnevezése', async () => {
        const name = prompt('A lista új neve:', d.list.name);
        if (!name || !name.trim()) return;
        try { await api('lists/save', {}, { id: state.listId, name: name.trim() }); await loadLists(); await loadList(true); render(); } catch (e) { fail(e); }
      }) : null,
      btn('➕  Új lista', newList),
      d ? btn(isOwner ? '🗑  Lista törlése' : '🚪  Kilépés a listából', async () => {
        if (!confirm(isOwner ? 'Törlöd a „' + d.list.name + '” listát mindenkinél?' : 'Kilépsz a „' + d.list.name + '” listából?')) return;
        try { await api('lists/delete', {}, { id: state.listId }); state.listId = 0; await loadLists(); await loadList(true); render(); } catch (e) { fail(e); }
      }, 'danger') : null,
      btn('⚙️  Beállítások', showSettings),
      btn('↩︎  Kijelentkezés', async () => { await api('logout', {}, {}); state.user = null; showLogin(); })));
  });

  let deferredInstall = null;
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredInstall = e; });

  function showSettings() {
    const u = state.user;
    const acc = el('form', { class: 'form' },
      field('Neved', el('input', { name: 'name', required: true, value: u.name })),
      field('Email', el('input', { name: 'email', type: 'email', required: true, value: u.email })),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    acc.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { state.user = (await api('account/save', {}, { name: acc.name.value, email: acc.email.value })).user; who.textContent = state.user.name; toast('Mentve'); } catch (err) { fail(err); }
    });
    const pw = el('form', { class: 'form' },
      field('Jelenlegi jelszó', el('input', { name: 'current', type: 'password', required: true, autocomplete: 'current-password' })),
      field('Új jelszó (min. 8 karakter)', el('input', { name: 'new', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn', type: 'submit' }, 'Jelszó mentése'));
    pw.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('account/password', {}, { current: pw.current.value, new: pw.new.value }); pw.reset(); toast('Jelszó módosítva'); } catch (err) { fail(err); }
    });
    const installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const body = el('div', { class: 'form' },
      el('h3', { style: 'margin:0' }, 'Fiók'), acc,
      el('hr'), el('h3', { style: 'margin:0' }, 'Jelszó'), pw,
      el('hr'), el('h3', { style: 'margin:0' }, 'Telepítés a telefonra'),
      installed ? el('p', { class: 'ok', style: 'margin:0' }, 'Az app telepítve van ezen az eszközön.')
        : el('p', { class: 'muted', style: 'margin:0' }, 'iPhone: Safari → Megosztás → „Főképernyőhöz adás”. Android: Chrome menü → „Alkalmazás telepítése”.'),
      !installed && deferredInstall ? el('button', { class: 'btn', type: 'button', onclick: async () => { deferredInstall.prompt(); await deferredInstall.userChoice; deferredInstall = null; } }, 'Telepítés') : null,
      u.is_admin ? adminPart() : null);
    modal('Beállítások', body);
  }

  function adminPart() {
    const box = el('div', { class: 'form' }, el('hr'), el('h3', { style: 'margin:0' }, 'Felhasználók (admin)'), el('p', { class: 'muted' }, 'Betöltés…'));
    api('admin/users').then((d) => {
      const opts = el('form', { class: 'form' },
        field('Az app neve', el('input', { name: 'app_name', value: d.options.app_name })),
        el('label', { class: 'check' }, el('input', { type: 'checkbox', name: 'reg', checked: d.options.allow_register }), 'Bárki regisztrálhat saját fiókot'),
        el('button', { class: 'btn small', type: 'submit' }, 'Mentés'));
      opts.addEventListener('submit', async (e) => {
        e.preventDefault();
        try { await api('admin/options/save', {}, { app_name: opts.app_name.value, allow_register: opts.reg.checked }); toast('Mentve'); } catch (err) { fail(err); }
      });
      fill(box, el('hr'), el('h3', { style: 'margin:0' }, 'Felhasználók (admin)'),
        el('div', { class: 'list' }, d.users.map((x) => el('div', { class: 'item', style: 'grid-template-columns:1fr auto;cursor:default' },
          el('div', {}, el('div', { class: 'who' }, x.name + (x.is_admin ? ' (admin)' : '')), el('div', { class: 'what' }, x.email)),
          x.id === state.user.id ? el('span', { class: 'muted' }, 'te') : el('button', { class: 'btn small ghost', type: 'button', onclick: async () => {
            if (!confirm('Törlöd ' + x.name + ' fiókját és a saját listáit?')) return;
            try { await api('admin/users/delete', {}, { id: x.id }); toast('Törölve'); document.querySelector('.modal-back')?.remove(); showSettings(); } catch (err) { fail(err); }
          } }, 'Törlés')))),
        opts);
    }).catch(fail);
    return box;
  }

  // ---------------------------------------------------------------- indulás
  async function start() {
    fill(root, el('p', { class: 'muted' }, 'Betöltés…'));
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
    try {
      state.cfg = await api('config');
      const reset = new URLSearchParams(location.search).get('reset');
      if (reset) return showReset(reset);
      const { user } = await api('me');
      state.user = user;
      if (!user) return joinToken() && state.cfg.allow_register ? showRegister() : showLogin();
      openMain();
    } catch (e) {
      // offline indítás: ha van mentett lista, azt mutatjuk
      const cached = LS.get('bev_cache_' + state.listId, null);
      if (e.status === 0 && cached) {
        state.user = LS.get('bev_user', null) || { id: 0, name: '', email: '' };
        state.data = cached; state.offline = true; render();
      } else fail(e);
    }
  }
  start();
})();
