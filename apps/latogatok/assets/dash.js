// Látogatószámláló irányítópult: áttekintés, látogatások, beállítások, fiók.
(function () {
  'use strict';

  const root = document.getElementById('admin');
  const MONTHS = ['jan.', 'febr.', 'márc.', 'ápr.', 'máj.', 'jún.', 'júl.', 'aug.', 'szept.', 'okt.', 'nov.', 'dec.'];
  const RANGES = [[1, 'Ma'], [7, '7 nap'], [30, '30 nap'], [90, '90 nap']];
  const state = { tab: 'overview', days: 7, stats: null, visits: [], q: '', settings: null, more: false };

  // ---------------------------------------------------------------- segédek
  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else if (k === 'class') node.className = v;
      else if (k === 'value') node.value = v;
      else if (k === 'checked') node.checked = !!v;
      else if (k === 'style' && typeof v === 'object') Object.assign(node.style, v);
      else node.setAttribute(k, v === true ? '' : v);
    }
    for (const c of children.flat()) {
      if (c === null || c === undefined || c === false) continue;
      node.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return node;
  }

  async function api(route, params, body) {
    const qs = new URLSearchParams(Object.assign({ r: route }, params || {}));
    const opts = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
    if (body) { opts.method = 'POST'; opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    let res, data;
    try {
      res = await fetch('api/?' + qs.toString(), opts);
      data = await res.json();
    } catch (e) { throw Object.assign(new Error('Nem sikerült kapcsolódni a szerverhez.'), { status: 0 }); }
    if (res.status === 401 && route !== 'login') { showLogin(); throw Object.assign(new Error(''), { status: 401, silent: true }); }
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
  const nf = (n) => Number(n).toLocaleString('hu-HU');
  const dur = (s) => { s = Math.round(s); return s < 60 ? s + ' mp' : Math.floor(s / 60) + ' p ' + String(s % 60).padStart(2, '0') + ' mp'; };
  const shortDay = (d) => { const x = new Date(d + 'T12:00:00'); return MONTHS[x.getMonth()] + ' ' + x.getDate() + '.'; };
  const when = (dt) => String(dt).slice(5, 16).replace('-', '.').replace(' ', ' ');

  function modal(title, body) {
    const back = el('div', { class: 'modal-back', onclick: (e) => { if (e.target === back) close(); } });
    const close = () => { back.remove(); document.removeEventListener('keydown', esc); };
    const esc = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', esc);
    back.append(el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      el('div', { class: 'row between', style: 'margin-bottom:10px' }, el('h2', { style: 'margin:0' }, title), el('button', { class: 'icon-btn', type: 'button', onclick: close, 'aria-label': 'Bezárás' }, '✕')),
      body));
    document.body.append(back);
    return close;
  }

  // ---------------------------------------------------------------- belépés
  function narrow(title, ...content) {
    root.replaceChildren(el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, title), ...content)));
  }
  function showLogin() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { await api('login', {}, { email: f.email.value, password: f.password.value }); start(); } catch (err) { fail(err); }
    } },
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    field('Jelszó', el('input', { name: 'password', type: 'password', required: true, autocomplete: 'current-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Belépés'),
    el('button', { class: 'link-btn', type: 'button', onclick: showForgot }, 'Elfelejtett jelszó?'));
    narrow('Belépés', f);
  }
  function showForgot() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try {
        await api('forgot', {}, { email: f.email.value });
        f.replaceChildren(el('p', { class: 'ok' }, 'Ha ezzel az email címmel van fiók, elküldtük a jelszó-visszaállító linket (1 óráig érvényes).'),
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

  // ---------------------------------------------------------------- keret
  const TABS = [['overview', 'Áttekintés'], ['visits', 'Látogatások'], ['settings', 'Beállítások'], ['account', 'Fiók']];
  function render() {
    const tabs = el('nav', { class: 'tabs' }, TABS.map(([k, l]) => el('button', { class: state.tab === k ? 'on' : '', type: 'button', onclick: () => go(k) }, l)));
    const view = { overview: viewOverview, visits: viewVisits, settings: viewSettings, account: viewAccount }[state.tab]();
    root.replaceChildren(tabs, view);
  }
  async function go(tab) {
    state.tab = tab;
    try {
      if (tab === 'overview') state.stats = await api('admin/stats', { days: state.days });
      if (tab === 'visits') await loadVisits(false);
      if (tab === 'settings' || tab === 'account') state.settings = await api('admin/settings');
    } catch (e) { fail(e); }
    render();
  }
  function rangePicker() {
    return el('div', { class: 'range', role: 'group', 'aria-label': 'Időszak' }, RANGES.map(([d, l]) =>
      el('button', { type: 'button', class: state.days === d ? 'on' : '', 'aria-pressed': String(state.days === d), onclick: () => { state.days = d; go(state.tab); } }, l)));
  }

  // ---------------------------------------------------------------- áttekintés
  function chart(series, hourly) {
    const max = Math.max(1, ...series.map((p) => p.pv));
    const wrap = el('div', { class: 'chart' });
    const tip = el('div', { class: 'tip', hidden: true });
    const show = (col, p) => {
      tip.replaceChildren(el('strong', {}, hourly ? p.label : shortDay(p.label)), el('br'), nf(p.pv) + ' megtekintés · ' + nf(p.uv) + ' látogató');
      tip.hidden = false;
      const r = col.getBoundingClientRect();
      const w = wrap.getBoundingClientRect();
      tip.style.left = Math.min(Math.max(r.left - w.left + r.width / 2, 70), w.width - 70) + 'px';
      tip.style.top = (col.querySelector('.bar').getBoundingClientRect().top - w.top - 6) + 'px';
    };
    const plot = el('div', { class: 'chart-plot' }, series.map((p) => {
      const col = el('div', { class: 'chart-col', tabindex: '0', role: 'img',
        'aria-label': (hourly ? p.label : shortDay(p.label)) + ': ' + p.pv + ' megtekintés, ' + p.uv + ' látogató' },
      el('div', { class: 'bar' + (p.pv ? '' : ' zero'), style: { height: (p.pv / max * 100) + '%' } }));
      col.addEventListener('mouseenter', () => show(col, p));
      col.addEventListener('focus', () => show(col, p));
      col.addEventListener('mouseleave', () => { tip.hidden = true; });
      col.addEventListener('blur', () => { tip.hidden = true; });
      return col;
    }));
    const lab = (p) => (hourly ? p.label : shortDay(p.label));
    const mid = series[Math.floor(series.length / 2)];
    wrap.append(el('div', { class: 'chart-max' }, nf(max)), plot,
      el('div', { class: 'chart-axis' }, el('span', {}, lab(series[0])), series.length > 2 ? el('span', {}, lab(mid)) : null, el('span', {}, lab(series[series.length - 1]))),
      tip);
    return wrap;
  }

  function toplist(title, rows, fmt) {
    const max = Math.max(1, ...rows.map((r) => Number(r.n)));
    return el('div', { class: 'card' }, el('h3', {}, title),
      rows.length ? el('ol', { class: 'toplist' }, rows.map((r) => el('li', { title: r.href || r.name },
        el('span', { class: 'fill', style: { width: (r.n / max * 100) + '%' }, 'aria-hidden': 'true' }),
        el('span', { class: 'name' }, r.name || '(üres)'),
        el('span', { class: 'n' }, fmt ? fmt(r) : nf(r.n)))))
        : el('p', { class: 'muted' }, 'Nincs adat.'));
  }

  function viewOverview() {
    const s = state.stats;
    if (!s) return el('p', { class: 'muted' }, 'Betöltés…');
    const t = s.totals;
    const hourly = s.days === 1;
    const tile = (v, k) => el('div', { class: 'tile' }, el('div', { class: 'v' }, v), el('div', { class: 'k' }, k));
    return el('div', {},
      el('div', { class: 'row between', style: 'margin-bottom:14px' }, rangePicker(),
        el('button', { class: 'btn small ghost', type: 'button', onclick: () => go('overview') }, 'Frissítés')),
      el('div', { class: 'tiles' },
        tile(nf(t.visitors), 'egyedi látogató'),
        tile(nf(t.pageviews), 'oldalmegtekintés'),
        tile(nf(t.new), 'új látogató'),
        tile(dur(t.avg_seconds), 'átlagos idő / oldal'),
        tile(nf(t.clicks), 'kattintás')),
      el('div', { class: 'card' },
        el('h3', {}, hourly ? 'Oldalmegtekintések óránként (ma)' : 'Oldalmegtekintések naponta'),
        chart(s.series, hourly),
        el('details', { style: 'margin-top:12px' }, el('summary', { class: 'muted' }, 'Adatok táblázatként'),
          el('div', { class: 'table-wrap' }, el('table', { class: 'visits' },
            el('thead', {}, el('tr', {}, el('th', {}, hourly ? 'Óra' : 'Nap'), el('th', { class: 'num' }, 'Megtekintés'), el('th', { class: 'num' }, 'Látogató'))),
            el('tbody', {}, s.series.slice().reverse().map((p) => el('tr', { style: 'cursor:default' },
              el('td', {}, hourly ? p.label : shortDay(p.label)), el('td', { class: 'num' }, nf(p.pv)), el('td', { class: 'num' }, nf(p.uv))))))))),
      el('div', { class: 'tops' },
        toplist('Legnézettebb oldalak', s.pages, (r) => nf(r.n) + ' · ' + dur(r.avg_s)),
        toplist('Honnan jöttek', s.referrers),
        toplist('Mire kattintottak', s.clicks),
        toplist('Böngészők', s.browsers),
        toplist('Rendszerek', s.os),
        toplist('Eszközök', s.devices)));
  }

  // ---------------------------------------------------------------- látogatások
  async function loadVisits(append) {
    const { visits } = await api('admin/visits', { days: state.days, q: state.q, offset: append ? state.visits.length : 0 });
    state.visits = append ? state.visits.concat(visits) : visits;
    state.more = visits.length === 100;
  }

  function viewVisits() {
    const search = el('input', { type: 'search', placeholder: 'IP-cím, látogató-azonosító vagy oldal…', value: state.q, style: 'flex:1;min-width:200px' });
    const doSearch = () => { state.q = search.value.trim(); go('visits'); };
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') doSearch(); });
    return el('div', {},
      el('div', { class: 'row between', style: 'margin-bottom:14px' }, rangePicker(),
        el('div', { class: 'row', style: 'flex:1;justify-content:flex-end' }, search, el('button', { class: 'btn small ghost', type: 'button', onclick: doSearch }, 'Keresés'))),
      state.visits.length ? el('div', { class: 'card table-wrap', style: 'padding:6px' }, el('table', { class: 'visits' },
        el('thead', {}, el('tr', {},
          el('th', {}, 'Idő'), el('th', {}, 'IP'), el('th', { class: 'hide-sm' }, 'Böngésző'), el('th', {}, 'Oldal'),
          el('th', { class: 'num' }, 'Eltöltött idő'), el('th', { class: 'num' }, 'Katt.'))),
        el('tbody', {}, state.visits.map((v) => el('tr', { tabindex: '0', onclick: () => openVisit(v.id), onkeydown: (e) => { if (e.key === 'Enter') openVisit(v.id); } },
          el('td', { style: 'white-space:nowrap' }, when(v.started_at), v.is_new ? el('span', { class: 'badge confirmed', style: 'margin-left:6px' }, 'új') : null),
          el('td', { style: 'white-space:nowrap' }, v.ip || '–'),
          el('td', { class: 'hide-sm' }, v.browser + (v.browser_version ? ' ' + v.browser_version : ''), el('div', { class: 'muted', style: 'font-size:.8rem' }, v.os + ' · ' + v.device)),
          el('td', { class: 'path', title: v.path }, v.path),
          el('td', { class: 'num' }, v.active_seconds ? dur(v.active_seconds) : '–'),
          el('td', { class: 'num' }, v.clicks)))))) : el('p', { class: 'note' }, 'Ebben az időszakban nincs látogatás.'),
      state.more ? el('div', { class: 'row', style: 'justify-content:center;margin-top:12px' },
        el('button', { class: 'btn ghost', type: 'button', onclick: async () => { try { await loadVisits(true); render(); } catch (e) { fail(e); } } }, 'Továbbiak')) : null);
  }

  async function openVisit(id) {
    let d;
    try { d = await api('admin/visit', { id }); } catch (e) { return fail(e); }
    const v = d.visit;
    const row = (k, val) => [el('dt', {}, k), el('dd', {}, val || '–')];
    modal('Látogatás', el('div', {},
      el('dl', { class: 'kv' },
        row('Időpont', String(v.started_at).slice(0, 16)),
        row('IP-cím', v.ip),
        row('Oldal', v.path + (v.title ? ' — ' + v.title : '')),
        row('Eltöltött idő', v.active_seconds ? dur(v.active_seconds) : ''),
        row('Kattintások', String(v.clicks)),
        row('Böngésző', v.browser + ' ' + v.browser_version),
        row('Rendszer, eszköz', v.os + ' · ' + v.device + (v.screen ? ' · ' + v.screen : '')),
        row('Nyelv', v.lang),
        row('Honnan jött', v.referrer),
        row('Látogató', v.visitor_id + (Number(v.is_new) ? ' (első látogatás)' : ''))),
      el('h3', {}, 'Kattintások'),
      d.clicks.length ? el('ol', { class: 'toplist' }, d.clicks.map((c) => el('li', { title: c.href },
        el('span', { class: 'name' }, (c.label || c.tag) + (c.href ? ' → ' + c.href.replace(/^https?:\/\//, '') : '')),
        el('span', { class: 'n' }, String(c.created_at).slice(11, 19))))) : el('p', { class: 'muted' }, 'Nem kattintott.'),
      d.journey.length > 1 ? [el('h3', { style: 'margin-top:14px' }, 'A látogató oldalai'),
        el('ol', { class: 'toplist' }, d.journey.map((j) => el('li', {},
          el('span', { class: 'name' }, when(j.started_at) + '  ' + j.path),
          el('span', { class: 'n' }, (j.active_seconds ? dur(j.active_seconds) : '–') + ' · ' + j.clicks + ' katt.'))))] : null,
      el('hr'),
      el('button', { class: 'btn small ghost', type: 'button', onclick: () => { document.querySelector('.modal-back')?.remove(); state.q = v.visitor_id; go('visits'); } }, 'Minden látogatása')));
  }

  // ---------------------------------------------------------------- beállítások
  function viewSettings() {
    const s = state.settings;
    const o = s.options;
    const snippet = '<script src="' + s.base_url + '/consent.js" data-privacy="/adatkezeles/" defer></script>\n<script src="' + s.base_url + '/t.js" defer></script>';
    const ips = el('textarea', { name: 'ips', placeholder: 'Soronként egy IP-cím' }, o.exclude_ips.join('\n'));
    const f = el('form', { class: 'card form' },
      el('h2', {}, 'Mérés'),
      field('A weboldal neve', el('input', { name: 'site', value: o.site_name })),
      el('label', { class: 'check' }, el('input', { type: 'checkbox', name: 'anon', checked: o.anonymize_ip }), 'IP-címek anonimizálása (az utolsó számjegycsoport nullázva)'),
      field('Kizárt IP-címek (ezekről nem mér — pl. a saját géped)', ips,
        'A te mostani IP-címed: ' + s.my_ip),
      el('button', { class: 'link-btn', type: 'button', onclick: () => { if (!ips.value.split('\n').includes(s.my_ip)) ips.value = (ips.value.trim() + '\n' + s.my_ip).trim(); } }, '+ saját IP hozzáadása'),
      field('Adatok megőrzése (nap)', el('input', { name: 'ret', type: 'number', min: 30, max: 1100, value: o.retention_days }), 'Ennél régebbi látogatások automatikusan törlődnek.'),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('admin/settings', {}, { site_name: f.site.value, anonymize_ip: f.anon.checked, exclude_ips: ips.value.split('\n'), retention_days: Number(f.ret.value) });
        state.settings = await api('admin/settings');
        toast('Mentve');
        render();
      } catch (err) { fail(err); }
    });
    return el('div', { class: 'grid2' }, f,
      el('div', {},
        el('div', { class: 'card form' },
          el('h2', {}, 'Beillesztés weboldalba'),
          el('p', { class: 'muted', style: 'margin:0' }, 'Tedd ezt a két sort minden mérni kívánt oldal <head> részébe. Az első a hozzájárulás-sáv (a data-privacy az adatkezelési tájékoztató címe) — mérés csak elfogadás után indul. Ha az oldal maga kér hozzájárulást, az elsőt hagyd el, a másodikhoz add hozzá: data-consent="off".'),
          el('div', { class: 'note', style: 'font-family:ui-monospace,monospace;font-size:.85rem;word-break:break-all;user-select:all;white-space:pre-wrap' }, snippet)),
        el('div', { class: 'card form' },
          el('h2', {}, 'Adatok törlése'),
          el('p', { class: 'muted', style: 'margin:0' }, 'Minden eddigi látogatás és kattintás végleges törlése.'),
          el('button', { class: 'btn danger', type: 'button', onclick: async () => {
            if (!confirm('Biztosan törlöd az összes látogatási adatot? Ez nem vonható vissza.')) return;
            try { await api('admin/reset-data', {}, {}); toast('Minden adat törölve'); } catch (err) { fail(err); }
          } }, 'Minden adat törlése'))));
  }

  // ---------------------------------------------------------------- fiók
  function viewAccount() {
    const s = state.settings;
    const pw = el('form', { class: 'card form' }, el('h2', {}, 'Jelszó módosítása'),
      field('Jelenlegi jelszó', el('input', { name: 'current', type: 'password', required: true, autocomplete: 'current-password' })),
      field('Új jelszó (min. 8 karakter)', el('input', { name: 'new', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn', type: 'submit' }, 'Jelszó mentése'));
    pw.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('admin/password', {}, { current: pw.current.value, new: pw.new.value }); pw.reset(); toast('Jelszó módosítva'); } catch (err) { fail(err); }
    });
    const add = el('form', { class: 'form' },
      el('div', { class: 'grid2' }, field('Név', el('input', { name: 'name', required: true })), field('Email', el('input', { name: 'email', type: 'email', required: true }))),
      field('Jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn small', type: 'submit' }, 'Admin hozzáadása'));
    add.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('admin/admins/save', {}, { name: add.name.value, email: add.email.value, password: add.password.value }); go('account'); toast('Admin hozzáadva'); } catch (err) { fail(err); }
    });
    return el('div', { class: 'grid2' },
      el('div', {},
        el('div', { class: 'card' }, el('h2', {}, s.me.name), el('p', { class: 'muted' }, s.me.email),
          el('button', { class: 'btn ghost', type: 'button', onclick: async () => { await api('logout', {}, {}); showLogin(); } }, 'Kijelentkezés')),
        pw),
      el('div', { class: 'card form' }, el('h2', {}, 'Adminok'),
        el('div', { class: 'list' }, s.admins.map((a) => el('div', { class: 'item', style: 'grid-template-columns:1fr auto;cursor:default' },
          el('div', {}, el('div', { class: 'who' }, a.name), el('div', { class: 'what' }, a.email)),
          a.id === s.me.id ? el('span', { class: 'muted' }, 'te') : el('button', { class: 'btn small ghost', type: 'button', onclick: async () => {
            if (!confirm('Törlöd ' + a.name + ' hozzáférését?')) return;
            try { await api('admin/admins/delete', {}, { id: a.id }); go('account'); } catch (e) { fail(e); }
          } }, 'Törlés')))),
        el('hr'), el('h3', {}, 'Új admin'), add));
  }

  // ---------------------------------------------------------------- indulás
  async function start() {
    root.replaceChildren(el('p', { class: 'muted' }, 'Betöltés…'));
    try {
      const { admin } = await api('me');
      if (!admin) return showLogin();
      go('overview');
    } catch (e) { fail(e); }
  }

  const resetToken = new URLSearchParams(location.search).get('reset');
  if (resetToken) showReset(resetToken);
  else start();
})();
