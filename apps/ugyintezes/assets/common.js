// Ügyintézési Segéd – közös felületi segédek (admin és ügyfél-fiók).
(function () {
  'use strict';

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

  function toast(msg, isError) {
    const t = el('div', { class: 'toast' + (isError ? ' err' : ''), role: 'status' }, msg);
    document.body.append(t);
    setTimeout(() => t.remove(), 3500);
  }

  // JSON API a ../api/ alatt; 401-nél az onAuth hívódik (belépő képernyő).
  function makeApi(onAuth, publicRoutes) {
    return async function api(route, params, body) {
      const qs = new URLSearchParams(Object.assign({ r: route }, params || {}));
      const opts = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
      if (body) { opts.method = 'POST'; opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
      let res, data;
      try {
        res = await fetch('../api/?' + qs.toString(), opts);
        data = await res.json();
      } catch (e) { throw Object.assign(new Error('Nem sikerült kapcsolódni a szerverhez.'), { status: 0 }); }
      if (res.status === 401 && !publicRoutes.includes(route)) { onAuth(); throw Object.assign(new Error(''), { silent: true }); }
      if (!res.ok) throw Object.assign(new Error(data.error || 'Hiba történt.'), { status: res.status });
      return data;
    };
  }

  window.Ugy = {
    el, fill, toast, makeApi,
    fail: (e) => { if (!e.silent) toast(e.message, true); },
    field: (label, control, hint) => el('label', {}, label, control, hint ? el('small', {}, hint) : null),
    huDate: (d) => (d ? String(d).replace(/-/g, '. ') + '.' : '–'),
    copy: (t) => navigator.clipboard?.writeText(t).then(() => toast('Kimásolva'), () => toast('Jelöld ki és másold ki kézzel.', true)),
  };
})();
