// Ügyintézési Segéd – ügyfél-fiók: belépés, jelszó beállítása, a saját chatbot tanítása.
(function () {
  'use strict';
  const { el, fill, toast, field, huDate, fail } = window.Ugy;
  const root = document.getElementById('portal');
  const api = window.Ugy.makeApi(() => showLogin(), ['portal/login', 'portal/password', 'portal/forgot']);
  const state = { me: null, teach: null };

  function narrow(title, ...content) {
    fill(document.getElementById('top-actions'));
    document.getElementById('who').textContent = 'A chatbotod tanítása';
    fill(root, el('div', { class: 'narrow' }, el('div', { class: 'card' }, el('h1', {}, title), ...content)));
  }

  function showLogin() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try { await api('portal/login', {}, { email: f.email.value, password: f.password.value }); start(); } catch (err) { fail(err); }
    } },
    el('p', { class: 'muted', style: 'margin:0' }, 'Lépj be a megrendeléskor megadott email címeddel, és tanítsd a weboldalad chatbotját: témák, kulcsszavak, válaszok.'),
    field('Email', el('input', { name: 'email', type: 'email', required: true, autocomplete: 'username' })),
    field('Jelszó', el('input', { name: 'password', type: 'password', required: true, autocomplete: 'current-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Belépés'),
    el('button', { class: 'link-btn', type: 'button', onclick: showForgot }, 'Elfelejtett jelszó / még nincs jelszavam'));
    narrow('Belépés', f);
  }

  function showForgot() {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      try {
        await api('portal/forgot', {}, { email: f.email.value });
        fill(f, el('p', { class: 'ok' }, 'Ha ezzel az email címmel van előfizetés, elküldtük a jelszóbeállító linket (1 óráig érvényes). Nézd meg a levélszemét mappát is.'),
          el('button', { class: 'btn ghost block', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
      } catch (err) { fail(err); }
    } },
    el('p', { class: 'muted', style: 'margin:0' }, 'Add meg a megrendeléskor használt email címet, és küldünk egy linket, amivel jelszót állíthatsz be.'),
    field('Email', el('input', { name: 'email', type: 'email', required: true })),
    el('button', { class: 'btn block', type: 'submit' }, 'Link küldése'),
    el('button', { class: 'link-btn', type: 'button', onclick: showLogin }, 'Vissza a belépéshez'));
    narrow('Jelszó beállítása', f);
  }

  function showSetPassword(token) {
    const f = el('form', { class: 'form', onsubmit: async (e) => {
      e.preventDefault();
      if (f.password.value !== f.password2.value) return toast('A két jelszó nem egyezik.', true);
      try {
        await api('portal/password', {}, { token, password: f.password.value });
        history.replaceState(null, '', location.pathname);
        toast('Jelszó beállítva');
        start();
      } catch (err) { fail(err); }
    } },
    field('Új jelszó (min. 8 karakter)', el('input', { name: 'password', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    field('Még egyszer', el('input', { name: 'password2', type: 'password', required: true, minlength: 8, autocomplete: 'new-password' })),
    el('button', { class: 'btn block', type: 'submit' }, 'Jelszó beállítása és belépés'));
    narrow('Jelszó beállítása', f);
  }

  async function loadTeach() {
    const d = await api('portal/kb');
    state.teach = window.UgyTeach.prepare(d, state.teach);
  }

  function render() {
    const me = state.me;
    document.getElementById('who').textContent = me.name;
    fill(document.getElementById('top-actions'), el('button', { class: 'btn small ghost', type: 'button', style: 'color:var(--on-primary);border-color:currentColor', onclick: async () => {
      if (state.teach?.dirty && !confirm('A nem mentett változások elvesznek. Kilépsz?')) return;
      try { await api('portal/logout', {}, {}); } catch (e) { /* kilépés hiba esetén is */ }
      state.me = null; state.teach = null; showLogin();
    } }, 'Kilépés'));
    const sub = me.active_now
      ? el('span', { class: 'badge active' }, 'Előfizetés aktív · ' + huDate(me.paid_until) + '-ig')
      : el('span', { class: 'badge expired' }, me.status === 'pending' ? 'A chat az első befizetés után indul' : 'Az előfizetés nem aktív');
    fill(root, window.UgyTeach.view(state.teach, {
      render,
      top: el('div', { class: 'row' }, el('strong', {}, me.name), sub),
      intro: 'Itt tanítod a weboldalad chatbotját. Minden témához adj meg pár kulcsszót és a választ; a chatbot a látogató kérdésében lévő kulcsszavak alapján választ. Próbáld ki lent, mit válaszolna, és mentsd: a chat azonnal az új tudással válaszol.',
      save: (kb) => api('portal/kb/save', {}, { kb }),
      reload: loadTeach,
    }));
  }

  async function start() {
    fill(root, el('p', { class: 'muted' }, 'Betöltés…'));
    try {
      const { customer } = await api('portal/me');
      if (!customer) return showLogin();
      state.me = customer;
      await loadTeach();
      render();
    } catch (e) { fail(e); }
  }

  const token = new URLSearchParams(location.search).get('token');
  if (token) showSetPassword(token);
  else start();
})();
