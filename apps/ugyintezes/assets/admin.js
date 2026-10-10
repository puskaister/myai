// Ügyintézési Segéd admin: ügyfelek (megrendelések, előfizetések, tudásbázis,
// beépítő kód, befizetések), tanítás (témák szerkesztése és kipróbálása), beállítások, fiók.
(function () {
  'use strict';

  const root = document.getElementById('admin');
  const STATUS = { pending: 'Megrendelve', active: 'Aktív', paused: 'Szüneteltetve' };
  const state = { tab: 'customers', list: null, settings: null, teach: null };

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

  // Gyerekek cseréje: listákat kilapít, az üres (null/false) elemeket kihagyja.
  function fill(node, ...kids) {
    node.replaceChildren(...kids.flat(Infinity).filter((k) => k !== null && k !== undefined && k !== false));
  }

  async function api(route, params, body) {
    const qs = new URLSearchParams(Object.assign({ r: route }, params || {}));
    const opts = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
    if (body) { opts.method = 'POST'; opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    let res, data;
    try {
      res = await fetch('../api/?' + qs.toString(), opts);
      data = await res.json();
    } catch (e) { throw Object.assign(new Error('Nem sikerült kapcsolódni a szerverhez.'), { status: 0 }); }
    if (res.status === 401 && route !== 'login') { showLogin(); throw Object.assign(new Error(''), { silent: true }); }
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
  const huDate = (d) => (d ? String(d).replace(/-/g, '. ') + '.' : '–');
  const huf = (n) => Number(n).toLocaleString('hu-HU') + ' Ft';
  const copy = (t) => navigator.clipboard?.writeText(t).then(() => toast('Kimásolva'), () => toast('Jelöld ki és másold ki kézzel.', true));

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

    const close = modal(c.name, el('div', {}, pay, embed, f));
  }


  // ---------------------------------------------------------------- tanítás
  // A chatbot témái (kulcsszavak → válasz) szerkesztése és kipróbálása. A mentett
  // tudástárat a chat azonnal használja. Az egyeztetés ugyanaz, mint a chat.html-ben.
  const norm = (s) => String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
  function matchTopics(temak, q) {
    const nq = ' ' + norm(q) + ' ';
    return temak.map((t, i) => {
      let score = 0;
      const hits = [];
      (t.keys || []).forEach((k) => { const nk = norm(k); if (nk && nq.indexOf(nk) > -1) { score += nk.indexOf(' ') > -1 ? 3 : 2; hits.push(k); } });
      if (t.title && nq.indexOf(norm(t.title)) > -1) { score += 3; hits.push('cím'); }
      if (score > 0) score += Number(t.elsobbseg) || 0;
      return { i, t, score, hits };
    }).filter((x) => x.score > 0).sort((a, b) => b.score - a.score);
  }
  const PRIO = [[-1, 'Alacsony (pl. köszönés)'], [0, 'Normál'], [1, 'Magasabb (pl. ár, határidő)'], [2, 'Kiemelt']];
  const STOP = new Set('a az és meg is hogy van nem mi mit mik ki kik egy de ha vagy kell lehet tud tudok tudsz mennyi hogyan milyen mikor hol miért szeretnék szeretnek kérem kérlek'.split(' '));

  async function loadTeach(who) {
    try {
      const d = await api('admin/kb', who.customer ? { customer: who.customer } : { bot: 'my-ai' });
      const kb = JSON.parse(JSON.stringify(d.kb || {}));
      kb.temak = Array.isArray(kb.temak) ? kb.temak : [];
      kb.temak.forEach((t) => { t.keys = Array.isArray(t.keys) ? t.keys : []; });
      state.teach = { who, d, kb, dirty: false, open: -1, filter: '', test: '' };
    } catch (e) { fail(e); }
  }

  function viewTeach() {
    const T = state.teach;
    if (!T) return el('p', { class: 'muted' }, 'Betöltés…');
    const kb = T.kb;
    const dirty = () => { if (!T.dirty) { T.dirty = true; saveBar.classList.add('dirty'); status.textContent = 'Nem mentett változások'; } };
    const rerender = () => render();

    // melyik chatbot
    const customers = state.list ? state.list.customers : [];
    const pick = el('select', { 'aria-label': 'Melyik chatbotot tanítod?', onchange: async () => {
      if (T.dirty && !confirm('A nem mentett változások elvesznek. Másik chatbotra váltasz?')) { pick.value = T.who.customer ? 'c' + T.who.customer : 'my-ai'; return; }
      const v = pick.value;
      await loadTeach(v === 'my-ai' ? { bot: 'my-ai' } : { customer: Number(v.slice(1)) });
      render();
    } },
    el('option', { value: 'my-ai', selected: !T.who.customer }, 'my-ai.hu – a weboldal chatbotja'),
    customers.map((c) => el('option', { value: 'c' + c.id, selected: T.who.customer === c.id }, c.name + ' (' + c.slug + ')')));

    const head = el('div', { class: 'card form' },
      el('div', { class: 'row between' },
        el('h2', { style: 'margin:0' }, 'Tanítás'),
        el('div', { class: 'row' },
          T.d.source === 'db' && !T.who.customer ? el('span', { class: 'badge active' }, 'Tanított változat') : (!T.who.customer ? el('span', { class: 'badge' }, 'Alap tudástár') : null),
          el('a', { class: 'btn small ghost', href: T.d.chat_url, target: '_blank', rel: 'noopener' }, 'Chat megnyitása ↗'))),
      field('Melyik chatbotot tanítod?', pick),
      el('p', { class: 'muted', style: 'margin:0' }, 'A chatbot a kérdésben lévő kulcsszavak alapján választ témát, és a téma válaszát adja. Ha egy kérdésre rosszul vagy nem válaszol, próbáld ki lent, és adj hozzá kulcsszót vagy új témát. Mentés után a chat azonnal az új tudással válaszol.'),
      T.who.customer && !T.d.live ? el('p', { class: 'alert', style: 'margin:0' }, 'Ennek az ügyfélnek nincs aktív előfizetése, ezért a chatje most nem válaszol a weboldalán (a tanítás ettől még menthető).') : null);

    // kipróbálás
    const testOut = el('div', { class: 'note', 'aria-live': 'polite' });
    const runTest = () => {
      const q = testIn.value.trim();
      T.test = q;
      if (!q) return fill(testOut, el('span', { class: 'muted' }, 'Írj be egy kérdést, ahogy egy látogató kérdezné.'));
      const res = matchTopics(kb.temak, q);
      if (!res.length) {
        const words = [...new Set(norm(q).split(' ').filter((w) => w.length > 3 && !STOP.has(w)))].slice(0, 4);
        return fill(testOut,
          el('p', { style: 'margin:0 0 6px' }, el('strong', {}, 'Erre nem tudna válaszolni'), ' — ezt mondaná: „' + (kb.nem_ertem || 'Ezt sajnos nem értettem.') + '”'),
          el('button', { class: 'btn small', type: 'button', onclick: () => {
            kb.temak.push({ id: '', title: q.replace(/[?!.]+$/, ''), keys: words, answer: '' });
            T.open = kb.temak.length - 1; T.filter = ''; T.dirty = true; rerender();
            setTimeout(() => document.querySelector('.topic.open textarea')?.focus(), 50);
          } }, 'Új téma ebből a kérdésből'));
      }
      const best = res[0];
      const more = res.slice(1, 3).filter((x) => x.score >= best.score - 1);
      fill(testOut,
        el('p', { style: 'margin:0 0 4px' }, 'Válasz: ', el('strong', {}, best.t.title), el('span', { class: 'muted' }, ' · találat: ' + best.hits.join(', ') + ' · pont: ' + best.score)),
        el('div', { style: 'white-space:pre-wrap;margin:6px 0' }, best.t.answer || '(még nincs válasz)'),
        more.length ? el('p', { class: 'muted', style: 'margin:0' }, 'Kapcsolódó (felajánlja): ' + more.map((x) => x.t.title + ' (' + x.score + ')').join(' · ')) : null,
        res.length > 1 ? el('p', { class: 'muted', style: 'margin:4px 0 0;font-size:.85rem' }, 'Rossz témát választott? Adj a jó témának pontosabb (többszavas) kulcsszót, vagy emeld az elsőbbségét.') : null,
        el('button', { class: 'btn small ghost', type: 'button', style: 'margin-top:6px', onclick: () => { T.open = best.i; T.filter = ''; rerender(); } }, 'Téma szerkesztése'));
    };
    const testIn = el('input', { id: 'kb-test', placeholder: 'pl. mennyibe kerül egy weboldal?', value: T.test, oninput: runTest });
    const test = el('div', { class: 'card form' }, el('h3', { style: 'margin:0' }, 'Kipróbálás'), testIn, testOut);
    runTest();

    // általános
    const gen = el('details', { class: 'card form' },
      el('summary', { style: 'font-weight:700;cursor:pointer' }, 'Általános: név, üdvözlés, ha nem érti'),
      el('div', { class: 'grid2' },
        field('A chat neve (fejléc)', el('input', { value: kb.nev || '', oninput: (e) => { kb.nev = e.target.value; dirty(); } })),
        field('Szín', el('input', { type: 'color', value: kb.szin || '#4f46e5', oninput: (e) => { kb.szin = e.target.value; dirty(); } }))),
      field('Logó címe (nem kötelező)', el('input', { value: kb.logo || '', placeholder: '/assets/logo.svg vagy https://…', oninput: (e) => { kb.logo = e.target.value; dirty(); } })),
      field('Üdvözlés', el('textarea', { oninput: (e) => { kb.udvozles = e.target.value; dirty(); } }, kb.udvozles || '')),
      field('Ha nem érti a kérdést', el('textarea', { oninput: (e) => { kb.nem_ertem = e.target.value; dirty(); } }, kb.nem_ertem || '')));

    // témák
    const f = norm(T.filter);
    const shown = kb.temak.map((t, i) => [t, i]).filter(([t]) => !f || norm(t.title + ' ' + t.keys.join(' ') + ' ' + t.answer).includes(f));
    const move = (i, d) => { const j = i + d; if (j < 0 || j >= kb.temak.length) return; [kb.temak[i], kb.temak[j]] = [kb.temak[j], kb.temak[i]]; if (T.open === i) T.open = j; T.dirty = true; rerender(); };
    const topic = ([t, i]) => {
      const isOpen = T.open === i;
      const headRow = el('div', { class: 'row between', style: 'gap:6px' },
        el('button', { class: 'link-btn', type: 'button', style: 'text-align:left;flex:1;min-width:0;text-decoration:none;color:inherit', 'aria-expanded': isOpen ? 'true' : 'false', onclick: () => { T.open = isOpen ? -1 : i; rerender(); } },
          el('strong', {}, (i < 4 ? '★ ' : '') + (t.title || '(új téma)')),
          el('span', { class: 'muted', style: 'display:block;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap' }, t.keys.length ? t.keys.join(', ') : 'nincs kulcsszó')),
        el('div', { class: 'row', style: 'gap:4px' },
          el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Feljebb', title: 'Feljebb', disabled: i === 0, onclick: () => move(i, -1) }, '↑'),
          el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Lejjebb', title: 'Lejjebb', disabled: i === kb.temak.length - 1, onclick: () => move(i, 1) }, '↓')));
      if (!isOpen) return el('div', { class: 'topic' }, headRow);
      return el('div', { class: 'topic open' }, headRow,
        el('div', { class: 'form', style: 'margin-top:10px' },
          field('Téma címe', el('input', { value: t.title, oninput: (e) => { t.title = e.target.value; dirty(); } }), 'Ez lesz a válasz címe; ha a kérdésben szerepel, az is találat.'),
          field('Kulcsszavak (vesszővel elválasztva)', el('input', { value: t.keys.join(', '), oninput: (e) => { t.keys = e.target.value.split(',').map((k) => k.trim()).filter(Boolean); dirty(); } }),
            'Szótő is jó: a „foglal” illik a „foglalás”, „foglalni” szóra. Ékezet és kis/nagybetű nem számít. A többszavas kulcsszó erősebb.'),
          field('Válasz', el('textarea', { rows: 7, oninput: (e) => { t.answer = e.target.value; dirty(); } }, t.answer),
            'Új sor = új bekezdés. Felsorolás: a sor elején „- ”, számozott lépések: „1. ”.'),
          el('div', { class: 'row between' },
            field('Elsőbbség (ha több téma is illik)', el('select', { onchange: (e) => { t.elsobbseg = Number(e.target.value); dirty(); } },
              PRIO.map(([v, l]) => el('option', { value: v, selected: (Number(t.elsobbseg) || 0) === v }, l)))),
            el('button', { class: 'btn ghost small', type: 'button', style: 'color:var(--danger)', onclick: () => {
              if (!confirm('Törlöd ezt a témát: „' + (t.title || 'új téma') + '”?')) return;
              kb.temak.splice(i, 1); T.open = -1; T.dirty = true; rerender();
            } }, 'Téma törlése'))));
    };
    const search = el('input', { type: 'search', placeholder: 'Keresés a témákban…', value: T.filter, 'aria-label': 'Keresés a témákban', oninput: (e) => {
      T.filter = e.target.value; rerender();
      const s = document.querySelector('.topics-search'); if (s) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
    } });
    search.classList.add('topics-search');
    const topics = el('div', { class: 'card form' },
      el('div', { class: 'row between' }, el('h3', { style: 'margin:0' }, 'Témák (' + kb.temak.length + ')'),
        el('button', { class: 'btn small', type: 'button', onclick: () => { kb.temak.push({ id: '', title: '', keys: [], answer: '' }); T.open = kb.temak.length - 1; T.filter = ''; T.dirty = true; rerender(); setTimeout(() => document.querySelector('.topic.open input')?.focus(), 50); } }, '+ Új téma')),
      el('p', { class: 'muted', style: 'margin:0;font-size:.88rem' }, '★ Az első 4 téma gyorsgombként jelenik meg a chat alján. A sorrendet a nyilakkal állíthatod.'),
      search,
      shown.length ? el('div', { class: 'topics' }, shown.map(topic)) : el('p', { class: 'muted' }, kb.temak.length ? 'Nincs találat.' : 'Még nincs téma. Kezdd az „+ Új téma” gombbal.'));

    // mentés
    const status = el('span', { class: 'muted' }, T.dirty ? 'Nem mentett változások' : 'Minden mentve');
    const save = async () => {
      const body = Object.assign(T.who.customer ? { customer: T.who.customer } : { bot: 'my-ai' }, { kb });
      try {
        const r = await api('admin/kb/save', {}, body);
        toast('Mentve – a chat már ezzel válaszol (' + r.temak + ' téma)');
        const open = T.open, test = T.test;
        await loadTeach(T.who); state.teach.open = open; state.teach.test = test; render();
      } catch (e) { fail(e); }
    };
    const saveBar = el('div', { class: 'card row between savebar' + (T.dirty ? ' dirty' : '') },
      status,
      el('div', { class: 'row' },
        !T.who.customer && T.d.source === 'db' ? el('button', { class: 'btn ghost small', type: 'button', onclick: async () => {
          if (!confirm('Visszaállítod az alap tudástárat? A tanított változat törlődik.')) return;
          try { await api('admin/kb/reset', {}, { bot: 'my-ai' }); toast('Visszaállítva az alapra'); await loadTeach({ bot: 'my-ai' }); render(); } catch (e) { fail(e); }
        } }, 'Vissza az alapra') : null,
        el('button', { class: 'btn ghost small', type: 'button', onclick: () => copy(JSON.stringify(kb, null, 2)) }, 'Másolás (JSON)'),
        el('button', { class: 'btn', type: 'button', onclick: save }, 'Mentés')));

    return el('div', { class: 'teach' }, head, el('div', { class: 'grid2' }, test, gen), topics, saveBar);
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
  window.addEventListener('beforeunload', (e) => { if (state.teach?.dirty) { e.preventDefault(); e.returnValue = ''; } });
  const resetToken = new URLSearchParams(location.search).get('reset');
  if (resetToken) showReset(resetToken);
  else start();
})();
