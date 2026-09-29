// Admin felület: foglalások, szolgáltatások, nyitvatartás, beállítások, fiók.
(function () {
  'use strict';

  const root = document.getElementById('admin');
  const MONTHS = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
  const DAYS = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
  const STATUS = { pending: 'Jóváhagyásra vár', confirmed: 'Visszaigazolva', rejected: 'Elutasítva', cancelled: 'Lemondva', completed: 'Teljesítve', noshow: 'Nem jelent meg' };

  const state = { tab: 'bookings', date: today(), view: 'day', data: null, bookings: [], pending: [] };

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

  async function api(route, params, body, isForm) {
    const qs = new URLSearchParams(Object.assign({ r: route }, params || {}));
    const opts = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
    if (body) {
      opts.method = 'POST';
      if (isForm) opts.body = body;
      else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    }
    let res, data;
    try {
      res = await fetch('../api/?' + qs.toString(), opts);
      data = await res.json();
    } catch (e) {
      throw Object.assign(new Error('Nem sikerült kapcsolódni a szerverhez.'), { status: 0 });
    }
    if (res.status === 401 && route !== 'login') { showLogin(); throw Object.assign(new Error('Bejelentkezés szükséges.'), { status: 401, silent: true }); }
    if (!res.ok) throw Object.assign(new Error(data.error || 'Hiba történt.'), { status: res.status });
    return data;
  }

  function pad(n) { return String(n).padStart(2, '0'); }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function today() { return ymd(new Date()); }
  function addDays(date, n) { const d = new Date(date + 'T12:00:00'); d.setDate(d.getDate() + n); return ymd(d); }
  function weekStart(date) { const d = new Date(date + 'T12:00:00'); return addDays(date, -((d.getDay() + 6) % 7)); }
  function huDate(date) { const d = new Date(date + 'T12:00:00'); return d.getFullYear() + '. ' + MONTHS[d.getMonth()] + ' ' + d.getDate() + '., ' + DAYS[d.getDay()]; }
  function shortDate(date) { const d = new Date(date + 'T12:00:00'); return MONTHS[d.getMonth()].slice(0, 3) + '. ' + d.getDate() + '. ' + DAYS[d.getDay()].slice(0, 3); }
  const hm = (dt) => String(dt).slice(11, 16);

  function toast(msg, isError) {
    const t = el('div', { class: 'toast' + (isError ? ' err' : ''), role: 'status' }, msg);
    document.body.append(t);
    setTimeout(() => t.remove(), 3500);
  }
  function fail(e) { if (!e.silent) toast(e.message, true); }

  function modal(title, body, onClose) {
    const back = el('div', { class: 'modal-back', onclick: (e) => { if (e.target === back) close(); } });
    const close = () => { back.remove(); document.removeEventListener('keydown', esc); if (onClose) onClose(); };
    const esc = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', esc);
    back.append(el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      el('div', { class: 'row between', style: 'margin-bottom:10px' }, el('h2', { style: 'margin:0' }, title), el('button', { class: 'icon-btn', type: 'button', onclick: close, 'aria-label': 'Bezárás' }, '✕')),
      body));
    document.body.append(back);
    const first = back.querySelector('input, select, textarea');
    if (first) first.focus();
    return close;
  }

  function field(label, control, hint) {
    return el('label', {}, label, control, hint ? el('small', {}, hint) : null);
  }
  function check(label, checked, attrs) {
    return el('label', { class: 'check' }, el('input', Object.assign({ type: 'checkbox', checked }, attrs || {})), label);
  }

  // ---------------------------------------------------------------- belépés
  function showLogin() {
    const form = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try {
        await api('login', {}, { email: form.email.value, password: form.password.value });
        start();
      } catch (err) { fail(err); }
    } },
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    field('Jelszó', el('input', { name: 'password', type: 'password', required: true, autocomplete: 'current-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Belépés'),
    el('button', { class: 'link-btn', type: 'button', onclick: showForgot }, 'Elfelejtett jelszó?'));
    root.replaceChildren(el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, 'Belépés'), form)));
  }

  function showForgot() {
    const form = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try {
        await api('forgot', {}, { email: form.email.value });
        form.replaceChildren(
          el('p', { class: 'ok' }, 'Ha ezzel az email címmel van admin fiók, elküldtük rá a jelszó-visszaállító linket. Nézd meg a leveleidet (a spam mappát is) — a link 1 óráig érvényes.'),
          el('button', { class: 'btn ghost block', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
      } catch (err) { fail(err); }
    } },
    el('p', { class: 'muted' }, 'Add meg az admin fiókod email címét, és küldünk egy linket, amivel új jelszót állíthatsz be.'),
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Link küldése'),
    el('button', { class: 'link-btn', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
    root.replaceChildren(el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, 'Elfelejtett jelszó'), form)));
  }

  function showReset(token) {
    const form = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      if (form.password.value !== form.password2.value) return toast('A két jelszó nem egyezik.', true);
      try {
        await api('reset', {}, { token, password: form.password.value });
        history.replaceState(null, '', location.pathname);
        toast('Az új jelszó beállítva — most már beléphetsz vele.');
        showLogin();
      } catch (err) { fail(err); }
    } },
    field('Új jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    field('Új jelszó még egyszer', el('input', { name: 'password2', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Jelszó beállítása'),
    el('button', { class: 'link-btn', type: 'button', onclick: () => { history.replaceState(null, '', location.pathname); showLogin(); } }, 'Mégse'));
    root.replaceChildren(el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, 'Új jelszó beállítása'), form)));
  }

  // ---------------------------------------------------------------- keret
  const TABS = [['bookings', 'Foglalások'], ['services', 'Szolgáltatások'], ['hours', 'Nyitvatartás'], ['settings', 'Beállítások'], ['account', 'Fiók']];

  function render() {
    const tabs = el('nav', { class: 'tabs' }, TABS.map(([k, label]) =>
      el('button', { class: state.tab === k ? 'on' : '', type: 'button', onclick: () => { state.tab = k; render(); } }, label)));
    const view = { bookings: viewBookings, services: viewServices, hours: viewHours, settings: viewSettings, account: viewAccount }[state.tab]();
    root.replaceChildren(tabs, view);
  }

  async function reloadSettings() {
    state.data = await api('admin/settings');
  }

  // ---------------------------------------------------------------- foglalások
  function range() {
    if (state.view === 'week') { const from = weekStart(state.date); return [from, addDays(from, 6)]; }
    return [state.date, state.date];
  }

  async function loadBookings() {
    const [from, to] = range();
    try {
      const data = await api('admin/bookings', { from, to });
      state.bookings = data.bookings;
      state.pending = data.pending;
    } catch (e) { fail(e); }
    if (state.tab === 'bookings') render();
  }

  function bookingItem(b, showDate) {
    const inactive = ['cancelled', 'rejected', 'noshow'].includes(b.status);
    return el('button', { class: 'item' + (inactive ? ' dim' : ''), type: 'button', onclick: () => openBooking(b.id) },
      el('div', {}, showDate ? el('div', { class: 'what' }, shortDate(String(b.start_at).slice(0, 10))) : null, el('div', { class: 'time' }, hm(b.start_at)), el('div', { class: 'what' }, hm(b.end_at))),
      el('div', {}, el('div', { class: 'who' }, b.name), el('div', { class: 'what' }, b.service_name + (b.phone ? ' · ' + b.phone : ''))),
      el('span', { class: 'badge ' + b.status }, STATUS[b.status] || b.status));
  }

  function viewBookings() {
    const [from, to] = range();
    const step = state.view === 'week' ? 7 : 1;
    const active = state.bookings.filter((b) => b.status === 'pending' || b.status === 'confirmed');
    const label = state.view === 'week' ? shortDate(from) + ' – ' + shortDate(to) : huDate(state.date);
    const search = el('input', { type: 'search', placeholder: 'Keresés névre, telefonra, rendszámra…', style: 'flex:1;min-width:200px' });
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') doSearch(search.value); });

    const list = state.bookings.length
      ? el('div', { class: 'list' }, state.bookings.map((b) => bookingItem(b, state.view === 'week')))
      : el('p', { class: 'note' }, 'Erre az időszakra nincs foglalás.');

    return el('div', {},
      state.pending.length ? el('div', { class: 'card' },
        el('h3', {}, 'Jóváhagyásra vár (' + state.pending.length + ')'),
        el('div', { class: 'list' }, state.pending.map((b) => bookingItem(Object.assign({ status: 'pending' }, b), true)))) : null,
      el('div', { class: 'datebar' },
        el('button', { class: 'btn ghost small', type: 'button', onclick: () => { state.date = addDays(state.date, -step); loadBookings(); }, 'aria-label': 'Előző' }, '‹'),
        el('button', { class: 'btn ghost small', type: 'button', onclick: () => { state.date = today(); loadBookings(); } }, 'Ma'),
        el('button', { class: 'btn ghost small', type: 'button', onclick: () => { state.date = addDays(state.date, step); loadBookings(); }, 'aria-label': 'Következő' }, '›'),
        el('input', { type: 'date', value: state.date, onchange: (e) => { if (e.target.value) { state.date = e.target.value; loadBookings(); } } }),
        el('span', { class: 'label' }, label),
        el('select', { style: 'width:auto', onchange: (e) => { state.view = e.target.value; loadBookings(); } },
          el('option', { value: 'day', selected: state.view === 'day' }, 'Nap'),
          el('option', { value: 'week', selected: state.view === 'week' }, 'Hét')),
        el('button', { class: 'btn small', type: 'button', onclick: () => editBooking(null) }, '+ Új foglalás')),
      el('div', { class: 'stat' },
        el('div', {}, el('b', {}, active.length), 'aktív foglalás'),
        el('div', {}, el('b', {}, state.bookings.filter((b) => b.status === 'pending').length), 'jóváhagyásra vár')),
      list,
      el('div', { class: 'row', style: 'margin-top:20px' }, search, el('button', { class: 'btn ghost small', type: 'button', onclick: () => doSearch(search.value) }, 'Keresés')));
  }

  async function doSearch(q) {
    if (!q.trim()) return;
    try {
      const { bookings } = await api('admin/search', { q });
      modal('Találatok: „' + q + '”', bookings.length
        ? el('div', { class: 'list' }, bookings.map((b) => bookingItem(b, true)))
        : el('p', { class: 'note' }, 'Nincs találat.'));
    } catch (e) { fail(e); }
  }

  async function openBooking(id) {
    document.querySelectorAll('.modal-back').forEach((m) => m.remove());
    let b;
    try {
      b = (await api('admin/booking', { id })).booking;
    } catch (e) { return fail(e); }

    const fieldsDef = state.data.settings.fields;
    const note = el('textarea', { placeholder: 'Üzenet az ügyfélnek (bekerül az emailbe)' }, b.admin_note || '');
    const notify = el('input', { type: 'checkbox', checked: !!b.email });
    const setStatus = async (status) => {
      try {
        await api('admin/booking/status', {}, { id: b.id, status, admin_note: note.value, notify: notify.checked });
        toast('Mentve: ' + STATUS[status]);
        close();
        loadBookings();
      } catch (e) { fail(e); }
    };
    const act = (status, label, cls) => (b.status === status ? null : el('button', { class: 'btn small ' + (cls || 'ghost'), type: 'button', onclick: () => setStatus(status) }, label));
    const day = String(b.start_at).slice(0, 10);

    const body = el('div', {},
      el('dl', { class: 'summary' },
        el('dt', {}, 'Állapot'), el('dd', {}, el('span', { class: 'badge ' + b.status }, STATUS[b.status])),
        el('dt', {}, 'Időpont'), el('dd', {}, huDate(day) + ' ' + hm(b.start_at) + '–' + hm(b.end_at)),
        el('dt', {}, 'Szolgáltatás'), el('dd', {}, b.service_name),
        el('dt', {}, 'Név'), el('dd', {}, b.name),
        b.phone ? [el('dt', {}, 'Telefon'), el('dd', {}, el('a', { href: 'tel:' + b.phone }, b.phone))] : null,
        b.email ? [el('dt', {}, 'Email'), el('dd', {}, el('a', { href: 'mailto:' + b.email }, b.email))] : null,
        fieldsDef.map((f) => (b.fields && b.fields[f.key] ? [el('dt', {}, f.label), el('dd', {}, b.fields[f.key])] : null)),
        b.note ? [el('dt', {}, 'Megjegyzés'), el('dd', {}, b.note)] : null,
        el('dt', {}, 'Forrás'), el('dd', {}, b.source === 'admin' ? 'Admin rögzítette' : 'Online foglalás')),
      el('div', { class: 'form' },
        field('Üzenet az ügyfélnek', note),
        el('label', { class: 'check' }, notify, 'Email értesítés az ügyfélnek állapotváltozáskor')),
      el('div', { class: 'row', style: 'margin-top:14px' },
        act('confirmed', b.status === 'pending' ? 'Jóváhagyás' : 'Visszaigazolva', b.status === 'pending' ? '' : 'ghost'),
        b.status === 'pending' ? act('rejected', 'Elutasítás', 'danger') : null,
        ['pending', 'confirmed'].includes(b.status) ? act('cancelled', 'Lemondás') : null,
        b.status === 'confirmed' ? act('completed', 'Teljesítve') : null,
        b.status === 'confirmed' ? act('noshow', 'Nem jelent meg') : null),
      el('hr'),
      el('div', { class: 'row between' },
        el('button', { class: 'btn small ghost', type: 'button', onclick: () => { close(); editBooking(b); } }, 'Szerkesztés / áthelyezés'),
        el('button', { class: 'btn small ghost', type: 'button', style: 'color:var(--danger)', onclick: async () => {
          if (!confirm('Végleg törlöd ezt a foglalást? (Értesítés nem megy.)')) return;
          try { await api('admin/booking/delete', {}, { id: b.id }); close(); toast('Törölve'); loadBookings(); } catch (e) { fail(e); }
        } }, 'Törlés')));
    const close = modal('Foglalás', body);
  }

  function editBooking(b) {
    const services = state.data.services;
    if (!services.length) return toast('Előbb vegyél fel legalább egy szolgáltatást.', true);
    const fieldsDef = state.data.settings.fields;
    const f = el('form', { class: 'form' });
    const service = el('select', { name: 'service_id' }, services.map((s) =>
      el('option', { value: s.id, selected: b ? s.id === b.service_id : false }, s.name + (s.active ? '' : ' (inaktív)') + ' – ' + s.duration_min + ' perc')));
    const date = el('input', { type: 'date', name: 'date', required: true, value: b ? String(b.start_at).slice(0, 10) : state.date });
    const time = el('input', { type: 'time', name: 'time', required: true, step: 300, value: b ? hm(b.start_at) : '' });
    const durationOf = () => { const s = services.find((x) => String(x.id) === service.value); return s ? s.duration_min : 30; };
    const duration = el('input', { type: 'number', name: 'duration_min', min: 5, step: 5, value: b ? Math.round((new Date(String(b.end_at).replace(' ', 'T')) - new Date(String(b.start_at).replace(' ', 'T'))) / 60000) : durationOf() });
    const slotBox = el('div', { class: 'slots', style: 'margin-top:0' });
    const loadSlots = async () => {
      slotBox.replaceChildren();
      if (!date.value) return;
      try {
        const { slots } = await api('admin/slots', Object.assign({ service_id: service.value, date: date.value }, b ? { exclude: b.id } : {}));
        slotBox.replaceChildren(...(slots.length
          ? slots.map((t) => el('button', { class: 'slot' + (t === time.value ? ' sel' : ''), type: 'button', onclick: () => { time.value = t; loadSlots(); } }, t))
          : [el('small', {}, 'Nincs szabad időpont a nyitvatartáson belül — időpont kézzel is megadható.')]));
      } catch (e) { fail(e); }
    };
    service.addEventListener('change', () => { duration.value = durationOf(); loadSlots(); });
    date.addEventListener('change', loadSlots);

    const extra = fieldsDef.map((fd) => field(fd.label, fd.type === 'textarea'
      ? el('textarea', { name: 'f_' + fd.key }, (b && b.fields && b.fields[fd.key]) || '')
      : fd.type === 'select'
        ? el('select', { name: 'f_' + fd.key }, el('option', { value: '' }, '–'), fd.options.map((o) => el('option', { value: o, selected: b && b.fields && b.fields[fd.key] === o }, o)))
        : el('input', { name: 'f_' + fd.key, value: (b && b.fields && b.fields[fd.key]) || '' })));
    const notify = el('input', { type: 'checkbox', checked: false });

    f.append(
      field('Szolgáltatás', service),
      el('div', { class: 'grid2' }, field('Dátum', date), field('Kezdés', time)),
      el('div', {}, el('small', {}, 'Szabad kezdési időpontok:'), slotBox),
      field('Időtartam (perc)', duration),
      field('Név *', el('input', { name: 'name', required: true, value: b ? b.name : '' })),
      el('div', { class: 'grid2' },
        field('Telefon', el('input', { name: 'phone', type: 'tel', value: b ? b.phone : '' })),
        field('Email', el('input', { name: 'email', type: 'email', value: b ? b.email : '' }))),
      extra,
      field('Ügyfél megjegyzése', el('textarea', { name: 'note' }, b ? b.note || '' : '')),
      field('Belső / ügyfélnek szóló üzenet', el('textarea', { name: 'admin_note' }, b ? b.admin_note || '' : '')),
      el('label', { class: 'check' }, notify, b ? 'Email az ügyfélnek a módosításról' : 'Visszaigazoló email az ügyfélnek'),
      el('button', { class: 'btn block', type: 'submit' }, b ? 'Mentés' : 'Foglalás rögzítése'));

    const save = async (force) => {
      const fields = {};
      fieldsDef.forEach((fd) => { fields[fd.key] = f['f_' + fd.key].value; });
      const payload = {
        id: b ? b.id : 0, service_id: Number(service.value), date: date.value, time: time.value, duration_min: Number(duration.value),
        name: f.name.value, phone: f.phone.value, email: f.email.value, note: f.note.value, admin_note: f.admin_note.value,
        fields, notify: notify.checked, force: !!force,
      };
      try {
        await api('admin/booking/save', {}, payload);
        toast('Mentve');
        close();
        state.date = date.value;
        loadBookings();
      } catch (e) {
        if (e.status === 409 && !force && confirm(e.message)) return save(true);
        fail(e);
      }
    };
    f.addEventListener('submit', (e) => { e.preventDefault(); save(false); });
    const close = modal(b ? 'Foglalás szerkesztése' : 'Új foglalás', f);
    loadSlots();
  }

  // ---------------------------------------------------------------- szolgáltatások
  function viewServices() {
    const services = state.data.services;
    const move = async (i, d) => {
      const ids = services.map((s) => s.id);
      const j = i + d;
      if (j < 0 || j >= ids.length) return;
      [ids[i], ids[j]] = [ids[j], ids[i]];
      try { await api('admin/services/order', {}, { ids }); await reloadSettings(); render(); } catch (e) { fail(e); }
    };
    return el('div', {},
      el('div', { class: 'row between', style: 'margin-bottom:12px' },
        el('p', { class: 'muted', style: 'margin:0' }, 'Az ügyfelek ezek közül választanak. Az időtartam határozza meg, mennyi ideig foglalt a hely.'),
        el('button', { class: 'btn small', type: 'button', onclick: () => editService(null) }, '+ Új szolgáltatás')),
      services.length ? el('div', { class: 'list' }, services.map((s, i) =>
        el('div', { class: 'item' + (s.active ? '' : ' dim'), style: 'grid-template-columns:1fr auto;cursor:default' },
          el('div', {}, el('div', { class: 'who' }, s.name + (s.active ? '' : ' (inaktív)')), el('div', { class: 'what' }, s.duration_min + ' perc' + (s.price ? ' · ' + s.price : ''))),
          el('div', { class: 'row' },
            el('button', { class: 'icon-btn', type: 'button', onclick: () => move(i, -1), 'aria-label': 'Feljebb', disabled: i === 0 }, '↑'),
            el('button', { class: 'icon-btn', type: 'button', onclick: () => move(i, 1), 'aria-label': 'Lejjebb', disabled: i === services.length - 1 }, '↓'),
            el('button', { class: 'btn small ghost', type: 'button', onclick: () => editService(s) }, 'Szerkesztés'))))) : el('p', { class: 'note' }, 'Még nincs szolgáltatás.'));
  }

  function editService(s) {
    const f = el('form', { class: 'form' },
      field('Megnevezés *', el('input', { name: 'name', required: true, value: s ? s.name : '' })),
      field('Leírás', el('textarea', { name: 'description' }, s ? s.description || '' : '')),
      el('div', { class: 'grid2' },
        field('Időtartam (perc) *', el('input', { name: 'duration_min', type: 'number', min: 5, step: 5, required: true, value: s ? s.duration_min : 30 })),
        field('Ár (szövegként)', el('input', { name: 'price', value: s ? s.price : '', placeholder: 'pl. 8 000 Ft' }))),
      check('Aktív (online foglalható)', s ? !!s.active : true, { name: 'active' }),
      el('div', { class: 'row between' },
        s ? el('button', { class: 'btn small ghost', type: 'button', style: 'color:var(--danger)', onclick: async () => {
          if (!confirm('Törlöd a szolgáltatást? A korábbi foglalások megmaradnak.')) return;
          try { await api('admin/services/delete', {}, { id: s.id }); close(); await reloadSettings(); render(); } catch (e) { fail(e); }
        } }, 'Törlés') : el('span'),
        el('button', { class: 'btn', type: 'submit' }, 'Mentés')));
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      try {
        await api('admin/services/save', {}, { id: s ? s.id : 0, name: f.name.value, description: f.description.value, duration_min: Number(f.duration_min.value), price: f.price.value, active: f.active.checked });
        close();
        await reloadSettings();
        render();
        toast('Mentve');
      } catch (err) { fail(err); }
    });
    const close = modal(s ? 'Szolgáltatás szerkesztése' : 'Új szolgáltatás', f);
  }

  // ---------------------------------------------------------------- nyitvatartás
  async function saveGroup(group, value) {
    try {
      await api('admin/settings', {}, { group, value });
      await reloadSettings();
      toast('Mentve');
      render();
    } catch (e) { fail(e); }
  }

  function viewHours() {
    const hours = JSON.parse(JSON.stringify(state.data.settings.hours));
    const closures = JSON.parse(JSON.stringify(state.data.settings.closures));
    const order = ['1', '2', '3', '4', '5', '6', '0'];

    const hoursBox = el('div');
    const drawHours = () => hoursBox.replaceChildren(...order.map((d) => {
      const ranges = hours[d] || (hours[d] = []);
      return el('div', { class: 'hours-row' },
        el('strong', { style: 'text-transform:capitalize;padding-top:10px' }, DAYS[Number(d)]),
        el('div', { class: 'ranges' },
          ranges.length ? null : el('span', { class: 'muted', style: 'padding-top:10px' }, 'Zárva'),
          ranges.map((r, i) => el('div', { class: 'range' },
            el('input', { type: 'time', value: r[0], onchange: (e) => { r[0] = e.target.value; } }),
            '–',
            el('input', { type: 'time', value: r[1], onchange: (e) => { r[1] = e.target.value; } }),
            el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Sáv törlése', onclick: () => { ranges.splice(i, 1); drawHours(); } }, '✕'))),
          el('div', {}, el('button', { class: 'link-btn', type: 'button', onclick: () => {
            const last = ranges[ranges.length - 1];
            ranges.push(last ? [last[1], last[1] < '17:00' ? '17:00' : '20:00'] : ['08:00', '17:00']);
            drawHours();
          } }, ranges.length ? '+ újabb sáv (pl. ebédszünet után)' : '+ nyitva'))));
    }));
    drawHours();

    const closBox = el('div', { class: 'editable' });
    const drawClosures = () => closBox.replaceChildren(
      ...(closures.length ? closures.map((c, i) => el('div', { class: 'rowx' },
        el('div', { class: 'grid2' },
          field('Ettől', el('input', { type: 'date', value: c.from, onchange: (e) => { c.from = e.target.value; } })),
          field('Eddig', el('input', { type: 'date', value: c.to, onchange: (e) => { c.to = e.target.value; } })),
          field('Ok (nem kötelező)', el('input', { value: c.reason, onchange: (e) => { c.reason = e.target.value; } }))),
        el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Törlés', onclick: () => { closures.splice(i, 1); drawClosures(); } }, '✕'))) : [el('p', { class: 'muted' }, 'Nincs megadott zárva tartás.')]),
      el('div', {}, el('button', { class: 'link-btn', type: 'button', onclick: () => { const t = today(); closures.push({ from: t, to: t, reason: '' }); drawClosures(); } }, '+ zárva tartás (szabadság, ünnep)')));
    drawClosures();

    return el('div', { class: 'grid2' },
      el('div', { class: 'card form' }, el('h2', {}, 'Heti nyitvatartás'), hoursBox,
        el('button', { class: 'btn', type: 'button', onclick: () => saveGroup('hours', hours) }, 'Nyitvatartás mentése')),
      el('div', { class: 'card form' }, el('h2', {}, 'Zárva tartás'), el('p', { class: 'muted' }, 'Ezeken a napokon nem lehet foglalni.'), closBox,
        el('button', { class: 'btn', type: 'button', onclick: () => saveGroup('closures', closures.filter((c) => c.from)) }, 'Zárva tartás mentése')));
  }

  // ---------------------------------------------------------------- beállítások
  function formCard(title, desc, controls, onSave) {
    const f = el('form', { class: 'card form' }, el('h2', {}, title), desc ? el('p', { class: 'muted', style: 'margin:0' }, desc) : null, controls, el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    f.addEventListener('submit', (e) => { e.preventDefault(); onSave(f); });
    return f;
  }

  function viewSettings() {
    const s = state.data.settings;
    const biz = s.business;
    const logoInput = el('input', { type: 'file', accept: 'image/png,image/jpeg,image/webp' });
    const uploadLogo = async () => {
      if (!logoInput.files[0]) return toast('Válassz ki egy képet.', true);
      const fd = new FormData();
      fd.append('logo', logoInput.files[0]);
      try { await api('admin/logo', {}, fd, true); toast('Logó feltöltve — frissítsd az oldalt.'); await reloadSettings(); render(); } catch (e) { fail(e); }
    };
    const removeLogo = async () => {
      const fd = new FormData();
      fd.append('remove', '1');
      try { await api('admin/logo', {}, fd, true); await reloadSettings(); render(); } catch (e) { fail(e); }
    };

    const fields = JSON.parse(JSON.stringify(s.fields));
    const fieldsBox = el('div', { class: 'editable' });
    const drawFields = () => fieldsBox.replaceChildren(
      ...(fields.length ? fields.map((fd, i) => el('div', { class: 'rowx' },
        el('div', { class: 'form' },
          el('div', { class: 'grid2' },
            field('Mező neve', el('input', { value: fd.label, onchange: (e) => { fd.label = e.target.value; } })),
            field('Típus', el('select', { onchange: (e) => { fd.type = e.target.value; drawFields(); } },
              [['text', 'Rövid szöveg'], ['textarea', 'Hosszabb szöveg'], ['select', 'Lenyíló lista']].map(([v, l]) => el('option', { value: v, selected: fd.type === v }, l))))),
          fd.type === 'select' ? field('Választási lehetőségek (vesszővel elválasztva)', el('input', { value: (fd.options || []).join(', '), onchange: (e) => { fd.options = e.target.value.split(',').map((x) => x.trim()).filter(Boolean); } })) : null,
          check('Kötelező', !!fd.required, { onchange: (e) => { fd.required = e.target.checked; } })),
        el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Mező törlése', onclick: () => { fields.splice(i, 1); drawFields(); } }, '✕'))) : [el('p', { class: 'muted' }, 'Nincs extra mező.')]),
      el('div', {}, el('button', { class: 'link-btn', type: 'button', onclick: () => { fields.push({ key: '', label: '', type: 'text', required: false, options: [] }); drawFields(); } }, '+ új mező')));
    drawFields();

    const bool = (f, name) => f[name].checked;

    return el('div', {},
      el('div', { class: 'note', style: 'margin-bottom:16px' }, 'Foglalási oldal címe: ', el('a', { href: state.data.base_url + '/', target: '_blank', rel: 'noopener' }, state.data.base_url + '/')),
      el('div', { class: 'grid2' },
        formCard('Cégadatok', 'Megjelenik a foglalási oldalon és az emailekben.', [
          field('Cég neve *', el('input', { name: 'name', required: true, value: biz.name })),
          field('Szlogen / rövid leírás', el('input', { name: 'tagline', value: biz.tagline })),
          field('Telefon', el('input', { name: 'phone', type: 'tel', value: biz.phone })),
          field('Email', el('input', { name: 'email', type: 'email', value: biz.email })),
          field('Cím', el('input', { name: 'address', value: biz.address })),
          field('Weboldal', el('input', { name: 'website', value: biz.website })),
        ], (f) => saveGroup('business', { name: f.name.value, tagline: f.tagline.value, phone: f.phone.value, email: f.email.value, address: f.address.value, website: f.website.value })),

        el('div', {},
          formCard('Arculat', 'A fő szín a fejléc, a gombok és az app ikon színe.', [
            field('Fő szín', el('input', { name: 'primary', type: 'color', value: s.theme.primary })),
          ], (f) => saveGroup('theme', { primary: f.primary.value })),
          el('div', { class: 'card form' },
            el('h2', {}, 'Logó'),
            s.theme.logo ? el('img', { src: '../uploads/' + s.theme.logo, alt: 'Logó', style: 'max-height:80px;max-width:220px;object-fit:contain;background:#fff;border-radius:10px;padding:6px' }) : el('p', { class: 'muted' }, 'Nincs logó — az app ikon a fő színből készül.'),
            field('Új logó (PNG, JPG, WEBP, max. 2 MB)', logoInput),
            el('div', { class: 'row' },
              el('button', { class: 'btn', type: 'button', onclick: uploadLogo }, 'Feltöltés'),
              s.theme.logo ? el('button', { class: 'btn ghost', type: 'button', onclick: removeLogo }, 'Logó eltávolítása') : null))),

        formCard('Foglalási szabályok', null, [
          el('div', { class: 'grid2' },
            field('Időpontok lépésköze (perc)', el('input', { name: 'slot_step', type: 'number', min: 5, step: 5, value: s.rules.slot_step }), 'Pl. 30 → 8:00, 8:30, 9:00…'),
            field('Párhuzamos helyek száma', el('input', { name: 'capacity', type: 'number', min: 1, value: s.rules.capacity }), 'Pl. emelők, székek, munkatársak'),
            field('Legalább ennyi órával előre', el('input', { name: 'lead_hours', type: 'number', min: 0, step: 0.5, value: s.rules.lead_hours })),
            field('Legfeljebb ennyi napra előre', el('input', { name: 'max_days', type: 'number', min: 1, value: s.rules.max_days }))),
          el('fieldset', { style: 'border:0;padding:0;margin:0;display:grid;gap:8px' },
            el('legend', { style: 'font-weight:600;margin-bottom:6px' }, 'Új foglalás'),
            el('label', { class: 'check' }, el('input', { type: 'radio', name: 'approval', value: 'auto', checked: s.rules.approval !== 'manual' }), 'Automatikusan visszaigazolva'),
            el('label', { class: 'check' }, el('input', { type: 'radio', name: 'approval', value: 'manual', checked: s.rules.approval === 'manual' }), 'Kézi jóváhagyás szükséges')),
          check('Az ügyfél online lemondhatja', !!s.rules.customer_cancel, { name: 'customer_cancel' }),
          field('Lemondás legkésőbb a kezdés előtt (óra)', el('input', { name: 'cancel_hours', type: 'number', min: 0, value: s.rules.cancel_hours })),
        ], (f) => saveGroup('rules', {
          slot_step: Number(f.slot_step.value), capacity: Number(f.capacity.value), lead_hours: Number(f.lead_hours.value), max_days: Number(f.max_days.value),
          approval: f.querySelector('input[name=approval]:checked').value, customer_cancel: bool(f, 'customer_cancel'), cancel_hours: Number(f.cancel_hours.value),
        })),

        formCard('Értesítések', null, [
          field('Értesítési email (ide jön az új foglalás)', el('input', { name: 'admin_email', type: 'email', value: s.notify.admin_email })),
          check('Email az ügyfélnek (visszaigazolás, változások)', !!s.notify.send_customer, { name: 'send_customer' }),
          check('Email nekem új foglalásról és lemondásról', !!s.notify.send_admin, { name: 'send_admin' }),
        ], (f) => saveGroup('notify', { admin_email: f.admin_email.value, send_customer: bool(f, 'send_customer'), send_admin: bool(f, 'send_admin') })),

        formCard('Foglalási űrlap', 'A név mindig kötelező. Az extra mezőkkel az űrlap a vállalkozáshoz igazítható (pl. rendszám, autó típusa).', [
          check('Telefonszám kötelező', !!s.contact.phone_required, { name: 'phone_required' }),
          check('Email kötelező', !!s.contact.email_required, { name: 'email_required' }),
          el('h3', { style: 'margin:8px 0 0' }, 'Extra mezők'),
          fieldsBox,
        ], async (f) => {
          try {
            await api('admin/settings', {}, { group: 'contact', value: { phone_required: bool(f, 'phone_required'), email_required: bool(f, 'email_required') } });
            await saveGroup('fields', fields);
          } catch (e) { fail(e); }
        }),

        formCard('Szövegek', null, [
          field('Bevezető szöveg', el('textarea', { name: 'intro' }, s.texts.intro)),
          field('Sikeres foglalás üzenete', el('textarea', { name: 'success' }, s.texts.success)),
          field('Feltételek / tudnivalók (a küldés gomb fölött)', el('textarea', { name: 'terms' }, s.texts.terms)),
        ], (f) => saveGroup('texts', { intro: f.intro.value, success: f.success.value, terms: f.terms.value }))));
  }

  // ---------------------------------------------------------------- fiók
  function viewAccount() {
    const me = state.data.me;
    const pw = el('form', { class: 'card form' },
      el('h2', {}, 'Jelszó módosítása'),
      field('Jelenlegi jelszó', el('input', { name: 'current', type: 'password', required: true, autocomplete: 'current-password' })),
      field('Új jelszó (min. 8 karakter)', el('input', { name: 'new', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn', type: 'submit' }, 'Jelszó mentése'));
    pw.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('admin/password', {}, { current: pw.current.value, new: pw.new.value }); pw.reset(); toast('Jelszó módosítva'); } catch (err) { fail(err); }
    });

    const add = el('form', { class: 'form' },
      el('div', { class: 'grid2' },
        field('Név', el('input', { name: 'name', required: true })),
        field('Email', el('input', { name: 'email', type: 'email', required: true }))),
      field('Jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn small', type: 'submit' }, 'Admin hozzáadása'));
    add.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('admin/admins/save', {}, { name: add.name.value, email: add.email.value, password: add.password.value }); await reloadSettings(); render(); toast('Admin hozzáadva'); } catch (err) { fail(err); }
    });

    return el('div', { class: 'grid2' },
      el('div', {},
        el('div', { class: 'card' },
          el('h2', {}, me.name), el('p', { class: 'muted' }, me.email),
          el('button', { class: 'btn ghost', type: 'button', onclick: async () => { await api('logout', {}, {}); showLogin(); } }, 'Kijelentkezés')),
        pw),
      el('div', { class: 'card form' },
        el('h2', {}, 'Adminok'),
        el('div', { class: 'list' }, state.data.admins.map((a) => el('div', { class: 'item', style: 'grid-template-columns:1fr auto;cursor:default' },
          el('div', {}, el('div', { class: 'who' }, a.name), el('div', { class: 'what' }, a.email)),
          a.id === me.id ? el('span', { class: 'muted' }, 'te') : el('button', { class: 'btn small ghost', type: 'button', onclick: async () => {
            if (!confirm('Törlöd ' + a.name + ' admin hozzáférését?')) return;
            try { await api('admin/admins/delete', {}, { id: a.id }); await reloadSettings(); render(); } catch (e) { fail(e); }
          } }, 'Törlés')))),
        el('hr'),
        el('h3', {}, 'Új admin'),
        add));
  }

  // ---------------------------------------------------------------- indulás
  async function start() {
    root.replaceChildren(el('p', { class: 'muted' }, 'Betöltés…'));
    try {
      const { admin } = await api('me');
      if (!admin) return showLogin();
      await reloadSettings();
      render();
      loadBookings();
    } catch (e) { fail(e); }
  }

  const resetToken = new URLSearchParams(location.search).get('reset');
  if (resetToken) showReset(resetToken);
  else start();
})();
