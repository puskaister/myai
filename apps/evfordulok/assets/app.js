// Évfordulók app: közelgő alkalmak, rögzítés/szerkesztés, beállítások.
(function () {
  'use strict';

  const root = document.getElementById('app');
  const who = document.getElementById('who');
  const MONTHS = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
  const MON = ['jan', 'febr', 'márc', 'ápr', 'máj', 'jún', 'júl', 'aug', 'szept', 'okt', 'nov', 'dec'];
  const DAYS = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
  const REMIND = [[-1, 'Ne küldjön emailt'], [0, 'Aznap reggel'], [1, '1 nappal előtte'], [2, '2 nappal előtte'], [3, '3 nappal előtte'], [7, '1 héttel előtte'], [14, '2 héttel előtte']];
  const state = { tab: 'list', cfg: null, user: null, events: [], today: null, admin: null };

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
    } catch (e) { throw Object.assign(new Error('Nem sikerült kapcsolódni. Ellenőrizd az internetkapcsolatot.'), { status: 0 }); }
    if (res.status === 401 && !['login', 'me'].includes(route)) { showLogin(); throw Object.assign(new Error(''), { silent: true }); }
    if (!res.ok) throw Object.assign(new Error(data.error || 'Hiba történt.'), { status: res.status });
    return data;
  }

  function toast(msg, isError) {
    const t = el('div', { class: 'toast' + (isError ? ' err' : ''), role: 'status' }, msg);
    document.body.append(t);
    setTimeout(() => t.remove(), 3500);
  }
  const fail = (e) => { if (!e.silent) toast(e.message, true); };
  const field = (label, control, hint) => el('label', {}, label, control, hint ? el('small', {}, hint) : null);

  function modal(title, body) {
    const back = el('div', { class: 'modal-back', onclick: (e) => { if (e.target === back) close(); } });
    const close = () => { back.remove(); document.removeEventListener('keydown', esc); };
    const esc = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', esc);
    back.append(el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      el('div', { class: 'row between', style: 'margin-bottom:10px' }, el('h2', { style: 'margin:0' }, title), el('button', { class: 'icon-btn', type: 'button', onclick: close, 'aria-label': 'Bezárás' }, '✕')),
      body));
    document.body.append(back);
    const first = back.querySelector('input:not([type=radio]), textarea');
    if (first) first.focus();
    return close;
  }

  const parse = (d) => new Date(d + 'T12:00:00');
  const longDate = (d) => { const x = parse(d); return x.getFullYear() + '. ' + MONTHS[x.getMonth()] + ' ' + x.getDate() + '., ' + DAYS[x.getDay()]; };
  const whenText = (n) => (n === 0 ? 'Ma' : n === 1 ? 'Holnap' : n < 7 ? n + ' nap múlva' : n < 14 ? 'jövő héten' : 'még ' + n + ' nap');
  const catIcon = (c) => (state.cfg.categories[c] || ['', '📌'])[1];

  // ---------------------------------------------------------------- belépés
  function narrow(title, ...content) {
    fill(root, el('div', { class: 'narrow', style: 'margin-top:12px' }, el('div', { class: 'card' }, el('h1', {}, title), ...content)));
  }
  function showLogin() {
    who.textContent = 'Fontos dátumok, időben';
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { const r = await api('login', {}, { email: f.email.value, password: f.password.value }); state.user = r.user; openMain(); } catch (err) { fail(err); }
    } },
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    field('Jelszó', el('input', { name: 'password', type: 'password', required: true, autocomplete: 'current-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Belépés'),
    el('div', { class: 'row between' },
      el('button', { class: 'link-btn', type: 'button', onclick: showForgot }, 'Elfelejtett jelszó?'),
      state.cfg && state.cfg.allow_register ? el('button', { class: 'link-btn', type: 'button', onclick: showRegister }, 'Regisztráció') : null));
    narrow('Belépés', f);
  }
  function showRegister() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { const r = await api('register', {}, { name: f.name.value, email: f.email.value, password: f.password.value }); state.user = r.user; openMain(); } catch (err) { fail(err); }
    } },
    field('Neved', el('input', { name: 'name', required: true, autocomplete: 'name' })),
    field('Email (ide jönnek az emlékeztetők)', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'email' })),
    field('Jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Fiók létrehozása'),
    el('button', { class: 'link-btn', type: 'button', onclick: showLogin }, 'Már van fiókom'));
    narrow('Regisztráció', f);
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

  // ---------------------------------------------------------------- fő nézet
  async function openMain() {
    who.textContent = state.user.name;
    await loadEvents();
    render();
  }

  async function loadEvents() {
    try {
      const d = await api('events');
      state.events = d.events;
      state.today = d.today;
      updateBadge();
    } catch (e) { fail(e); }
  }

  // Telepített appnál a mai és holnapi alkalmak száma az ikonon (ahol a rendszer támogatja).
  function updateBadge() {
    const n = state.events.filter((e) => e.days_until !== null && e.days_until <= 1).length;
    try {
      if (n && navigator.setAppBadge) navigator.setAppBadge(n);
      else if (navigator.clearAppBadge) navigator.clearAppBadge();
    } catch (e) { /* nem támogatott */ }
    document.title = (n ? '(' + n + ') ' : '') + document.title.replace(/^\(\d+\) /, '');
  }

  function render() {
    const tabs = el('nav', { class: 'tabs' },
      el('button', { class: state.tab === 'list' ? 'on' : '', type: 'button', onclick: () => { state.tab = 'list'; render(); } }, 'Közelgő'),
      el('button', { class: state.tab === 'settings' ? 'on' : '', type: 'button', onclick: () => { state.tab = 'settings'; render(); } }, 'Beállítások'));
    fill(root, tabs, state.tab === 'list' ? viewList() : viewSettings());
    document.querySelector('.fab')?.remove();
    if (state.tab === 'list') document.body.append(el('button', { class: 'fab', type: 'button', 'aria-label': 'Új alkalom', onclick: () => editEvent(null) }, '+'));
  }

  function evRow(e) {
    const d = parse(e.next);
    const soon = e.days_until <= 7;
    return el('button', { class: 'ev', type: 'button', onclick: () => editEvent(e) },
      el('div', { class: 'date' }, el('b', {}, d.getDate()), el('span', {}, MON[d.getMonth()])),
      el('div', {},
        el('div', { class: 't' }, catIcon(e.category) + ' ' + e.title),
        el('div', { class: 's' }, e.label + (e.recurrence === 'monthly' ? ' · havonta' : e.recurrence === 'once' ? ' · egyszeri' : '') + ' · ' + DAYS[d.getDay()])),
      el('div', { class: 'days' + (soon ? ' soon' : '') }, whenText(e.days_until)));
  }

  function viewList() {
    const upcoming = state.events.filter((e) => e.next !== null);
    const past = state.events.filter((e) => e.next === null);
    if (!state.events.length) {
      return el('div', { class: 'empty' }, el('div', { class: 'big', 'aria-hidden': 'true' }, '🎂'),
        el('h2', {}, 'Még nincs rögzített alkalom'),
        el('p', {}, 'Születésnapok, névnapok, évfordulók — rögzítsd őket, és előtte napon emailben szólunk.'),
        el('button', { class: 'btn', type: 'button', onclick: () => editEvent(null) }, '+ Első alkalom rögzítése'));
    }

    // Kiemelés: ma és holnap; ha nincs ilyen, a legközelebbi alkalom.
    const hot = upcoming.filter((e) => e.days_until <= 1);
    const hl = (hot.length ? hot : upcoming.slice(0, 1)).map((e) => el('div', { class: 'hl-card', role: 'button', tabindex: '0', onclick: () => editEvent(e), onkeydown: (k) => { if (k.key === 'Enter') editEvent(e); } },
      el('div', { class: 'emoji', 'aria-hidden': 'true' }, catIcon(e.category)),
      el('div', {},
        el('div', { class: 'when' }, e.days_until === 0 ? 'Ma' : e.days_until === 1 ? 'Holnap' : 'Következő · ' + whenText(e.days_until)),
        el('div', { class: 't' }, e.title),
        el('div', { class: 's' }, e.label + ' · ' + longDate(e.next)))));

    // A következő 12 hónap, hónapok szerint csoportosítva.
    const groups = [];
    let current = null;
    for (const e of upcoming) {
      const key = e.next.slice(0, 7);
      if (!current || current.key !== key) { current = { key, items: [] }; groups.push(current); }
      current.items.push(e);
    }
    return el('div', { style: 'padding-bottom:90px' },
      el('div', { class: 'hl' }, hl),
      groups.map((g) => {
        const [y, m] = g.key.split('-').map(Number);
        return [el('div', { class: 'month-h' }, MONTHS[m - 1] + (y !== parse(state.today).getFullYear() ? ' ' + y : '')), g.items.map(evRow)];
      }),
      past.length ? el('details', { style: 'margin-top:20px' }, el('summary', { class: 'muted' }, 'Elmúlt egyszeri alkalmak (' + past.length + ')'),
        past.map((e) => el('button', { class: 'ev', type: 'button', onclick: () => editEvent(e), style: 'opacity:.6' },
          el('div', { class: 'date' }, el('b', {}, e.day), el('span', {}, MON[e.month - 1])),
          el('div', {}, el('div', { class: 't' }, catIcon(e.category) + ' ' + e.title), el('div', { class: 's' }, e.year + '. ' + MONTHS[e.month - 1] + ' ' + e.day + '.')),
          el('div', { class: 'days' }, 'elmúlt')))) : null);
  }

  // ---------------------------------------------------------------- rögzítés / szerkesztés
  function editEvent(e) {
    const cfg = state.cfg;
    const f = el('form', { class: 'form' });
    const cats = el('div', { class: 'cat-grid', role: 'radiogroup', 'aria-label': 'Kategória' },
      Object.entries(cfg.categories).map(([k, [label, icon]]) => el('label', {},
        el('input', { type: 'radio', name: 'category', value: k, checked: e ? e.category === k : k === 'birthday' }),
        el('span', { class: 'e', 'aria-hidden': 'true' }, icon), label)));
    const rec = el('div', { class: 'seg', role: 'radiogroup', 'aria-label': 'Ismétlődés' },
      [['yearly', 'Évente'], ['monthly', 'Havonta'], ['once', 'Egyszeri']].map(([k, label]) => el('label', {},
        el('input', { type: 'radio', name: 'recurrence', value: k, checked: e ? e.recurrence === k : k === 'yearly' }), label)));
    const month = el('select', { name: 'month', 'aria-label': 'Hónap' }, MONTHS.map((m, i) => el('option', { value: i + 1, selected: e ? e.month === i + 1 : i === new Date().getMonth() }, m)));
    const day = el('select', { name: 'day', 'aria-label': 'Nap' }, Array.from({ length: 31 }, (_, i) => el('option', { value: i + 1, selected: e ? e.day === i + 1 : i + 1 === new Date().getDate() }, (i + 1) + '.')));
    const year = el('input', { name: 'year', type: 'number', inputmode: 'numeric', min: 1800, max: 2200, placeholder: 'Évszám', value: e && e.year ? e.year : '', 'aria-label': 'Évszám' });
    const yearHint = el('small', {});
    const monthWrap = el('div', {}, month);
    const remind = el('select', { name: 'remind' }, REMIND.map(([v, l]) => el('option', { value: v, selected: e ? e.remind_days === v : v === 1 }, l)));

    const sync = () => {
      const r = f.querySelector('input[name=recurrence]:checked').value;
      monthWrap.hidden = r === 'monthly';
      year.required = r === 'once';
      year.hidden = r === 'monthly';
      yearHint.textContent = r === 'once' ? 'Egyszeri alkalomnál az évszám kötelező.'
        : r === 'yearly' ? 'Évszám nem kötelező — ha megadod (pl. születési év), kiírjuk, hányadik alkalom következik.'
          : 'Minden hónap ezen a napján (rövidebb hónapban az utolsó napon).';
    };
    rec.addEventListener('change', sync);

    f.append(
      field('Mi az alkalom?', el('input', { name: 'title', required: true, maxlength: 150, placeholder: 'pl. Anya születésnapja', value: e ? e.title : '' })),
      el('div', {}, el('div', { style: 'font-weight:600;margin-bottom:6px' }, 'Kategória'), cats),
      el('div', {}, el('div', { style: 'font-weight:600;margin-bottom:6px' }, 'Ismétlődés'), rec),
      el('div', {}, el('div', { style: 'font-weight:600;margin-bottom:6px' }, 'Dátum'), el('div', { class: 'date-row' }, monthWrap, day, year), yearHint),
      field('Email-emlékeztető', remind),
      field('Megjegyzés (nem kötelező)', el('textarea', { name: 'note', maxlength: 1000, placeholder: 'pl. ajándékötlet, telefonszám' }, e ? e.note || '' : '')),
      el('div', { class: 'row between' },
        e ? el('button', { class: 'btn ghost', type: 'button', style: 'color:var(--danger)', onclick: async () => {
          if (!confirm('Törlöd: „' + e.title + '”?')) return;
          try { await api('events/delete', {}, { id: e.id }); close(); toast('Törölve'); await loadEvents(); render(); } catch (err) { fail(err); }
        } }, 'Törlés') : el('span'),
        el('button', { class: 'btn', type: 'submit' }, 'Mentés')));
    sync();

    f.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const payload = {
        id: e ? e.id : 0, title: f.title.value, category: f.querySelector('input[name=category]:checked').value,
        recurrence: f.querySelector('input[name=recurrence]:checked').value, month: Number(month.value), day: Number(day.value),
        year: year.hidden || year.value === '' ? null : Number(year.value), remind_days: Number(remind.value), note: f.note.value,
      };
      try {
        await api('events/save', {}, payload);
        close();
        toast('Mentve');
        await loadEvents();
        render();
      } catch (err) { fail(err); }
    });
    const close = modal(e ? 'Alkalom szerkesztése' : 'Új alkalom', f);
  }

  // ---------------------------------------------------------------- beállítások
  function viewSettings() {
    const u = state.user;
    const acc = el('form', { class: 'card form' },
      el('h2', {}, 'Fiók és értesítések'),
      field('Neved', el('input', { name: 'name', required: true, value: u.name })),
      field('Email (ide jönnek az emlékeztetők)', el('input', { name: 'email', type: 'email', required: true, value: u.email })),
      el('label', { class: 'check' }, el('input', { type: 'checkbox', name: 'notify', checked: u.notify }), 'Email-emlékeztetők küldése'),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    acc.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { state.user = (await api('account/save', {}, { name: acc.name.value, email: acc.email.value, notify: acc.notify.checked })).user; toast('Mentve'); render(); } catch (err) { fail(err); }
    });

    const copy = (t) => navigator.clipboard?.writeText(t).then(() => toast('Kimásolva'), () => toast('Jelöld ki és másold ki kézzel.', true));
    const cal = el('div', { class: 'card form' },
      el('h2', {}, 'Naptár-feliratkozás'),
      el('p', { class: 'muted', style: 'margin:0' }, 'Ezzel a személyes linkkel az alkalmaid megjelennek a telefonod naptárában (iPhone: Beállítások → Naptár → Fiókok → Feliratkozott naptár; Google Naptár: Egyéb naptárak → URL-ből).'),
      el('div', { class: 'snippet' }, u.calendar_url),
      el('div', { class: 'row' },
        el('button', { class: 'btn small ghost', type: 'button', onclick: () => copy(u.calendar_url) }, 'Link másolása'),
        el('a', { class: 'btn small ghost', href: u.calendar_url.replace(/^https?:/, 'webcal:') }, 'Megnyitás a naptárban'),
        el('button', { class: 'btn small ghost', type: 'button', onclick: async () => {
          if (!confirm('Új linket generálsz? A régi link ezután nem működik.')) return;
          try { state.user = (await api('account/calendar-reset', {}, {})).user; render(); } catch (err) { fail(err); }
        } }, 'Új link')));

    const pw = el('form', { class: 'card form' }, el('h2', {}, 'Jelszó módosítása'),
      field('Jelenlegi jelszó', el('input', { name: 'current', type: 'password', required: true, autocomplete: 'current-password' })),
      field('Új jelszó (min. 8 karakter)', el('input', { name: 'new', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn', type: 'submit' }, 'Jelszó mentése'));
    pw.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('account/password', {}, { current: pw.current.value, new: pw.new.value }); pw.reset(); toast('Jelszó módosítva'); } catch (err) { fail(err); }
    });

    const install = el('div', { class: 'card form' }, el('h2', {}, 'Telepítés a telefonra'),
      el('p', { class: 'muted', style: 'margin:0' }, 'iPhone: Safari → Megosztás → „Főképernyőhöz adás”. Android: Chrome menü → „Alkalmazás telepítése” (vagy az alábbi gomb).'),
      installBtn());

    return el('div', { class: 'grid2' },
      el('div', {}, acc, cal),
      el('div', {}, install, pw, u.is_admin ? adminCard() : null,
        el('button', { class: 'btn ghost block', type: 'button', onclick: async () => { await api('logout', {}, {}); state.user = null; document.querySelector('.fab')?.remove(); showLogin(); } }, 'Kijelentkezés')));
  }

  let deferredInstall = null;
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredInstall = e; });
  function installBtn() {
    if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) return el('p', { class: 'ok', style: 'margin:0' }, 'Az app telepítve van ezen az eszközön.');
    if (!deferredInstall) return null;
    return el('button', { class: 'btn', type: 'button', onclick: async () => { deferredInstall.prompt(); await deferredInstall.userChoice; deferredInstall = null; render(); } }, 'Telepítés');
  }

  function adminCard() {
    const box = el('div', { class: 'card form' }, el('h2', {}, 'Felhasználók (admin)'), el('p', { class: 'muted' }, 'Betöltés…'));
    api('admin/users').then((d) => {
      const o = d.options;
      const opts = el('form', { class: 'form' },
        field('Az app neve', el('input', { name: 'app_name', value: o.app_name })),
        el('label', { class: 'check' }, el('input', { type: 'checkbox', name: 'reg', checked: o.allow_register }), 'Bárki regisztrálhat saját fiókot'),
        el('button', { class: 'btn small', type: 'submit' }, 'Mentés'));
      opts.addEventListener('submit', async (e) => {
        e.preventDefault();
        try { await api('admin/options/save', {}, { app_name: opts.app_name.value, allow_register: opts.reg.checked, send_hour: o.send_hour }); toast('Mentve'); } catch (err) { fail(err); }
      });
      const add = el('form', { class: 'form' },
        el('div', { class: 'grid2' }, field('Név', el('input', { name: 'name', required: true })), field('Email', el('input', { name: 'email', type: 'email', required: true }))),
        field('Kezdő jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
        el('button', { class: 'btn small', type: 'submit' }, 'Felhasználó hozzáadása'));
      add.addEventListener('submit', async (e) => {
        e.preventDefault();
        try { await api('admin/users/save', {}, { name: add.name.value, email: add.email.value, password: add.password.value }); toast('Hozzáadva'); render(); } catch (err) { fail(err); }
      });
      fill(box, el('h2', {}, 'Felhasználók (admin)'),
        el('div', { class: 'list' }, d.users.map((x) => el('div', { class: 'item', style: 'grid-template-columns:1fr auto;cursor:default' },
          el('div', {}, el('div', { class: 'who' }, x.name + (x.is_admin ? ' (admin)' : '')), el('div', { class: 'what' }, x.email + ' · ' + x.events + ' alkalom')),
          x.id === state.user.id ? el('span', { class: 'muted' }, 'te') : el('button', { class: 'btn small ghost', type: 'button', onclick: async () => {
            if (!confirm('Törlöd ' + x.name + ' fiókját és összes alkalmát?')) return;
            try { await api('admin/users/delete', {}, { id: x.id }); render(); } catch (err) { fail(err); }
          } }, 'Törlés')))),
        el('hr'), el('h3', {}, 'Új felhasználó'), add,
        el('hr'), el('h3', {}, 'Beállítások'), opts,
        el('hr'), el('h3', {}, 'Napi emlékeztető-kör'),
        el('p', { class: 'muted', style: 'margin:0' }, 'Legutóbb lefutott: ' + (d.last_run || 'még nem') + '. Időzítés: a GitHub Actions ütemező minden reggel meghívja; tartaléknak a tárhely CRON funkciójában is beállítható ez a cím (naponta egyszer, pl. 7:00):'),
        el('div', { class: 'snippet' }, d.cron_url));
    }).catch(fail);
    return box;
  }

  // ---------------------------------------------------------------- indulás
  async function start() {
    fill(root, el('p', { class: 'muted' }, 'Betöltés…'));
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
    try {
      state.cfg = await api('config');
      const token = new URLSearchParams(location.search).get('reset');
      if (token) return showReset(token);
      const { user } = await api('me');
      state.user = user;
      if (user) openMain(); else showLogin();
    } catch (e) { fail(e); }
  }
  start();
})();
