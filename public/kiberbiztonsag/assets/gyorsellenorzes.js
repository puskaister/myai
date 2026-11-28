// Kiberbiztonsági gyorsellenőrzés: pontszám, teendők száma, domain-vizsgálat, teendőlista e-mailben.
// A válaszok és az utolsó domain-eredmény a böngészőben (localStorage) maradnak; a szerver csak
// a teendőlista kérésekor kapja meg őket (api/report.php), és ott újraszámolja az eredményt.
(function () {
  'use strict';
  var DATA = JSON.parse(document.getElementById('kb-data').textContent);
  var QS = DATA.questions;
  var KEY = 'myai-kiber-v1';
  var API = '/kiberbiztonsag/api/';
  var PRIO = { now: 'Azonnal', soon: '30 napon belül', later: 'Később' };
  var VALUE = { igen: 1, reszben: 0.5, nem: 0, nemtudom: 0 };

  var state = load();
  var $ = function (id) { return document.getElementById(id); };

  function load() {
    try { var s = JSON.parse(localStorage.getItem(KEY) || '{}'); return { answers: s.answers || {}, domain: s.domain || null, sentTo: s.sentTo || '' }; }
    catch (e) { return { answers: {}, domain: null, sentTo: '' }; }
  }
  function save() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) { /* privát mód */ } }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function prioOf(w) { return w >= 8 ? 'now' : (w >= 5 ? 'soon' : 'later'); }
  function level(score) {
    if (score == null) return { cls: '', txt: 'Válaszolj a kérdésekre' };
    if (score >= 75) return { cls: 'good', txt: 'Jó alapok, kevés teendővel' };
    if (score >= 50) return { cls: 'mid', txt: 'Közepes védelem, vannak fontos hiányok' };
    return { cls: 'bad', txt: 'Sérülékeny, érdemes most lépni' };
  }

  function questionnaireScore() {
    var got = 0, max = 0, answered = 0;
    QS.forEach(function (q) {
      var a = state.answers[q.id];
      if (!a) return;
      answered++; max += q.w; got += q.w * VALUE[a];
    });
    return { score: answered ? Math.round(100 * got / max) : null, answered: answered };
  }

  // A teendők száma prioritásonként (a részletes lista a levélben van; ugyanez a szabály: _inc/kiber-lib.php).
  function todoCounts() {
    var c = { now: 0, soon: 0, later: 0 };
    QS.forEach(function (q) {
      var a = state.answers[q.id];
      if (a && a !== 'igen') c[prioOf(q.w)]++;
    });
    if (state.domain && state.domain.items) state.domain.items.forEach(function (it) { if (it.fix) c[it.fix.prio]++; });
    return c;
  }

  function render() {
    var qs = questionnaireScore();
    var ds = state.domain ? state.domain.score : null;
    var total = qs.score == null ? ds : (ds == null ? qs.score : Math.round(qs.score * 0.7 + ds * 0.3));
    var lv = level(total);
    var started = qs.answered > 0 || !!state.domain;

    $('kb-score-num').textContent = total == null ? '–' : total;
    $('kb-gauge').style.setProperty('--p', total || 0);
    $('kb-score').className = 'card kb-score ' + lv.cls;
    $('kb-level').textContent = lv.txt;
    $('kb-progress').textContent = qs.answered + ' / ' + QS.length + ' kérdés megválaszolva' + (state.domain ? ', domain megvizsgálva' : '');
    $('kb-sub-q').textContent = qs.score == null ? '–' : qs.score;
    $('kb-sub-d').textContent = ds == null ? '–' : ds;

    var c = todoCounts(), n = c.now + c.soon + c.later;
    $('kb-counts').innerHTML = !started ? '<li class="kb-muted">Itt jelenik meg, hány teendőt találtunk.</li>'
      : (n === 0 ? '<li class="kb-muted">Eddig nincs teendő. Szép munka!</li>'
      : ['now', 'soon', 'later'].map(function (p) {
          return '<li><span class="kb-pill ' + p + '">' + PRIO[p] + '</span><b>' + c[p] + '</b></li>';
        }).join(''));
    $('kb-report-summary').textContent = !started
      ? 'Válaszolj legalább egy kérdésre, vagy vizsgáld meg a domaint, és összeállítjuk a teendőidet.'
      : (n === 0 ? 'Eddig nem találtunk teendőt. A teljes eredményt így is elküldjük, ha kéred.'
      : n + ' teendőt találtunk' + (c.now ? ', ebből ' + c.now + ' azonnali' : '') + '. A részletes, lépésenkénti listát e-mailben küldjük el.');
  }

  // --- kérdőív
  document.querySelectorAll('.kb-q input[type=radio]').forEach(function (inp) {
    if (state.answers[inp.name] === inp.value) inp.checked = true;
    inp.addEventListener('change', function () {
      state.answers[inp.name] = inp.value;
      inp.closest('.kb-q').classList.add('done');
      save(); render();
    });
    if (inp.checked) inp.closest('.kb-q').classList.add('done');
  });

  // --- domain-vizsgálat
  var STATUS = { ok: ['Rendben', 'ok'], warn: ['Figyelmeztetés', 'warn'], bad: ['Hiba', 'bad'], info: ['Info', 'info'] };
  function renderDomain() {
    var box = $('kb-domain-result');
    var d = state.domain;
    if (!d) { box.innerHTML = ''; return; }
    box.innerHTML = '<div class="kb-domain-head"><b>' + esc(d.domain) + '</b><span>' + d.score + ' / 100</span></div><ul class="kb-items">' +
      d.items.map(function (it) {
        var s = STATUS[it.status] || STATUS.info;
        return '<li class="kb-item ' + s[1] + '"><span class="kb-dot" aria-label="' + s[0] + '"></span><div><b>' + esc(it.label) + '</b><p>' + esc(it.detail) + '</p></div></li>';
      }).join('') + '</ul>';
  }
  if (state.domain) $('kb-domain').value = state.domain.domain;
  $('kb-domain-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var val = $('kb-domain').value.trim();
    var btn = $('kb-domain-btn'), box = $('kb-domain-result');
    if (!val) { box.innerHTML = '<p class="kb-err">Add meg a domaint, például: cegem.hu</p>'; return; }
    btn.disabled = true; btn.textContent = 'Vizsgálat…';
    box.innerHTML = '<p class="kb-muted kb-loading">DNS-rekordok és tanúsítvány ellenőrzése, ez 5–20 másodperc…</p>';
    fetch(API + 'domain.php?d=' + encodeURIComponent(val), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { error: 'Váratlan válasz a szervertől (' + r.status + ').' }; }); })
      .then(function (res) {
        if (res.error || !res.items) throw new Error(res.error || 'A vizsgálat nem sikerült.');
        state.domain = res; save(); renderDomain(); render();
        // ha még nincs e-mail megadva, a domainből kitaláljuk a céges címet
        var em = $('kb-email');
        if (em && !em.value) em.placeholder = 'nev@' + res.domain;
      })
      .catch(function (err) {
        box.innerHTML = '<p class="kb-err">' + esc(err && err.message ? err.message : 'Hálózati hiba, próbáld újra.') + '</p>';
      })
      .then(function () { btn.disabled = false; btn.textContent = 'Vizsgálat'; });
  });

  // --- teendőlista e-mailben
  var form = $('kb-mail-form'), msg = $('kb-form-msg'), sendBtn = $('kb-send');
  function setMsg(text, cls) { msg.textContent = text; msg.className = 'kb-form-msg' + (cls ? ' ' + cls : ''); }
  if (state.sentTo) setMsg('Korábban elküldtük a teendőlistát ide: ' + state.sentTo + '. Ha azóta módosítottál a válaszokon, kérd újra.', 'ok');
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var email = $('kb-email').value.trim();
    var qs = questionnaireScore();
    if (!qs.answered && !state.domain) { setMsg('Előbb válaszolj legalább egy kérdésre, vagy vizsgáld meg a domaint.', 'err'); return; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { setMsg('Adj meg egy érvényes e-mail-címet.', 'err'); $('kb-email').focus(); return; }
    if (!$('kb-consent').checked) { setMsg('A küldéshez jelöld be, hogy elolvastad az adatkezelési tájékoztatót.', 'err'); return; }
    sendBtn.disabled = true; sendBtn.textContent = 'Küldés…'; setMsg('', '');
    fetch(API + 'report.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        email: email, company: $('kb-company').value.trim(), website: $('kb-website').value,
        consent: true, newsletter: $('kb-newsletter').checked,
        answers: state.answers, domain: state.domain ? state.domain.domain : ''
      })
    })
      .then(function (r) { return r.json().catch(function () { return { error: 'Váratlan válasz a szervertől (' + r.status + ').' }; }); })
      .then(function (res) {
        if (!res.ok) throw new Error(res.error || 'A küldés nem sikerült.');
        state.sentTo = email; save();
        setMsg('Elküldtük a teendőlistát ide: ' + email + '. Ha pár percen belül nem érkezik meg, nézd meg a spam mappát is.' +
          ($('kb-newsletter').checked ? ' A feliratkozást a levélben lévő linkkel tudod megerősíteni.' : ''), 'ok');
      })
      .catch(function (err) { setMsg(err && err.message ? err.message : 'Hálózati hiba, próbáld újra.', 'err'); })
      .then(function () { sendBtn.disabled = false; sendBtn.textContent = 'Kérem a teendőlistát'; });
  });

  $('kb-reset').addEventListener('click', function () {
    if (!confirm('Biztosan törlöd a válaszokat és a domain-eredményt?')) return;
    state = { answers: {}, domain: null, sentTo: '' }; save();
    document.querySelectorAll('.kb-q input[type=radio]').forEach(function (i) { i.checked = false; });
    document.querySelectorAll('.kb-q.done').forEach(function (q) { q.classList.remove('done'); });
    $('kb-domain').value = ''; setMsg('', ''); renderDomain(); render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  renderDomain();
  render();
  document.documentElement.setAttribute('data-kb', 'ready'); // a CI böngészős tesztje ezt keresi
})();
