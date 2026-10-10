// Ügyintézési Segéd – tanító szerkesztő (admin: bármely chatbot; ügyfél-fiók: a sajátja).
// A chatbot témái (kulcsszavak → válasz) szerkesztése és kipróbálása. Az egyeztetés
// ugyanaz, mint a chat.html-ben — ha ott változik, itt is kövesd.
(function () {
  'use strict';
  const { el, fill, field, toast, copy, fail } = window.Ugy;

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

  // A szerver válaszából szerkeszthető állapot (az előzőből megtartja a nyitott témát és a próbakérdést).
  function prepare(d, prev) {
    const kb = JSON.parse(JSON.stringify(d.kb || {}));
    kb.temak = Array.isArray(kb.temak) ? kb.temak : [];
    kb.temak.forEach((t) => { t.keys = Array.isArray(t.keys) ? t.keys : []; });
    return { d, kb, dirty: false, open: prev ? prev.open : -1, filter: '', test: prev ? prev.test : '' };
  }

  // o: { render, save(kb) → Promise, reload() → Promise, reset?() → Promise, top: [node], intro }
  function view(T, o) {
    const kb = T.kb;
    const dirty = () => { if (!T.dirty) { T.dirty = true; saveBar.classList.add('dirty'); status.textContent = 'Nem mentett változások'; } };
    const changed = () => { T.dirty = true; o.render(); };

    const head = el('div', { class: 'card form' },
      el('div', { class: 'row between' },
        el('h2', { style: 'margin:0' }, 'Tanítás'),
        el('a', { class: 'btn small ghost', href: T.d.chat_url, target: '_blank', rel: 'noopener' }, 'Chat megnyitása ↗')),
      o.top || null,
      el('p', { class: 'muted', style: 'margin:0' }, o.intro || 'A chatbot a kérdésben lévő kulcsszavak alapján választ témát, és a téma válaszát adja. Ha egy kérdésre rosszul vagy nem válaszol, próbáld ki lent, és adj hozzá kulcsszót vagy új témát. Mentés után a chat azonnal az új tudással válaszol.'),
      T.d.live === false ? el('p', { class: 'alert', style: 'margin:0' }, 'Nincs aktív előfizetés, ezért a chat most nem válaszol a weboldalon. A tanítás ettől még menthető, és a befizetés után azonnal él.') : null);

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
            T.open = kb.temak.length - 1; T.filter = ''; changed();
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
        el('button', { class: 'btn small ghost', type: 'button', style: 'margin-top:6px', onclick: () => { T.open = best.i; T.filter = ''; o.render(); } }, 'Téma szerkesztése'));
    };
    const testIn = el('input', { id: 'kb-test', placeholder: 'pl. mennyibe kerül?', value: T.test, oninput: runTest });
    const test = el('div', { class: 'card form' }, el('h3', { style: 'margin:0' }, 'Kipróbálás'), testIn, testOut);
    runTest();

    // általános
    const gen = el('details', { class: 'card form' },
      el('summary', { style: 'font-weight:700;cursor:pointer' }, 'Általános: név, üdvözlés, ha nem érti'),
      el('div', { class: 'grid2' },
        field('A chat neve (fejléc)', el('input', { value: kb.nev || '', oninput: (e) => { kb.nev = e.target.value; dirty(); } })),
        field('Szín', el('input', { type: 'color', value: kb.szin || '#4f46e5', oninput: (e) => { kb.szin = e.target.value; dirty(); } }))),
      field('Logó címe (nem kötelező)', el('input', { value: kb.logo || '', placeholder: 'https://a-weboldalad.hu/logo.png', oninput: (e) => { kb.logo = e.target.value; dirty(); } })),
      field('Üdvözlés', el('textarea', { oninput: (e) => { kb.udvozles = e.target.value; dirty(); } }, kb.udvozles || '')),
      field('Ha nem érti a kérdést', el('textarea', { oninput: (e) => { kb.nem_ertem = e.target.value; dirty(); } }, kb.nem_ertem || '')));

    // témák
    const f = norm(T.filter);
    const shown = kb.temak.map((t, i) => [t, i]).filter(([t]) => !f || norm(t.title + ' ' + t.keys.join(' ') + ' ' + t.answer).includes(f));
    const move = (i, d) => { const j = i + d; if (j < 0 || j >= kb.temak.length) return; [kb.temak[i], kb.temak[j]] = [kb.temak[j], kb.temak[i]]; if (T.open === i) T.open = j; changed(); };
    const topic = ([t, i]) => {
      const isOpen = T.open === i;
      const headRow = el('div', { class: 'row between', style: 'gap:6px' },
        el('button', { class: 'link-btn', type: 'button', style: 'text-align:left;flex:1;min-width:0;text-decoration:none;color:inherit', 'aria-expanded': isOpen ? 'true' : 'false', onclick: () => { T.open = isOpen ? -1 : i; o.render(); } },
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
              kb.temak.splice(i, 1); T.open = -1; changed();
            } }, 'Téma törlése'))));
    };
    const search = el('input', { type: 'search', class: 'topics-search', placeholder: 'Keresés a témákban…', value: T.filter, 'aria-label': 'Keresés a témákban', oninput: (e) => {
      T.filter = e.target.value; o.render();
      const s = document.querySelector('.topics-search'); if (s) { s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
    } });
    const topics = el('div', { class: 'card form' },
      el('div', { class: 'row between' }, el('h3', { style: 'margin:0' }, 'Témák (' + kb.temak.length + ')'),
        el('button', { class: 'btn small', type: 'button', onclick: () => { kb.temak.push({ id: '', title: '', keys: [], answer: '' }); T.open = kb.temak.length - 1; T.filter = ''; changed(); setTimeout(() => document.querySelector('.topic.open input')?.focus(), 50); } }, '+ Új téma')),
      el('p', { class: 'muted', style: 'margin:0;font-size:.88rem' }, '★ Az első 4 téma gyorsgombként jelenik meg a chat alján. A sorrendet a nyilakkal állíthatod.'),
      search,
      shown.length ? el('div', { class: 'topics' }, shown.map(topic)) : el('p', { class: 'muted' }, kb.temak.length ? 'Nincs találat.' : 'Még nincs téma. Kezdd az „+ Új téma” gombbal, vagy írj be fent egy kérdést.'));

    // mentés
    const status = el('span', { class: 'muted' }, T.dirty ? 'Nem mentett változások' : 'Minden mentve');
    const save = async () => {
      try {
        const r = await o.save(kb);
        toast('Mentve – a chat már ezzel válaszol (' + r.temak + ' téma)');
        await o.reload();
        o.render();
      } catch (e) { fail(e); }
    };
    const saveBar = el('div', { class: 'card row between savebar' + (T.dirty ? ' dirty' : '') },
      status,
      el('div', { class: 'row' },
        o.reset ? el('button', { class: 'btn ghost small', type: 'button', onclick: async () => {
          if (!confirm('Visszaállítod az alap tudástárat? A tanított változat törlődik.')) return;
          try { await o.reset(); toast('Visszaállítva az alapra'); await o.reload(); o.render(); } catch (e) { fail(e); }
        } }, 'Vissza az alapra') : null,
        el('button', { class: 'btn ghost small', type: 'button', onclick: () => copy(JSON.stringify(kb, null, 2)) }, 'Másolás (JSON)'),
        el('button', { class: 'btn', type: 'button', onclick: save }, 'Mentés')));

    return el('div', { class: 'teach' }, head, el('div', { class: 'grid2' }, test, gen), topics, saveBar);
  }

  // figyelmeztetés, ha mentés nélkül zárnák be az oldalt
  let current = null;
  window.addEventListener('beforeunload', (e) => { if (current && current.dirty) { e.preventDefault(); e.returnValue = ''; } });

  window.UgyTeach = { prepare: (d, prev) => (current = prepare(d, prev)), view, matchTopics };
})();
