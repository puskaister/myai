// Ügyintézési Segéd admin: ügyfelek (megrendelések, előfizetések, tudásbázis,
// beépítő kód, befizetések), tanítás (témák szerkesztése és kipróbálása), beállítások, fiók.
(function () {
  'use strict';

  const root = document.getElementById('admin');
  const STATUS = { pending: 'Megrendelve', active: 'Aktív', paused: 'Szüneteltetve' };
  const state = { tab: 'customers', list: null, settings: null, teach: null };

  // ---------------------------------------------------------------- segédek (assets/common.js)
  const { el, fill, toast, field, huDate, copy, fail } = window.Ugy;
  const api = window.Ugy.makeApi(() => showLogin(), ['login']);
  const huf = (n) => Number(n).toLocaleString('hu-HU') + ' Ft';

  function modal(title, body) {
    const back = el('div', { class: 'modal-back', onclick: (e) => { if (e.target === back) close(); } });
    const close = () => { back.remove(); document.removeEventListener('keydown', esc); if (location.hash) history.replaceState(null, '', location.pathname + location.search); };
    const esc = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', esc);
    back.append(el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title, style: 'max-width:760px' },
      el('div', { class: 'row between', style: 'margin-bottom:10px' }, el('h2', { style: 'margin:0' }, title), el('button', { class: 'icon-btn', type: 'button', onclick: close, 'aria-label': 'Bezárás' }, '✕')),
      body));
    document.body.append(back);
    return close;
  }

  // Az előfizetés állapota egy szóban, a lejárattal együtt.
  function subState(c) {
    if (c.status === 'pending') return ['pending', 'Megrendelve'];
    if (c.status === 'paused') return ['paused', 'Szüneteltetve'];
    if (!c.active_now) return ['expired', 'Lejárt'];
    return ['active', 'Aktív · ' + (c.days_left === 0 ? 'ma lejár' : 'még ' + c.days_left + ' nap')];
  }

  // ---------------------------------------------------------------- belépés
  function narrow(title, ...content) {
    fill(root, el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, title), ...content)));
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
      try { await api('reset', {}, { token, password: f.password.value }); history.replaceState(null, '', location.pathname); toast('Az új jelszó beállítva.'); showLogin(); } catch (err) { fail(err); }
    } },
    field('Új jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    field('Még egyszer', el('input', { name: 'password2', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Jelszó beállítása'));
    narrow('Új jelszó', f);
  }

  // ---------------------------------------------------------------- keret
  const TABS = [['customers', 'Ügyfelek'], ['teach', 'Tanítás'], ['settings', 'Beállítások'], ['account', 'Fiók']];
  function render() {
    const tabs = el('nav', { class: 'tabs' }, TABS.map(([k, l]) => el('button', { class: state.tab === k ? 'on' : '', type: 'button', onclick: () => go(k) }, l)));
    fill(root, tabs, { customers: viewCustomers, teach: viewTeach, settings: viewSettings, account: viewAccount }[state.tab]());
  }
  async function go(tab) {
    if (state.tab === 'teach' && tab !== 'teach' && state.teach?.dirty && !confirm('A tanítás nem mentett változásai elvesznek. Továbblépsz?')) return;
    state.tab = tab;
    try {
      if (tab === 'customers' || (tab === 'teach' && !state.list)) state.list = await api('admin/customers');
      if (tab === 'teach' && !state.teach) await loadTeach({ bot: 'my-ai' });
      if (tab === 'settings' || tab === 'account') state.settings = await api('admin/settings');
    } catch (e) { fail(e); }
    render();
  }

  // ---------------------------------------------------------------- ügyfelek
  function viewCustomers() {
    const d = state.list;
    if (!d) return el('p', { class: 'muted' }, 'Betöltés…');
    const active = d.customers.filter((c) => c.active_now).length;
    const pending = d.customers.filter((c) => c.status === 'pending').length;
    const soon = d.customers.filter((c) => c.active_now && c.days_left !== null && c.days_left <= 7).length;
    const r = d.last_result;
    return el('div', {},
      el('div', { class: 'stat' },
        el('div', {}, el('b', {}, active), 'aktív előfizetés'),
        el('div', {}, el('b', {}, huf(d.mrr_net)), 'havi bevétel (nettó)'),
        el('div', {}, el('b', {}, pending), 'új megrendelés'),
        el('div', {}, el('b', {}, soon), '7 napon belül lejár')),
      el('p', { class: 'muted' }, 'Havidíj: ' + d.price + (r ? ' · Napi kör: ' + r.at + ', ' + r.reminders + ' emlékeztető, ' + r.expired + ' lejárati értesítés' + (r.failed ? ', ' + r.failed + ' sikertelen' : '') : ' · A napi kör még nem futott.')),
      d.customers.length
        ? el('div', { class: 'list' }, d.customers.map((c) => {
          const [cls, label] = subState(c);
          return el('button', { class: 'item', type: 'button', onclick: () => openCustomer(c.id), style: 'grid-template-columns:1fr auto' },
            el('div', {},
              el('div', { class: 'who' }, c.name + (c.has_kb ? '' : ' · nincs tudásbázis')),
              el('div', { class: 'what' }, c.contact_name + ' · ' + c.email + (c.paid_until ? ' · érvényes: ' + huDate(c.paid_until) : ''))),
            el('span', { class: 'badge ' + cls }, label));
        }))
        : el('p', { class: 'note' }, 'Még nincs megrendelés. A my-ai.hu Ügyintézési Segéd oldalán lévő űrlapról érkeznek.'));
  }

  async function openCustomer(id) {
    let d;
    try { d = await api('admin/customer', { id }); } catch (e) { return fail(e); }
    const c = d.customer;
    const [cls, label] = subState(c);
    const f = el('form', { class: 'form' },
      el('div', { class: 'grid2' },
        field('Cég neve', el('input', { name: 'name', required: true, value: c.name })),
        field('Kapcsolattartó', el('input', { name: 'contact_name', value: c.contact_name })),
        field('Email', el('input', { name: 'email', type: 'email', required: true, value: c.email })),
        field('Telefon', el('input', { name: 'phone', value: c.phone })),
        field('Weboldal', el('input', { name: 'website', value: c.website })),
        field('Számlázási név', el('input', { name: 'billing_name', value: c.billing_name })),
        field('Számlázási cím', el('input', { name: 'billing_address', value: c.billing_address })),
        field('Adószám', el('input', { name: 'tax_number', value: c.tax_number })),
        field('Állapot', el('select', { name: 'status' }, Object.entries(STATUS).map(([k, l]) => el('option', { value: k, selected: c.status === k }, l)))),
        field('Érvényes eddig', el('input', { name: 'paid_until', type: 'date', value: c.paid_until || '' }), 'Befizetésnél automatikusan hosszabbodik.'),
        field('Buborék színe', el('input', { name: 'color', type: 'color', value: c.color || '#17695f' }))),
      c.message ? el('div', { class: 'note' }, el('strong', {}, 'Megrendelési üzenet: '), c.message) : null,
      field('Tudásbázis (JSON)', el('textarea', { name: 'kb_json', class: 'kb', spellcheck: 'false' }, d.kb_json),
        'A demó szerkesztőjében (my-ai.hu/ugyintezes) összeállított témák „Tudásbázis másolása” gombbal kimásolt formátuma. Mentéskor ellenőrizzük.'),
      field('Belső megjegyzés', el('textarea', { name: 'admin_note' }, c.admin_note || '')),
      el('div', { class: 'row between' },
        el('button', { class: 'btn ghost', type: 'button', style: 'color:var(--danger)', onclick: async () => {
          if (!confirm('Végleg törlöd ' + c.name + ' adatait (befizetések, tudásbázis)?')) return;
          try { await api('admin/customer/delete', {}, { id: c.id }); close(); toast('Törölve'); go('customers'); } catch (e) { fail(e); }
        } }, 'Törlés'),
        el('button', { class: 'btn', type: 'submit' }, 'Mentés')));
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const body = Object.fromEntries(new FormData(f).entries());
      try { await api('admin/customer/save', {}, Object.assign(body, { id: c.id })); toast('Mentve'); close(); go('customers'); } catch (err) { fail(err); }
    });

    const pay = el('div', { class: 'card form', style: 'margin-bottom:14px' },
      el('div', { class: 'row between' }, el('h3', { style: 'margin:0' }, 'Előfizetés'), el('span', { class: 'badge ' + cls }, label)),
      el('p', { class: 'muted', style: 'margin:0' }, 'Befizetés rögzítése (a lejárattól, vagy ha már lejárt, a mai naptól hosszabbít; állapot: aktív):'),
      el('div', { class: 'row' }, [1, 3, 12].map((m) => el('button', { class: 'btn small', type: 'button', onclick: async () => {
        if (!confirm(m + ' hónap befizetését rögzíted (' + c.name + ')?')) return;
        try { const r = await api('admin/payment/add', {}, { id: c.id, months: m, note: 'átutalás' }); toast('Rögzítve, érvényes: ' + huDate(r.paid_until)); close(); openCustomer(c.id); go('customers'); } catch (e) { fail(e); }
      } }, '+ ' + m + ' hónap'))),
      d.payments.length ? el('div', { class: 'list' }, d.payments.map((p) => el('div', { class: 'item', style: 'grid-template-columns:1fr auto;cursor:default' },
        el('div', {}, el('div', { class: 'who' }, p.months + ' hónap · ' + huf(p.amount)), el('div', { class: 'what' }, String(p.created_at).slice(0, 10) + (p.note ? ' · ' + p.note : ''))),
        el('span', { class: 'muted' }, 'eddig: ' + huDate(p.period_until))))) : el('p', { class: 'muted', style: 'margin:0' }, 'Még nincs befizetés.'));

    const embed = el('div', { class: 'card form', style: 'margin-bottom:14px' },
      el('h3', { style: 'margin:0' }, 'Beépítő kód (csak neked — ezt küldd el az ügyfélnek, vagy illeszd be te a weboldalába)'),
      el('div', { class: 'snippet' }, d.embed),
      el('div', { class: 'row' },
        el('button', { class: 'btn small ghost', type: 'button', onclick: () => copy(d.embed) }, 'Kód másolása'),
        el('button', { class: 'btn small', type: 'button', onclick: async () => { close(); state.tab = 'teach'; await loadTeach({ customer: c.id }); render(); } }, 'Tanítás (témák szerkesztése)'),
        c.has_kb ? el('a', { class: 'btn small ghost', href: '../chat.html?u=' + encodeURIComponent(c.slug), target: '_blank', rel: 'noopener' }, 'Chat kipróbálása ↗') : null),
      el('p', { class: 'muted', style: 'margin:0;font-size:.88rem' }, 'Azonosító: ' + c.slug + '. A chat csak aktív, kifizetett előfizetésnél válaszol; lejárat után ezt írja: „A chat jelenleg nem elérhető.”'));

    const portal = el('div', { class: 'card form', style: 'margin-bottom:14px' },
      el('div', { class: 'row between' }, el('h3', { style: 'margin:0' }, 'Ügyfél belépése (saját tanítás)'),
        el('span', { class: 'badge' + (c.has_portal ? ' active' : '') }, c.has_portal ? 'Van belépése' : 'Még nincs belépése')),
      el('p', { class: 'muted', style: 'margin:0' }, 'Az ügyfél a saját email címével lép be a ' + location.origin + location.pathname.replace(/admin\/.*$/, 'fiok/') + ' oldalon, és csak a saját chatbotját látja és tanítja.'),
      el('div', { class: 'row' }, el('button', { class: 'btn small', type: 'button', onclick: async () => {
        if (!confirm('Elküldöd a belépési linket ide: ' + c.email + '?')) return;
        try { const r = await api('admin/customer/invite', {}, { id: c.id }); toast('Elküldve: ' + r.email); } catch (e) { fail(e); }
      } }, c.has_portal ? 'Új jelszó link küldése' : 'Belépési link küldése')));

    const close = modal(c.name, el('div', {}, pay, embed, portal, f));
  }


  // ---------------------------------------------------------------- tanítás (assets/teach.js)
  async function loadTeach(who) {
    try {
      const d = await api('admin/kb', who.customer ? { customer: who.customer } : { bot: 'my-ai' });
      const prev = state.teach && state.teach.who.customer === who.customer ? state.teach : null;
      state.teach = Object.assign(window.UgyTeach.prepare(d, prev), { who });
    } catch (e) { fail(e); }
  }

  function viewTeach() {
    const T = state.teach;
    if (!T) return el('p', { class: 'muted' }, 'Betöltés…');
    const customers = state.list ? state.list.customers : [];
    const pick = el('select', { 'aria-label': 'Melyik chatbotot tanítod?', onchange: async () => {
      if (T.dirty && !confirm('A nem mentett változások elvesznek. Másik chatbotra váltasz?')) { pick.value = T.who.customer ? 'c' + T.who.customer : 'my-ai'; return; }
      const v = pick.value;
      await loadTeach(v === 'my-ai' ? { bot: 'my-ai' } : { customer: Number(v.slice(1)) });
      render();
    } },
    el('option', { value: 'my-ai', selected: !T.who.customer }, 'my-ai.hu – a weboldal chatbotja'),
    customers.map((c) => el('option', { value: 'c' + c.id, selected: T.who.customer === c.id }, c.name + ' (' + c.slug + ')')));
    const own = !T.who.customer;
    return window.UgyTeach.view(T, {
      render,
      top: el('div', { class: 'row', style: 'align-items:flex-end' },
        el('div', { style: 'flex:1;min-width:220px' }, field('Melyik chatbotot tanítod?', pick)),
        own ? el('span', { class: 'badge' + (T.d.source === 'db' ? ' active' : '') }, T.d.source === 'db' ? 'Tanított változat' : 'Alap tudástár') : null),
      save: (kb) => api('admin/kb/save', {}, Object.assign(own ? { bot: 'my-ai' } : { customer: T.who.customer }, { kb })),
      reload: () => loadTeach(T.who),
      reset: own && T.d.source === 'db' ? () => api('admin/kb/reset', {}, { bot: 'my-ai' }) : null,
    });
  }

  // ---------------------------------------------------------------- beállítások
  function viewSettings() {
    const s = state.settings;
    if (!s) return el('p', { class: 'muted' }, 'Betöltés…');
    const o = s.options;
    const f = el('form', { class: 'card form' },
      el('h2', {}, 'Díj és értesítések'),
      el('div', { class: 'grid2' },
        field('Havidíj (nettó Ft)', el('input', { name: 'price_net', type: 'number', min: 0, value: o.price_net })),
        field('ÁFA (%)', el('input', { name: 'vat_percent', type: 'number', min: 0, max: 50, value: o.vat_percent }))),
      field('Értesítési email (megrendelések, lejáratok)', el('input', { name: 'admin_email', type: 'email', value: o.admin_email })),
      field('Fizetési információ az emlékeztetőkben', el('textarea', { name: 'payment_info', placeholder: 'pl. Kedvezményezett: …\nSzámlaszám: …\nKözlemény: az ügyfél neve' }, o.payment_info),
        'Ha üres, az emlékeztető azt írja, hogy a díjbekérőt emailben küldöd.'),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('admin/settings/save', {}, { price_net: Number(f.price_net.value), vat_percent: Number(f.vat_percent.value), admin_email: f.admin_email.value, payment_info: f.payment_info.value });
        toast('Mentve'); go('settings');
      } catch (err) { fail(err); }
    });
    return el('div', { class: 'grid2' }, f,
      el('div', { class: 'card form' },
        el('h2', {}, 'Napi lejárati kör'),
        el('p', { class: 'muted', style: 'margin:0' }, 'A lejárat előtt 7 és 1 nappal emlékeztető megy az ügyfélnek, lejáratkor neki és neked is értesítés. A GitHub Actions ütemező minden reggel meghívja; tartaléknak a tárhely CRON funkciójában is beállítható ez a cím:'),
        el('div', { class: 'snippet' }, s.cron_url)));
  }

  // ---------------------------------------------------------------- fiók
  function viewAccount() {
    const s = state.settings;
    if (!s) return el('p', { class: 'muted' }, 'Betöltés…');
    const pw = el('form', { class: 'card form' }, el('h2', {}, 'Jelszó módosítása'),
      field('Jelenlegi jelszó', el('input', { name: 'current', type: 'password', required: true, autocomplete: 'current-password' })),
      field('Új jelszó (min. 8 karakter)', el('input', { name: 'new', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn', type: 'submit' }, 'Jelszó mentése'));
    pw.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('admin/password', {}, { current: pw.current.value, new: pw.new.value }); pw.reset(); toast('Jelszó módosítva'); } catch (err) { fail(err); }
    });
    return el('div', { class: 'grid2' },
      el('div', { class: 'card' }, el('h2', {}, s.me.name), el('p', { class: 'muted' }, s.me.email),
        el('button', { class: 'btn ghost', type: 'button', onclick: async () => { await api('logout', {}, {}); showLogin(); } }, 'Kijelentkezés')),
      pw);
  }

  // ---------------------------------------------------------------- indulás
  async function start() {
    fill(root, el('p', { class: 'muted' }, 'Betöltés…'));
    try {
      const { admin } = await api('me');
      if (!admin) return showLogin();
      await go('customers');
      const m = location.hash.match(/^#c(\d+)$/); // email linkből
      if (m) openCustomer(Number(m[1]));
    } catch (e) { fail(e); }
  }
  const resetToken = new URLSearchParams(location.search).get('reset');
  if (resetToken) showReset(resetToken);
  else start();
})();
