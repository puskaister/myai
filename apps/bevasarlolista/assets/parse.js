// Magyar bevásárlólista-értelmező: a kimondott vagy begépelt szöveget tételekre
// bontja, mennyiséggel együtt.
//   "vegyél két kiló almát, tejet meg fél kenyeret" →
//   [{name: "Alma", qty: "2 kg"}, {name: "Tej", qty: ""}, {name: "Kenyér", qty: "fél"}]
// Böngészőben: window.parseShopping; Node-ban: require('./parse.js').
(function (root) {
  'use strict';

  const NUMBERS = {
    egy: 1, egyet: 1, két: 2, kettő: 2, kettőt: 2, három: 3, hármat: 3, négy: 4, négyet: 4, öt: 5, ötöt: 5,
    hat: 6, hatot: 6, hét: 7, hetet: 7, nyolc: 8, nyolcat: 8, kilenc: 9, kilencet: 9, tíz: 10, tizet: 10,
    tizenkét: 12, tizenkettő: 12, tizenkettőt: 12, tizenöt: 15, húsz: 20, huszat: 20, harminc: 30, ötven: 50, száz: 100,
    fél: 0.5, felet: 0.5, másfél: 1.5, másfelet: 1.5,
  };
  const VAGUE = { pár: 'pár', néhány: 'néhány', sok: 'sok' };
  const UNITS = [
    [/^(kg|kiló|kilót|kilogramm|kilogrammot)$/, 'kg'], [/^(dkg|deka|dekát|dekagramm)$/, 'dkg'],
    [/^(g|gr|gramm|grammot)$/, 'g'], [/^(l|liter|litert)$/, 'l'], [/^(dl|deci|decit|deciliter)$/, 'dl'],
    [/^(db|darab|darabot)$/, 'db'], [/^(csomag|csomagot|zacskó|zacskót|zacsi)$/, 'csomag'],
    [/^(doboz|dobozt)$/, 'doboz'], [/^(üveg|üveget)$/, 'üveg'], [/^(tábla|táblát)$/, 'tábla'],
    [/^(tekercs|tekercset)$/, 'tekercs'], [/^(karton|kartont)$/, 'karton'], [/^(tálca|tálcát)$/, 'tálca'],
    [/^(fej|fejet)$/, 'fej'], [/^(gerezd|gerezdet)$/, 'gerezd'], [/^(csokor|csokrot)$/, 'csokor'],
    [/^(szál|szálat)$/, 'szál'], [/^(rúd|rudat)$/, 'rúd'], [/^(pár|párat)$/, 'pár'], [/^(adag|adagot)$/, 'adag'],
  ];
  // Mondatkezdő töltelékszavak ("vegyél", "kell még", "írd fel" …)
  const FILLER_WORDS = 'kellene|kéne|kell|kérnék|kérek|kérem|vegyél|vegyünk|vegyetek|vennék|venni|hozzál|hozz|legyen|írd fel|írj fel|írjad|írd|tedd fel|tegyél|add hozzá|és|meg|még|is|plusz|aztán|valamint|majd|egy kis|kis|egy kevés|kevés';
  const FILLERS = new RegExp('^(' + FILLER_WORDS + ')(\\s+|$)', 'i');
  const FILLER_ONLY = new RegExp('^(' + FILLER_WORDS + ')$', 'i'); // egy darab önálló töltelékszó ("kell")
  // Tételhatárok: vessző, pontosvessző, új sor, és a kötőszavak ("és", "meg", "még" …).
  // (A \b nem jó ékezetes szavaknál, ezért szóközökkel határolunk.)
  const SPLIT = /\s*[,;:\n]+\s*|\s+(?:és|meg|valamint|aztán|plusz|majd|még|illetve)\s+/i;
  // Néhány szabálytalan tárgyeset, amit az ékezet- és ragleválasztás nem talál meg.
  const IRREGULAR = { cukrot: 'cukor', porcukrot: 'porcukor', kenyeret: 'kenyér', bokrot: 'bokor', csokrot: 'csokor', lisztet: 'liszt' };

  const deaccent = (s) => s.toLowerCase().replace(/[áéíóöőúüű]/g, (c) => ({ á: 'a', é: 'e', í: 'i', ó: 'o', ö: 'o', ő: 'o', ú: 'u', ü: 'u', ű: 'u' })[c]);
  const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);
  const fmtNum = (n) => String(n).replace('.', ',');

  function makeLexicon(words) {
    const map = new Map();
    for (const w of words || []) {
      const lw = w.toLowerCase();
      map.set(deaccent(lw), lw);
    }
    return map;
  }

  // Tárgyragos / ékezetvesztett alak → szótári alapforma, ha felismerjük ("almát" → "alma").
  function baseForm(word, lexicon) {
    const w = word.toLowerCase();
    if (IRREGULAR[w]) return IRREGULAR[w];
    const cands = [w];
    if (/t$/.test(w)) {
      cands.push(w.slice(0, -1));
      cands.push(w.slice(0, -1).replace(/á$/, 'a').replace(/é$/, 'e'));
    }
    for (const suf of ['at', 'et', 'ot', 'öt', 'ét', 'át']) if (w.endsWith(suf) && w.length > suf.length + 1) cands.push(w.slice(0, -suf.length));
    for (const c of cands) {
      const hit = lexicon.get(deaccent(c));
      if (hit) return hit;
    }
    return null;
  }

  // Vessző nélkül diktált felsorolás szétbontása ismert tételekre:
  // "kenyér tej vaj" → [kenyér, tej, vaj]. Csak akkor bont, ha MINDEN szó (vagy
  // szókapcsolat, pl. "darált hús") ismert szótári tétel — különben null.
  function segment(tokens, lexicon) {
    const out = [];
    let i = 0;
    while (i < tokens.length) {
      let found = null;
      for (let len = Math.min(3, tokens.length - i); len >= 1 && !found; len--) {
        const phrase = tokens.slice(i, i + len).map((t) => t.toLowerCase().replace(/[.!?]+$/, ''));
        const last = baseForm(phrase[len - 1], lexicon) || phrase[len - 1];
        const key = deaccent([...phrase.slice(0, -1), last].join(' '));
        if (lexicon.has(key)) found = [lexicon.get(key), len];
      }
      if (!found) return null;
      out.push(found[0]);
      i += found[1];
    }
    return out;
  }

  function parseChunk(chunk, lexicon) {
    let s = chunk.trim().replace(/^[-–•*]\s*/, '');
    let prev;
    do { prev = s; s = s.replace(FILLERS, ''); } while (s !== prev);
    if (!s || FILLER_ONLY.test(s)) return null;
    let tokens = s.split(/\s+/);
    let qty = '';

    const t0 = tokens[0].toLowerCase();
    let num = null;
    if (/^\d+([.,]\d+)?$/.test(t0)) num = parseFloat(t0.replace(',', '.'));
    else if (t0 in NUMBERS) num = NUMBERS[t0];
    else if (t0 in VAGUE && tokens.length > 1) { qty = VAGUE[t0]; tokens = tokens.slice(1); }

    if (num !== null && tokens.length > 1) {
      const unit = UNITS.find(([re]) => re.test(tokens[1].toLowerCase()));
      if (unit) {
        qty = fmtNum(num) + ' ' + unit[1];
        tokens = tokens.slice(2);
      } else {
        const article = num === 1 && /^egy(et)?$/.test(t0); // "egy kenyér" — névelő, nem mennyiség
        qty = article ? '' : (num === 0.5 ? 'fél' : num === 1.5 ? 'másfél' : fmtNum(num) + ' db');
        tokens = tokens.slice(1);
      }
    } else if (num !== null && tokens.length === 1) {
      return null; // csak egy szám, név nélkül
    }
    if (!tokens.length) return null;

    // több, vessző nélkül egymás után mondott ismert tétel? ("kenyér tej vaj")
    if (tokens.length >= 2) {
      const parts = segment(tokens, lexicon);
      if (parts && parts.length >= 2) return parts.map((n, i) => ({ name: cap(n), qty: i === 0 ? qty : '' }));
    }

    // az utolsó szó alapformája (tárgyeset: "almát" → "alma")
    const last = tokens[tokens.length - 1];
    const base = baseForm(last.replace(/[.!?]+$/, ''), lexicon);
    if (base) tokens[tokens.length - 1] = base;
    const name = tokens.join(' ').replace(/[.!?]+$/, '').trim();
    if (!name) return null;
    return { name: cap(name), qty };
  }

  function parseShopping(text, words) {
    const lexicon = makeLexicon(words);
    const out = [];
    for (const chunk of String(text || '').replace(/[!?]+/g, ',').replace(/\.(\s|$)/g, ',$1').split(SPLIT)) {
      const item = chunk && parseChunk(chunk, lexicon);
      if (Array.isArray(item)) out.push(...item);
      else if (item) out.push(item);
    }
    return out;
  }

  if (typeof module !== 'undefined' && module.exports) module.exports = { parseShopping, deaccent };
  else root.parseShopping = parseShopping;
})(typeof window !== 'undefined' ? window : this);
