// Kiberbiztonsági gyorsellenőrzés: pontszám, teendőlista, domain-vizsgálat.
// A válaszok és az utolsó domain-eredmény a böngészőben (localStorage) maradnak.
(function () {
  'use strict';
  var DATA = JSON.parse(document.getElementById('kb-data').textContent);
  var QS = DATA.questions;
  var KEY = 'myai-kiber-v1';
  var API = '/kiberbiztonsag/api/domain.php';
  var PRIO = { now: 'Azonnal', soon: '30 napon belül', later: 'Később' };
  var PRIO_ORDER = { now: 0, soon: 1, later: 2 };
  var VALUE = { igen: 1, reszben: 0.5, nem: 0, nemtudom: 0 };

  var state = load();
  var $ = function (id) { return document.getElementById(id); };

  function load() {
    try { var s = JSON.parse(localStorage.getItem(KEY) || '{}'); return { answers: s.answers || {}, domain: s.domain || null }; }
    catch (e) { return { answers: {}, domain: null }; }
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

  function todos() {
    var list = [];
    QS.forEach(function (q) {
      var a = state.answers[q.id];
      if (!a || a === 'igen') return;
      var steps = q.steps.slice();
      if (a === 'nemtudom') steps.unshift('Először derítsd ki: kérdezd meg az informatikusodat vagy a szolgáltatót, hogy ez be van-e állítva. Ha igen, ez a teendő kipipálható.');
      if (a === 'reszben') steps.unshift('Részben már megvan: nézd át, hol hiányzik még, és ott pótold.');
      list.push({ prio: prioOf(q.w), w: q.w * (a === 'reszben' ? 0.5 : 1), title: q.fix, steps: steps, src: DATA.categories[q.cat], unsure: a === 'nemtudom' });
    });
    if (state.domain && state.domain.items) {
      state.domain.items.forEach(function (it) {
        if (!it.fix) return;
        list.push({ prio: it.fix.prio, w: Math.max(it.max - it.points, 1), title: it.fix.title, steps: it.fix.steps, src: 'Domain: ' + state.domain.domain });
      });
    }
    list.sort(function (a, b) { return (PRIO_ORDER[a.prio] - PRIO_ORDER[b.prio]) || (b.w - a.w); });
    return list;
  }

  function render() {
    var qs = questionnaireScore();
    var ds = state.domain ? state.domain.score : null;
    var total = qs.score == null ? ds : (ds == null ? qs.score : Math.round(qs.score * 0.7 + ds * 0.3));
    var lv = level(total);

    $('kb-score-num').textContent = total == null ? '–' : total;
    $('kb-gauge').style.setProperty('--p', total || 0);
    $('kb-score').className = 'card kb-score ' + lv.cls;
    $('kb-level').textContent = lv.txt;
    $('kb-progress').textContent = qs.answered + ' / ' + QS.length + ' kérdés megválaszolva' + (state.domain ? ', domain megvizsgálva' : '');
    $('kb-sub-q').textContent = qs.score == null ? '–' : qs.score;
    $('kb-sub-d').textContent = ds == null ? '–' : ds;

    var list = todos();
    $('kb-top').innerHTML = list.length
      ? list.slice(0, 3).map(function (t) { return '<li><span class="kb-pill ' + t.prio + '">' + PRIO[t.prio] + '</span> ' + esc(t.title) + '</li>'; }).join('')
      : '<li class="kb-muted">' + (qs.answered || state.domain ? 'Nincs teendő. Szép munka!' : 'Itt jelennek meg, ahogy válaszolsz.') + '</li>';

    if (!list.length) {
      $('kb-todo').innerHTML = '<p class="kb-muted">' + (qs.answered || state.domain ? 'A megadott válaszok alapján nincs teendő.' : 'Válaszolj legalább egy kérdésre, vagy vizsgáld meg a domaint.') + '</p>';
    } else {
      var html = '';
      ['now', 'soon', 'later'].forEach(function (p) {
        var group = list.filter(function (t) { return t.prio === p; });
        if (!group.length) return;
        html += '<h3 class="kb-group"><span class="kb-pill ' + p + '">' + PRIO[p] + '</span> ' + group.length + ' teendő</h3><div class="kb-todos">';
        group.forEach(function (t) {
          html += '<details class="card kb-todo"><summary><b>' + esc(t.title) + '</b><small>' + esc(t.src) + (t.unsure ? ' · ellenőrizendő' : '') + '</small></summary><ol>' +
            t.steps.map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('') + '</ol></details>';
        });
        html += '</div>';
      });
      $('kb-todo').innerHTML = html;
    }
    $('kb-print-meta').textContent = 'Összpontszám: ' + (total == null ? '–' : total + '/100') + (state.domain ? ' · Domain: ' + state.domain.domain : '') + ' · ' + new Date().toLocaleDateString('hu-HU');
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
    fetch(API + '?d=' + encodeURIComponent(val), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { error: 'Váratlan válasz a szervertől (' + r.status + ').' }; }); })
      .then(function (res) {
        if (res.error || !res.items) throw new Error(res.error || 'A vizsgálat nem sikerült.');
        state.domain = res; save(); renderDomain(); render();
      })
      .catch(function (err) {
        box.innerHTML = '<p class="kb-err">' + esc(err && err.message ? err.message : 'Hálózati hiba, próbáld újra.') + '</p>';
      })
      .then(function () { btn.disabled = false; btn.textContent = 'Vizsgálat'; });
  });

  // --- jelentés
  function reportText() {
    var qs = questionnaireScore();
    var lines = ['Kiberbiztonsági gyorsellenőrzés – my-ai.hu', new Date().toLocaleDateString('hu-HU'), ''];
    lines.push('Összpontszám: ' + $('kb-score-num').textContent + '/100 (' + $('kb-level').textContent + ')');
    lines.push('Kérdőív: ' + (qs.score == null ? '–' : qs.score) + '/100, ' + qs.answered + '/' + QS.length + ' kérdés megválaszolva');
    if (state.domain) {
      lines.push('Domain (' + state.domain.domain + '): ' + state.domain.score + '/100');
      state.domain.items.forEach(function (it) { lines.push('  [' + (STATUS[it.status] || STATUS.info)[0] + '] ' + it.label + ': ' + it.detail); });
    }
    lines.push('');
    ['now', 'soon', 'later'].forEach(function (p) {
      var group = todos().filter(function (t) { return t.prio === p; });
      if (!group.length) return;
      lines.push(PRIO[p].toUpperCase());
      group.forEach(function (t) {
        lines.push('- ' + t.title + ' (' + t.src + ')');
        t.steps.forEach(function (s, i) { lines.push('    ' + (i + 1) + '. ' + s); });
      });
      lines.push('');
    });
    lines.push('Ellenőrizd te is: https://my-ai.hu/kiberbiztonsag/gyorsellenorzes/');
    return lines.join('\n');
  }
  $('kb-copy').addEventListener('click', function () {
    var txt = reportText(), msg = $('kb-copy-msg');
    var done = function () { msg.textContent = 'A jelentés a vágólapon van: beillesztheted egy e-mailbe.'; };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done, fallback); else fallback();
    function fallback() {
      var ta = document.createElement('textarea'); ta.value = txt; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); done(); } catch (e) { msg.textContent = 'A másolás nem sikerült.'; }
      ta.remove();
    }
  });
  $('kb-print').addEventListener('click', function () {
    document.querySelectorAll('.kb-todo').forEach(function (d) { d.open = true; });
    window.print();
  });
  $('kb-reset').addEventListener('click', function () {
    if (!confirm('Biztosan törlöd a válaszokat és a domain-eredményt?')) return;
    state = { answers: {}, domain: null }; save();
    document.querySelectorAll('.kb-q input[type=radio]').forEach(function (i) { i.checked = false; });
    document.querySelectorAll('.kb-q.done').forEach(function (q) { q.classList.remove('done'); });
    $('kb-domain').value = ''; renderDomain(); render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  renderDomain();
  render();
})();
