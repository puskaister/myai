// Chatbot admin: beszélgetések, betanítás, megjelenés/beágyazás, beállítások, fiók.
(function () {
  'use strict';

  const root = document.getElementById('admin');
  const USD_HUF = 370; // csak a költségbecslés kiírásához
  const state = { tab: 'conversations', data: null, conversations: [], filter: '', usage: null };

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
    if (body) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
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

  function toast(msg, isError) {
    const t = el('div', { class: 'toast' + (isError ? ' err' : ''), role: 'status' }, msg);
    document.body.append(t);
    setTimeout(() => t.remove(), 3500);
  }
  function fail(e) { if (!e.silent) toast(e.message, true); }

  function modal(title, body) {
    const back = el('div', { class: 'modal-back', onclick: (e) => { if (e.target === back) close(); } });
    const close = () => { back.remove(); document.removeEventListener('keydown', esc); if (location.hash) history.replaceState(null, '', location.pathname + location.search); };
    const esc = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', esc);
    back.append(el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
      el('div', { class: 'row between', style: 'margin-bottom:10px' }, el('h2', { style: 'margin:0' }, title), el('button', { class: 'icon-btn', type: 'button', onclick: close, 'aria-label': 'Bezárás' }, '✕')),
      body));
    document.body.append(back);
    return close;
  }

  const field = (label, control, hint) => el('label', {}, label, control, hint ? el('small', {}, hint) : null);
  const check = (label, checked, attrs) => el('label', { class: 'check' }, el('input', Object.assign({ type: 'checkbox', checked }, attrs || {})), label);
  const fmtDate = (dt) => String(dt).slice(0, 16).replace(' ', ' · ');

  // ---------------------------------------------------------------- belépés, jelszó
  function narrowCard(title, ...content) {
    fill(root, el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, title), ...content)));
  }

  function showLogin() {
    const form = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { await api('login', {}, { email: form.email.value, password: form.password.value }); start(); } catch (err) { fail(err); }
    } },
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    field('Jelszó', el('input', { name: 'password', type: 'password', required: true, autocomplete: 'current-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Belépés'),
    el('button', { class: 'link-btn', type: 'button', onclick: showForgot }, 'Elfelejtett jelszó?'));
    narrowCard('Belépés', form);
  }

  function showForgot() {
    const form = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try {
        await api('forgot', {}, { email: form.email.value });
        fill(form, 
          el('p', { class: 'ok' }, 'Ha ezzel az email címmel van admin fiók, elküldtük rá a jelszó-visszaállító linket (1 óráig érvényes). Nézd meg a spam mappát is.'),
          el('button', { class: 'btn ghost block', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
      } catch (err) { fail(err); }
    } },
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Link küldése'),
    el('button', { class: 'link-btn', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
    narrowCard('Elfelejtett jelszó', form);
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
    el('button', { class: 'btn block', type: 'submit' }, 'Jelszó beállítása'));
    narrowCard('Új jelszó beállítása', form);
  }

  // ---------------------------------------------------------------- keret
  const TABS = [['conversations', 'Beszélgetések'], ['training', 'Betanítás'], ['look', 'Megjelenés és beágyazás'], ['settings', 'Beállítások'], ['account', 'Fiók']];

  function render() {
    const tabs = el('nav', { class: 'tabs' }, TABS.map(([k, label]) =>
      el('button', { class: state.tab === k ? 'on' : '', type: 'button', onclick: () => { state.tab = k; render(); } }, label)));
    const view = { conversations: viewConversations, training: viewTraining, look: viewLook, settings: viewSettings, account: viewAccount }[state.tab]();
    fill(root, tabs, view);
  }

  async function reloadSettings() { state.data = await api('admin/settings'); }

  async function saveGroup(group, value, quiet) {
    try {
      await api('admin/settings', {}, { group, value });
      await reloadSettings();
      if (!quiet) toast('Mentve');
      render();
      return true;
    } catch (e) { fail(e); return false; }
  }

  // ---------------------------------------------------------------- beszélgetések
  async function loadConversations() {
    try {
      const [list, usage] = await Promise.all([
        api('admin/conversations', state.filter ? { status: state.filter } : {}),
        api('admin/usage'),
      ]);
      state.conversations = list.conversations;
      state.usage = usage;
    } catch (e) { fail(e); }
    if (state.tab === 'conversations') render();
  }

  function answerSummary(answers) {
    const qs = state.data.settings.questions;
    return qs.map((q) => answers[q.key]).filter(Boolean).slice(0, 3).join(' · ');
  }

  function viewConversations() {
    const u = state.usage;
    const qs = state.data.settings.questions;
    return el('div', {},
      !state.data.api_key ? el('p', { class: 'alert', style: 'margin-bottom:16px' }, 'Nincs beállítva Anthropic API kulcs (GitHub secret: ANTHROPIC_API_KEY) — a bot addig nem tud válaszolni.') : null,
      u ? el('div', { class: 'stat' },
        el('div', {}, el('b', {}, u.conversations), 'beszélgetés (' + u.month + ')'),
        el('div', {}, el('b', {}, u.complete), 'minden adattal'),
        el('div', {}, el('b', {}, '~' + Math.round(u.usd * USD_HUF).toLocaleString('hu-HU') + ' Ft'), 'becsült AI-költség ($' + u.usd.toFixed(2) + ')')) : null,
      el('div', { class: 'row between', style: 'margin-bottom:12px' },
        el('select', { style: 'width:auto', onchange: (e) => { state.filter = e.target.value; loadConversations(); } },
          [['', 'Összes'], ['complete', 'Minden adattal'], ['open', 'Folyamatban']].map(([v, l]) => el('option', { value: v, selected: state.filter === v }, l))),
        el('div', { class: 'row' },
          el('button', { class: 'btn small ghost', type: 'button', onclick: loadConversations }, 'Frissítés'),
          el('a', { class: 'btn small ghost', href: '../api/?r=admin/export' }, 'CSV export'))),
      state.conversations.length
        ? el('div', { class: 'list' }, state.conversations.map((c) => el('button', { class: 'item', type: 'button', onclick: () => openConversation(c.id), style: 'grid-template-columns:110px 1fr auto' },
          el('div', {}, el('div', { class: 'what' }, fmtDate(c.updated_at)), el('div', { class: 'what' }, c.user_messages + ' üzenet')),
          el('div', {}, el('div', { class: 'who' }, (c.seen ? '' : '● ') + (answerSummary(c.answers) || 'Még nincs rögzített válasz')),
            el('div', { class: 'what' }, qs.filter((q) => q.required).filter((q) => c.answers[q.key]).length + ' / ' + qs.filter((q) => q.required).length + ' kötelező adat')),
          el('span', { class: 'badge ' + (c.status === 'complete' ? 'confirmed' : 'pending') }, c.status === 'complete' ? 'Minden adat' : 'Folyamatban'))))
        : el('p', { class: 'note' }, 'Még nincs beszélgetés. Próbáld ki a chatet a „Chat megnyitása” gombbal!'));
  }

  async function openConversation(id) {
    let data;
    try { data = await api('admin/conversation', { id }); } catch (e) { return fail(e); }
    const c = data.conversation;
    const qs = state.data.settings.questions;
    const botName = state.data.settings.bot.name;
    const body = el('div', {},
      el('p', { class: 'muted' }, fmtDate(c.created_at) + (c.source_url ? ' · ' : ''), c.source_url ? el('a', { href: c.source_url, target: '_blank', rel: 'noopener' }, 'forrás oldal') : null),
      el('h3', {}, 'Válaszok'),
      el('dl', { class: 'answers' }, qs.map((q) => [
        el('dt', {}, q.question + (q.required ? '' : ' (nem kötelező)')),
        c.answers[q.key] ? el('dd', {}, c.answers[q.key]) : el('dd', { class: 'missing' }, 'nincs megadva'),
      ])),
      el('h3', {}, 'Beszélgetés'),
      el('div', { class: 'transcript' }, data.messages.map((m) => el('div', { class: 'msg ' + (m.role === 'user' ? 'user' : 'bot'), title: m.created_at }, (m.role === 'user' ? '' : botName + ': ') + m.content))),
      el('hr'),
      el('button', { class: 'btn small ghost', type: 'button', style: 'color:var(--danger)', onclick: async () => {
        if (!confirm('Végleg törlöd ezt a beszélgetést?')) return;
        try { await api('admin/conversation/delete', {}, { id }); close(); toast('Törölve'); loadConversations(); } catch (e) { fail(e); }
      } }, 'Beszélgetés törlése'));
    const close = modal('Beszélgetés #' + c.id, body);
    loadConversations();
  }

  // ---------------------------------------------------------------- betanítás
  function viewTraining() {
    const s = state.data.settings;
    const bot = s.bot;
    const questions = JSON.parse(JSON.stringify(s.questions));
    const qBox = el('div', { class: 'editable' });
    const drawQuestions = () => fill(qBox, 
      ...(questions.length ? questions.map((q, i) => el('div', { class: 'q-row' },
        field('Kérdés', el('input', { value: q.question, placeholder: 'pl. Milyen autóról van szó?', oninput: (e) => { q.question = e.target.value; } })),
        field('Megjegyzés a botnak (nem kötelező)', el('input', { value: q.hint, placeholder: 'pl. márka és típus; ha nem tudja, írja, hogy nem tudja', oninput: (e) => { q.hint = e.target.value; } })),
        el('div', { class: 'row' },
          check('Kötelező — a bot mindenképp megkérdezi', !!q.required, { onchange: (e) => { q.required = e.target.checked; } }),
          el('div', { class: 'row' },
            el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Feljebb', disabled: i === 0, onclick: () => { [questions[i - 1], questions[i]] = [questions[i], questions[i - 1]]; drawQuestions(); } }, '↑'),
            el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Lejjebb', disabled: i === questions.length - 1, onclick: () => { [questions[i + 1], questions[i]] = [questions[i], questions[i + 1]]; drawQuestions(); } }, '↓'),
            el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Kérdés törlése', onclick: () => { questions.splice(i, 1); drawQuestions(); } }, '✕'))))) : [el('p', { class: 'muted' }, 'Még nincs kérdés.')]),
      el('div', {}, el('button', { class: 'link-btn', type: 'button', onclick: () => { questions.push({ key: '', question: '', hint: '', required: true }); drawQuestions(); qBox.querySelector('.q-row:last-of-type input')?.focus(); } }, '+ új kérdés')));
    drawQuestions();

    const f = el('form', { class: 'card form' },
      el('h2', {}, 'Tudásanyag és viselkedés'),
      field('A bot neve', el('input', { name: 'name', required: true, value: bot.name })),
      field('Köszöntés (ezzel nyílik a chat)', el('textarea', { name: 'greeting', required: true }, bot.greeting)),
      field('Tudásanyag', el('textarea', { name: 'knowledge', style: 'min-height:260px' }, bot.knowledge),
        'Minden, amit a botnak tudnia kell: szolgáltatások, árak, nyitvatartás, cím, gyakori kérdések. Amit itt nem talál, arra azt mondja, hogy egy kolléga visszajelez.'),
      field('Hangnem', el('input', { name: 'tone', value: bot.tone }), 'pl. „Barátságos, tegeződő, rövid mondatok.” vagy „Udvarias, magázó.”'),
      field('Zárómondat, ha minden adat megvan', el('textarea', { name: 'completion' }, bot.completion)),
      field('Extra szabályok (nem kötelező)', el('textarea', { name: 'instructions' }, bot.instructions), 'pl. „Árajánlatot soha ne adj, csak tájékoztató árat.”'),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      saveGroup('bot', { name: f.name.value, greeting: f.greeting.value, knowledge: f.knowledge.value, tone: f.tone.value, completion: f.completion.value, instructions: f.instructions.value });
    });

    return el('div', {},
      el('div', { class: 'note', style: 'margin-bottom:16px' }, 'Tipp: mentés után nyisd meg a chatet, és próbáld ki úgy, mintha ügyfél lennél. ',
        el('a', { href: '../', target: '_blank', rel: 'noopener' }, 'Chat megnyitása ↗')),
      el('div', { class: 'grid2' },
        el('div', { class: 'card form' },
          el('h2', {}, 'Fő kérdések'),
          el('p', { class: 'muted', style: 'margin:0' }, 'A bot ezeket a beszélgetés során természetesen, egyenként felteszi, és a válaszokat külön rögzíti. Ha minden kötelező válasz megvan, emailt kapsz.'),
          qBox,
          el('button', { class: 'btn', type: 'button', onclick: () => saveGroup('questions', questions.filter((q) => q.question.trim())) }, 'Kérdések mentése')),
        f));
  }

  // ---------------------------------------------------------------- megjelenés, beágyazás
  function viewLook() {
    const s = state.data.settings;
    const base = state.data.base_url;
    const snippet = '<script src="' + base + '/widget.php" async></script>';
    const copy = (txt) => navigator.clipboard?.writeText(txt).then(() => toast('Kimásolva'), () => toast('Jelöld ki és másold ki kézzel.', true));

    const biz = el('form', { class: 'card form' },
      el('h2', {}, 'Cégadatok'),
      field('Cég neve *', el('input', { name: 'name', required: true, value: s.business.name })),
      field('Weboldal', el('input', { name: 'website', value: s.business.website })),
      field('Telefon', el('input', { name: 'phone', value: s.business.phone })),
      field('Email', el('input', { name: 'email', type: 'email', value: s.business.email })),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    biz.addEventListener('submit', (e) => { e.preventDefault(); saveGroup('business', { name: biz.name.value, website: biz.website.value, phone: biz.phone.value, email: biz.email.value }); });

    const color = el('form', { class: 'card form' },
      el('h2', {}, 'Szín'),
      field('Fő szín (fejléc, buborék, app ikon)', el('input', { name: 'primary', type: 'color', value: s.theme.primary })),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    color.addEventListener('submit', (e) => { e.preventDefault(); saveGroup('theme', { primary: color.primary.value }); });

    return el('div', { class: 'grid2' },
      el('div', { class: 'card form' },
        el('h2', {}, 'Beágyazás weboldalba'),
        el('p', { class: 'muted', style: 'margin:0' }, 'Másold be ezt az egy sort a weboldal HTML-jébe (a </body> elé) — a jobb alsó sarokban megjelenik a chat-buborék.'),
        el('div', { class: 'snippet' }, snippet),
        el('button', { class: 'btn small ghost', type: 'button', onclick: () => copy(snippet) }, 'Kód másolása'),
        el('hr'),
        el('h3', {}, 'Közvetlen link'),
        el('p', { class: 'muted', style: 'margin:0' }, 'Megosztható Facebookon, QR-kódban, emailben. Telefonon appként is telepíthető.'),
        el('div', { class: 'snippet' }, base + '/'),
        el('button', { class: 'btn small ghost', type: 'button', onclick: () => copy(base + '/') }, 'Link másolása')),
      el('div', {}, color, biz));
  }

  // ---------------------------------------------------------------- beállítások
  function viewSettings() {
    const s = state.data.settings;
    const notify = el('form', { class: 'card form' },
      el('h2', {}, 'Értesítés'),
      field('Email cím, ahová az összefoglaló érkezik', el('input', { name: 'admin_email', type: 'email', value: s.notify.admin_email })),
      check('Email, ha egy látogató minden kötelező kérdésre válaszolt', !!s.notify.send_admin, { name: 'send_admin' }),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    notify.addEventListener('submit', (e) => { e.preventDefault(); saveGroup('notify', { admin_email: notify.admin_email.value, send_admin: notify.send_admin.checked }); });

    const ai = el('form', { class: 'card form' },
      el('h2', {}, 'AI modell és keretek'),
      field('Modell', el('select', { name: 'model' }, Object.entries(state.data.models).map(([id, label]) => el('option', { value: id, selected: s.ai.model === id }, label))),
        'Az árak becslések, a beszélgetés hosszától függenek. A valós havi költség a Beszélgetések fülön látszik.'),
      field('Max. látogatói üzenet beszélgetésenként', el('input', { name: 'max_user_messages', type: 'number', min: 5, max: 100, value: s.ai.max_user_messages })),
      field('Max. új beszélgetés naponta', el('input', { name: 'daily_limit', type: 'number', min: 1, value: s.ai.daily_limit }), 'Költségvédelem: a keret elérése után a chat aznapra szünetel.'),
      el('button', { class: 'btn', type: 'submit' }, 'Mentés'));
    ai.addEventListener('submit', (e) => { e.preventDefault(); saveGroup('ai', { model: ai.model.value, max_user_messages: Number(ai.max_user_messages.value), daily_limit: Number(ai.daily_limit.value) }); });

    return el('div', { class: 'grid2' }, notify, ai);
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
      el('div', { class: 'grid2' }, field('Név', el('input', { name: 'name', required: true })), field('Email', el('input', { name: 'email', type: 'email', required: true }))),
      field('Jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
      el('button', { class: 'btn small', type: 'submit' }, 'Admin hozzáadása'));
    add.addEventListener('submit', async (e) => {
      e.preventDefault();
      try { await api('admin/admins/save', {}, { name: add.name.value, email: add.email.value, password: add.password.value }); await reloadSettings(); render(); toast('Admin hozzáadva'); } catch (err) { fail(err); }
    });
    return el('div', { class: 'grid2' },
      el('div', {},
        el('div', { class: 'card' }, el('h2', {}, me.name), el('p', { class: 'muted' }, me.email),
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
        el('hr'), el('h3', {}, 'Új admin'), add));
  }

  // ---------------------------------------------------------------- indulás
  async function start() {
    fill(root, el('p', { class: 'muted' }, 'Betöltés…'));
    try {
      const { admin } = await api('me');
      if (!admin) return showLogin();
      await reloadSettings();
      render();
      await loadConversations();
      const m = location.hash.match(/^#c(\d+)$/); // email linkből
      if (m) openConversation(Number(m[1]));
    } catch (e) { fail(e); }
  }

  const resetToken = new URLSearchParams(location.search).get('reset');
  if (resetToken) showReset(resetToken);
  else start();
})();
