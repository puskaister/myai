// A my-ai.hu chatbot tudástárának tesztje (node tests/ugyintezes-kb.test.js):
// tipikus látogatói kérdés → melyik téma válaszol. Az egyeztetés ugyanaz, mint a
// chat.html-ben és az admin teach.js-ében.
const cfg = require('../apps/ugyintezes/ugyfelek/my-ai.json');
const norm = (s) => String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
function best(q) {
  const nq = ' ' + norm(q) + ' ';
  const r = cfg.temak.map((t) => {
    let s = 0;
    (t.keys || []).forEach((k) => { const nk = norm(k); if (nk && nq.indexOf(nk) > -1) s += nk.indexOf(' ') > -1 ? 3 : 2; });
    if (nq.indexOf(norm(t.title)) > -1) s += 3;
    if (s > 0) s += Number(t.elsobbseg) || 0;
    return { id: t.id, s };
  }).filter((x) => x.s > 0).sort((a, b) => b.s - a.s);
  return r[0] ? r[0].id : null;
}
const cases = [
  ['Mennyibe kerül az időpontfoglaló?', 'ar'],
  ['mennyibe kerul a chatbot', 'ar'],
  ['mi az ára egy weboldalnak?', 'ar'],
  ['kell app store?', 'appstore'],
  ['Hogyan tehetem ki a telefonom kezdőképernyőjére?', 'telefonra'],
  ['mennyi idő alatt készül el egy app?', 'hatarido'],
  ['Szia!', 'szia'],
  ['köszönöm', 'koszon'],
  ['Hogyan rendelhetem meg a chatbotot?', 'megrendeles'],
  ['hogyan tudom lemondani az előfizetést?', 'lejarat'],
  ['kik vagytok, mi a cégnév?', 'ceg'],
  ['mi a telefonszámotok?', 'kapcsolat'],
  ['tároljátok az adataimat?', 'adatvedelem'],
  ['használtok sütiket?', 'sutik'],
  ['ingyenes a bevásárlólista?', 'bevasarlolista'],
  ['nem működik a mikrofon', 'hang'],
  ['van születésnap emlékeztető?', 'evfordulok'],
  ['tudtok webshopot csinálni?', 'webshop'],
  ['excelt szeretnék kiváltani', 'belso'],
  ['van demó?', 'demo'],
  ['mesterséges intelligencia van benne?', 'ai'],
  ['hogyan fizetek, kapok számlát?', 'fizetes'],
  ['saját domainen is mehet?', 'domain'],
  ['gumiszerviznek kell foglaló', 'idopontfoglalo'],
  ['mi az ászf?', 'aszf'],
  ['statisztikát szeretnék a weboldalamra', 'latogatoszamlalo'],
  ['milyen appjaitok vannak?', 'kesz'],
  ['Mikor vagytok nyitva?', 'nyitvatartas'],
  ['hány órától hívhatlak?', 'nyitvatartas'],
  ['hogyan tudom tanítani a chatbotomat?', 'tanitas'],
  ['elfelejtettem a jelszavam', 'tanitas'],
  ['meg lehet venni az időpontfoglalót a saját szerveremre?', 'onallo'],
  ['be tudom építeni a wordpress oldalamba a foglalót?', 'onallo'],
  ['hol töltöm le a letöltőkóddal?', 'onallo'],
  ['mennyibe kerül az önálló változat?', 'onallo'],
];
let fails = 0;
for (const [q, want] of cases) {
  const got = best(q);
  if (got === want) console.log('  ok  ' + q + ' → ' + got);
  else { fails++; console.log('  HIBA ' + q + ' → ' + got + ' (várt: ' + want + ')'); if (process.env.GITHUB_ACTIONS) console.log('::error title=Chatbot tudástár::„' + q + '” → ' + got + ' (várt: ' + want + ')'); }
}
const ids = cfg.temak.map((t) => t.id);
if (new Set(ids).size !== ids.length) { fails++; console.log('  HIBA ismétlődő téma-azonosító'); }
if (fails) { console.log(fails + ' hiba'); process.exit(1); }
console.log('Chatbot tudástár: ' + cases.length + ' kérdés rendben.');
