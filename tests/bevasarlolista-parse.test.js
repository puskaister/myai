// A magyar bevásárlólista-értelmező tesztje (node tests/bevasarlolista-parse.test.js)
const { parseShopping } = require('../apps/bevasarlolista/assets/parse.js');
const dict = require('../apps/bevasarlolista/assets/dict.json');
const words = dict.categories.flatMap((c) => c.words);

const cases = [
  ['kenyér, tej és két kiló alma', [['Kenyér', ''], ['Tej', ''], ['Alma', '2 kg']]],
  ['vegyél két kiló almát, tejet meg fél kenyeret', [['Alma', '2 kg'], ['Tej', ''], ['Kenyér', 'fél']]],
  ['3 liter tej, 20 deka sajt és egy csomag spagetti', [['Tej', '3 l'], ['Sajt', '20 dkg'], ['Spagetti', '1 csomag']]],
  ['kell még tojás meg wc-papír', [['Tojás', ''], ['Wc-papír', '']]],
  ['hat tojást és másfél liter kólát', [['Tojás', '6 db'], ['Kóla', '1,5 l']]],
  ['egy kenyér', [['Kenyér', '']]],
  ['írd fel: vaj, cukrot, lisztet', [['Vaj', ''], ['Cukor', ''], ['Liszt', '']]],
  ['Paradicsom. Uborka. Paprika.', [['Paradicsom', ''], ['Uborka', ''], ['Paprika', '']]],
  ['darált húst és vizet', [['Darált hús', ''], ['Víz', '']]],
  ['2 doboz meggy', [['Meggy', '2 doboz']]],
  ['pár banánt', [['Banán', 'pár']]],
  ['fogkrém\nsampon\nmosogatószer', [['Fogkrém', ''], ['Sampon', ''], ['Mosogatószer', '']]],
  ['', []],
];

let fails = 0;
for (const [input, want] of cases) {
  const got = parseShopping(input, words).map((i) => [i.name, i.qty]);
  const ok = JSON.stringify(got) === JSON.stringify(want);
  if (!ok) fails++;
  console.log((ok ? '  ok   ' : '  HIBA ') + JSON.stringify(input) + (ok ? '' : '\n        várt: ' + JSON.stringify(want) + '\n        kapott: ' + JSON.stringify(got)));
  if (!ok && process.env.GITHUB_ACTIONS) console.log('::error title=Értelmező::' + input + ' → ' + JSON.stringify(got));
}
if (fails) { console.log(fails + ' eset HIBÁS'); process.exit(1); }
console.log('Értelmező: minden eset rendben.');
