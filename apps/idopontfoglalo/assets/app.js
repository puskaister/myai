// Ügyféloldali foglalási app: szolgáltatás → nap és időpont → adatok → kész.
// ?foglalas=<token> címmel a meglévő foglalás kezelése (megtekintés, lemondás).
(function () {
  'use strict';

  const BOOT = window.BOOT;
  const S = BOOT.settings;
  const app = document.getElementById('app');
  const MONTHS = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
  const DOW = ['H', 'K', 'Sze', 'Cs', 'P', 'Szo', 'V'];
  const STATUS = { pending: 'Jóváhagyásra vár', confirmed: 'Visszaigazolva', rejected: 'Elutasítva', cancelled: 'Lemondva', completed: 'Teljesítve', noshow: 'Nem jelent meg' };

  const state = { step: 'service', service: null, month: null, days: {}, date: null, slots: null, time: null, form: { fields: {} }, errors: {}, result: null, busy: false };

  // ---------------------------------------------------------------- segédek
  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
      else if (k === 'class') node.className = v;
      else if (k === 'value') node.value = v;
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
    if (!res.ok) throw Object.assign(new Error(data.error || 'Hiba történt.'), { status: res.status, fields: data.fields });
    return data;
  }

  const pad = (n) => String(n).padStart(2, '0');
  const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  const huDate = (s) => {
    const d = new Date(s + 'T12:00:00');
    return d.getFullYear() + '. ' + MONTHS[d.getMonth()] + ' ' + d.getDate() + '., ' + ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'][d.getDay()];
  };
  const duration = (min) => (min >= 60 ? Math.floor(min / 60) + ' óra' + (min % 60 ? ' ' + (min % 60) + ' perc' : '') : min + ' perc');

  function toast(msg, isError) {
    const t = el('div', { class: 'toast' + (isError ? ' err' : ''), role: 'status' }, msg);
    document.body.append(t);
    setTimeout(() => t.remove(), 3500);
  }

  // A korábbi foglalások azonosítói csak ebben a böngészőben (kényelmi funkció).
  const store = {
    get() { try { return JSON.parse(localStorage.getItem('idopont_bookings') || '[]'); } catch (e) { return []; } },
    add(item) { try { localStorage.setItem('idopont_bookings', JSON.stringify([item].concat(this.get().filter((b) => b.token !== item.token)).slice(0, 10))); } catch (e) { /* privát mód */ } },
  };

  function steps(n) {
    return el('div', { class: 'steps', 'aria-hidden': 'true' }, [1, 2, 3].map((i) => el('span', { class: i <= n ? 'on' : '' })));
  }

  function go(step) {
    state.step = step;
    render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ---------------------------------------------------------------- nézetek
  function viewService() {
    const upcoming = store.get().filter((b) => b.start >= ymd(new Date()));
    return [
      steps(1),
      el('div', { class: 'card' },
        el('h1', {}, 'Válassz szolgáltatást'),
        S.texts.intro ? el('p', { class: 'muted' }, S.texts.intro) : null,
        BOOT.services.length
          ? el('div', { class: 'choices' }, BOOT.services.map((s) =>
            el('button', { class: 'choice', type: 'button', onclick: () => pickService(s) },
              el('span', { class: 'title' }, s.name),
              el('span', { class: 'meta' }, el('span', {}, '⏱ ' + duration(s.duration_min)), s.price ? el('span', {}, s.price) : null),
              s.description ? el('span', { class: 'desc' }, s.description) : null)))
          : el('p', { class: 'note' }, 'Jelenleg nincs online foglalható szolgáltatás.')),
      upcoming.length ? el('div', { class: 'card' },
        el('h3', {}, 'Foglalásaim'),
        el('div', { class: 'list' }, upcoming.map((b) =>
          el('a', { class: 'item', href: '?foglalas=' + b.token, style: 'grid-template-columns:1fr auto;text-decoration:none' },
            el('div', {}, el('div', { class: 'who' }, b.service), el('div', { class: 'what' }, b.when)),
            el('span', { class: 'muted' }, '›'))))) : null,
    ];
  }

  function pickService(s) {
    state.service = s;
    state.date = null;
    state.time = null;
    state.slots = null;
    state.month = state.month || ymd(new Date()).slice(0, 7);
    go('date');
    loadDays();
  }

  async function loadDays() {
    state.days = null;
    render();
    try {
      const data = await api('days', { service_id: state.service.id, month: state.month });
      state.days = data.days;
      // Első megnyitáskor, ha ebben a hónapban nincs szabad nap, lépjünk a következőre.
      if (!state.autoAdvanced && !Object.values(data.days).some(Boolean) && canNextMonth()) {
        state.autoAdvanced = true;
        return shiftMonth(1);
      }
      state.autoAdvanced = true;
    } catch (e) {
      state.days = {};
      toast(e.message, true);
    }
    render();
  }

  function canNextMonth() {
    const last = new Date();
    last.setDate(last.getDate() + (S.rules.max_days || 60));
    return state.month < ymd(last).slice(0, 7);
  }

  function shiftMonth(delta) {
    const [y, m] = state.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    state.month = ymd(d).slice(0, 7);
    loadDays();
  }

  async function pickDate(date) {
    state.date = date;
    state.time = null;
    state.slots = null;
    render();
    try {
      state.slots = (await api('slots', { service_id: state.service.id, date })).slots;
    } catch (e) {
      state.slots = [];
      toast(e.message, true);
    }
    render();
    const slots = document.querySelector('.slots, #no-slots');
    if (slots) slots.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function viewDate() {
    const [y, m] = state.month.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const daysInMonth = new Date(y, m, 0).getDate();
    const offset = (first.getDay() + 6) % 7; // hétfővel kezdődő hét
    const today = ymd(new Date());
    const thisMonth = today.slice(0, 7);

    const cells = DOW.map((d) => el('div', { class: 'dow' }, d));
    for (let i = 0; i < offset; i++) cells.push(el('div'));
    for (let d = 1; d <= daysInMonth; d++) {
      const date = state.month + '-' + pad(d);
      const free = state.days && state.days[date];
      cells.push(el('button', {
        class: 'day' + (date === today ? ' today' : '') + (date === state.date ? ' sel' : ''),
        type: 'button', disabled: !free, 'aria-label': huDate(date) + (free ? '' : ' – nincs szabad időpont'),
        onclick: () => pickDate(date),
      }, d));
    }

    let slotsBox = null;
    if (state.date) {
      if (state.slots === null) slotsBox = el('p', { class: 'muted' }, 'Időpontok betöltése…');
      else if (!state.slots.length) slotsBox = el('p', { class: 'note', id: 'no-slots' }, 'Erre a napra már nincs szabad időpont.');
      else slotsBox = el('div', {},
        el('h3', { style: 'margin-top:18px' }, huDate(state.date)),
        el('div', { class: 'slots' }, state.slots.map((t) =>
          el('button', { class: 'slot' + (t === state.time ? ' sel' : ''), type: 'button', onclick: () => { state.time = t; go('details'); } }, t))));
    }

    return [
      steps(2),
      el('button', { class: 'link-btn back', type: 'button', onclick: () => go('service') }, '‹ Vissza'),
      el('div', { class: 'card' },
        el('h1', {}, 'Válassz időpontot'),
        el('p', { class: 'muted' }, state.service.name + ' · ' + duration(state.service.duration_min)),
        el('div', { class: 'cal-head' },
          el('button', { class: 'btn ghost small', type: 'button', disabled: state.month <= thisMonth, onclick: () => shiftMonth(-1), 'aria-label': 'Előző hónap' }, '‹'),
          el('strong', {}, y + '. ' + MONTHS[m - 1]),
          el('button', { class: 'btn ghost small', type: 'button', disabled: !canNextMonth(), onclick: () => shiftMonth(1), 'aria-label': 'Következő hónap' }, '›')),
        state.days === null ? el('p', { class: 'muted' }, 'Betöltés…') : el('div', { class: 'cal' }, cells),
        slotsBox),
    ];
  }

  function input(name, label, opts) {
    opts = opts || {};
    const isField = name.startsWith('fields.');
    const key = isField ? name.slice(7) : name;
    const value = isField ? state.form.fields[key] : state.form[name];
    const err = state.errors[name];
    const onInput = (e) => { if (isField) state.form.fields[key] = e.target.value; else state.form[name] = e.target.value; };
    let control;
    if (opts.type === 'textarea') {
      control = el('textarea', { name, required: opts.required, oninput: onInput, class: err ? 'invalid' : null }, value || '');
    } else if (opts.type === 'select') {
      control = el('select', { name, required: opts.required, onchange: onInput, class: err ? 'invalid' : null },
        el('option', { value: '' }, '– válassz –'),
        (opts.options || []).map((o) => el('option', { value: o, selected: o === value }, o)));
    } else {
      control = el('input', {
        name, type: opts.type || 'text', required: opts.required, value: value || '', autocomplete: opts.autocomplete,
        inputmode: opts.inputmode, oninput: onInput, class: err ? 'invalid' : null,
      });
    }
    return el('label', {}, label + (opts.required ? ' *' : ''), control, err ? el('span', { class: 'field-error' }, err) : null);
  }

  function viewDetails() {
    const s = state.service;
    return [
      steps(3),
      el('button', { class: 'link-btn back', type: 'button', onclick: () => go('date') }, '‹ Másik időpont'),
      el('div', { class: 'card' },
        el('h1', {}, 'Adataid'),
        el('dl', { class: 'summary' },
          el('dt', {}, 'Szolgáltatás'), el('dd', {}, s.name),
          el('dt', {}, 'Időpont'), el('dd', {}, huDate(state.date) + ' ' + state.time)),
        el('form', { class: 'form', onsubmit: submit, novalidate: true },
          input('name', 'Név', { required: true, autocomplete: 'name' }),
          input('phone', 'Telefonszám', { required: S.contact.phone_required, type: 'tel', autocomplete: 'tel', inputmode: 'tel' }),
          input('email', 'Email', { required: S.contact.email_required, type: 'email', autocomplete: 'email' }),
          S.fields.map((f) => input('fields.' + f.key, f.label, { required: f.required, type: f.type, options: f.options })),
          input('note', 'Megjegyzés', { type: 'textarea' }),
          // honeypot: embernek láthatatlan, a robotok kitöltik
          el('input', { name: 'website', tabindex: '-1', autocomplete: 'off', style: 'position:absolute;left:-9999px', 'aria-hidden': 'true' }),
          S.texts.terms ? el('p', { class: 'note' }, S.texts.terms) : null,
          S.rules.approval === 'manual' ? el('p', { class: 'muted' }, 'A foglalást visszaigazolás után tekintjük véglegesnek — erről emailt küldünk.') : null,
          el('button', { class: 'btn block', type: 'submit', disabled: state.busy }, state.busy ? 'Küldés…' : (S.rules.approval === 'manual' ? 'Foglalási kérés elküldése' : 'Időpont lefoglalása')))),
    ];
  }

  async function submit(e) {
    e.preventDefault();
    if (state.busy) return;
    state.errors = {};
    state.busy = true;
    render();
    try {
      const data = await api('book', {}, Object.assign({
        service_id: state.service.id, date: state.date, time: state.time,
        website: e.target.website ? e.target.website.value : '',
      }, state.form));
      if (!data.token) throw new Error('Hiba történt.');
      state.result = data;
      store.add({ token: data.token, service: state.service.name, when: data.booking.when, start: state.date });
      state.busy = false;
      go('done');
    } catch (err) {
      state.busy = false;
      if (err.status === 409) {
        toast(err.message, true);
        state.time = null;
        go('date');
        pickDate(state.date);
        return;
      }
      state.errors = err.fields || {};
      render();
      toast(err.message, true);
      const bad = document.querySelector('.invalid');
      if (bad) bad.focus();
    }
  }

  function viewDone() {
    const b = state.result.booking;
    return el('div', { class: 'card' },
      el('div', { class: 'big-ok', 'aria-hidden': 'true' }, b.status === 'pending' ? '⏳' : '✅'),
      el('h1', {}, b.status === 'pending' ? 'Foglalási kérés elküldve' : 'Sikeres foglalás!'),
      el('p', {}, b.status === 'pending' ? 'Hamarosan visszaigazoljuk emailben.' : S.texts.success),
      el('dl', { class: 'summary' },
        el('dt', {}, 'Szolgáltatás'), el('dd', {}, b.service_name),
        el('dt', {}, 'Időpont'), el('dd', {}, b.when),
        el('dt', {}, 'Állapot'), el('dd', {}, el('span', { class: 'badge ' + b.status }, STATUS[b.status]))),
      el('div', { class: 'row' },
        el('a', { class: 'btn', href: 'api/?r=ics&token=' + state.result.token }, '📅 Naptárhoz adás'),
        el('a', { class: 'btn ghost', href: '?foglalas=' + state.result.token }, 'Foglalás kezelése')),
      el('hr'),
      el('button', { class: 'link-btn', type: 'button', onclick: () => { state.form = { fields: {} }; state.result = null; go('service'); } }, 'Új foglalás'));
  }

  async function viewManage(token) {
    app.replaceChildren(el('p', { class: 'muted' }, 'Betöltés…'));
    let b;
    try {
      b = (await api('booking', { token })).booking;
    } catch (e) {
      app.replaceChildren(el('div', { class: 'card' }, el('p', { class: 'alert' }, e.message), el('a', { class: 'btn', href: './' }, 'Új foglalás')));
      return;
    }
    const cancel = async () => {
      if (!confirm('Biztosan lemondod ezt a foglalást?')) return;
      try {
        await api('cancel', {}, { token });
        toast('A foglalást lemondtad.');
        viewManage(token);
      } catch (e) { toast(e.message, true); }
    };
    const active = b.status === 'pending' || b.status === 'confirmed';
    app.replaceChildren(el('div', { class: 'card' },
      el('h1', {}, 'Foglalásod'),
      el('dl', { class: 'summary' },
        el('dt', {}, 'Név'), el('dd', {}, b.name),
        el('dt', {}, 'Szolgáltatás'), el('dd', {}, b.service_name),
        el('dt', {}, 'Időpont'), el('dd', {}, b.when),
        el('dt', {}, 'Állapot'), el('dd', {}, el('span', { class: 'badge ' + b.status }, STATUS[b.status] || b.status))),
      el('div', { class: 'row' },
        active ? el('a', { class: 'btn', href: 'api/?r=ics&token=' + token }, '📅 Naptárhoz adás') : null,
        b.can_cancel ? el('button', { class: 'btn ghost', type: 'button', onclick: cancel }, 'Lemondás') : null,
        el('a', { class: 'btn ghost', href: './' }, 'Új foglalás')),
      active && !b.can_cancel && S.rules.customer_cancel
        ? el('p', { class: 'muted', style: 'margin-top:12px' }, 'Online lemondás a kezdés előtt ' + S.rules.cancel_hours + ' óráig lehetséges. Ezután kérjük, hívj minket.')
        : null));
  }

  function render() {
    if (state.step === 'service') app.replaceChildren(...viewService());
    else if (state.step === 'date') app.replaceChildren(...viewDate());
    else if (state.step === 'details') app.replaceChildren(...viewDetails());
    else if (state.step === 'done') app.replaceChildren(viewDone());
  }

  // ---------------------------------------------------------------- telepítés (PWA)
  function setupInstall() {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
    const btn = document.getElementById('install-btn');
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    if (!btn || standalone) return;
    let deferred = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferred = e;
      btn.hidden = false;
    });
    const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isIOS) btn.hidden = false;
    btn.addEventListener('click', async () => {
      if (deferred) {
        deferred.prompt();
        await deferred.userChoice;
        deferred = null;
        btn.hidden = true;
      } else if (isIOS) {
        alert('iPhone / iPad: koppints a Safari alján a Megosztás gombra (négyzet felfelé mutató nyíllal), majd válaszd a „Főképernyőhöz adás” lehetőséget.');
      }
    });
  }

  // ---------------------------------------------------------------- indulás
  setupInstall();
  const token = new URLSearchParams(location.search).get('foglalas');
  if (token) viewManage(token);
  else render();
})();
