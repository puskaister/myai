// Böngészős felületi teszt: valódi Chrome-ban végigkattintja egy app fő
// folyamatait (belépéssel együtt), és minden lépés után ellenőrzi, hogy
//   – a látható szövegben nincs "null", "undefined", "NaN" vagy "[object …]",
//   – nem volt JavaScript-hiba.
// Használat: BASE=http://127.0.0.1:8000 node ui-test.js <app>
// app: idopontfoglalo | latogatok | evfordulok | bevasarlolista | chatbot | ugyintezes
'use strict';
const puppeteer = require('puppeteer-core');

const app = process.argv[2];
const BASE = process.env.BASE;
const CHROME = process.env.CHROME || '/usr/bin/google-chrome';
const PASS = process.env.PASS || 'titkos123';
const EMAIL = process.env.EMAIL || 'admin@example.com';
// Önálló szóként (ékezetes betű is betűnek számít: a „nullázva” nem hiba).
const BAD = /\[object [A-Za-z]+\]|(?<![\p{L}\p{N}_])(?:null|undefined|NaN)(?![\p{L}\p{N}_])/u;

let fails = 0;
const jsErrors = [];
const ok = (m) => console.log('  ok  ' + m);
const bad = (m) => { fails++; console.log('  HIBA ' + m); if (process.env.GITHUB_ACTIONS) console.log('::error title=Felületi teszt (' + app + ')::' + m.replace(/\n/g, ' ')); };

async function textOk(page, label) {
  const t = await page.evaluate(() => document.body.innerText);
  const m = t.match(BAD);
  if (m) {
    const i = Math.max(0, m.index - 60);
    bad(label + ': „' + m[0] + '” a látható szövegben … ' + t.slice(i, m.index + 40).replace(/\s+/g, ' '));
  } else ok(label);
}
const waitText = (page, text, timeout = 10000) =>
  page.waitForFunction((t) => document.body.innerText.includes(t), { timeout }, text);
const clickText = (page, selector, text) => page.evaluate((sel, t) => {
  const el = [...document.querySelectorAll(sel)].find((e) => e.textContent.trim().includes(t));
  if (!el) throw new Error('nincs ilyen elem: ' + sel + ' „' + t + '”');
  el.click();
}, selector, text);
const settle = (ms = 400) => new Promise((r) => setTimeout(r, ms));

async function login(page, path, email = EMAIL, pass = PASS) {
  await page.goto(BASE + path, { waitUntil: 'networkidle0' });
  await page.waitForSelector('input[name=email]');
  await page.type('input[name=email]', email);
  await page.type('input[name=password]', pass);
  await page.click('form button[type=submit]');
}

const flows = {
  async idopontfoglalo(page) {
    await page.goto(BASE + '/', { waitUntil: 'networkidle0' });
    await waitText(page, 'Válassz szolgáltatást');
    await textOk(page, 'foglalás 1/4: szolgáltatások');
    await page.click('.choice');
    await page.waitForSelector('.day:not([disabled])', { timeout: 15000 });
    await textOk(page, 'foglalás 2/4: naptár');
    await page.click('.day:not([disabled])');
    await page.waitForSelector('.slot');
    await textOk(page, 'foglalás 2/4: időpontok');
    await page.click('.slot');
    await page.waitForSelector('input[name=name]');
    await textOk(page, 'foglalás 3/4: űrlap');
    await page.type('input[name=name]', 'Felületi Teszt');
    await page.type('input[name=phone]', '+36 30 111 2222');
    await page.type('input[name=email]', 'ui@example.com');
    for (const f of await page.$$('input[name^="fields."]')) await f.type('UI-001');
    await page.click('form button[type=submit]');
    await page.waitForFunction(() => /Sikeres foglalás|Foglalási kérés elküldve/.test(document.body.innerText), { timeout: 10000 });
    await textOk(page, 'foglalás 4/4: visszaigazolás');

    // Telepítési útmutató: a felismert böngészőhöz illő lépések.
    const UAS = [
      ['iPhone Safari', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 'Főképernyőhöz adás', '.ig-arrow.bottom'],
      ['iPhone Chrome', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/138.0.7204.156 Mobile/15E148 Safari/604.1', 'címsor jobb oldalán', '.ig-arrow.top'],
      ['iPhone Facebook', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/520.0.0.38.101;FBBV/1;FBDV/iPhone15,2;FBMD/iPhone;FBSN/iOS;FBSV/18.5;FBSS/3;FBLC/hu_HU]', 'Megnyitás a Safariban', null],
      ['Android Facebook', 'Mozilla/5.0 (Linux; Android 14; SM-S911B Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/138.0.0.0 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/520.0.0.38.101;]', 'Megnyitás böngészőben', null],
    ];
    for (const [label, ua, expect, arrow] of UAS) {
      const p = await page.browser().newPage();
      p.on('pageerror', (e) => jsErrors.push('JS hiba (' + label + '): ' + e.message));
      await p.setUserAgent(ua);
      await p.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
      await p.goto(BASE + '/', { waitUntil: 'networkidle0' });
      await p.waitForSelector('#install-btn:not([hidden])', { timeout: 5000 });
      await p.click('#install-btn');
      await p.waitForSelector('.ig-sheet', { timeout: 5000 });
      const t = await p.evaluate(() => document.querySelector('.ig-sheet').innerText);
      if (!t.includes(expect)) bad('telepítési útmutató (' + label + '): nincs benne „' + expect + '”: ' + t.replace(/\s+/g, ' '));
      else if (arrow && !(await p.$(arrow))) bad('telepítési útmutató (' + label + '): hiányzik a nyíl ' + arrow);
      else await textOk(p, 'telepítési útmutató: ' + label);
      await p.close();
    }

    await login(page, '/admin/');
    await page.waitForSelector('.tabs');
    await settle(800);
    await textOk(page, 'admin: foglalások');
    const pending = await page.$('.card .item');
    if (pending) {
      await pending.click();
      await page.waitForSelector('.modal');
      await textOk(page, 'admin: foglalás részletei');
      await page.keyboard.press('Escape');
    }
    for (const tab of ['Szolgáltatások', 'Nyitvatartás', 'Beállítások', 'Fiók']) {
      await clickText(page, '.tabs button', tab);
      await settle();
      await textOk(page, 'admin: ' + tab);
    }
  },

  async latogatok(page) {
    await login(page, '/');
    await page.waitForSelector('.tiles', { timeout: 10000 });
    await settle(600);
    await textOk(page, 'irányítópult: áttekintés');
    for (const d of ['Ma', '30 nap']) { await clickText(page, '.range button', d); await settle(600); await textOk(page, 'áttekintés: ' + d); }
    for (const tab of ['Látogatások', 'Beállítások', 'Fiók']) {
      await clickText(page, '.tabs button', tab);
      await settle(600);
      await textOk(page, 'irányítópult: ' + tab);
    }
  },

  async evfordulok(page) {
    await login(page, '/', 'anna@example.com');
    await page.waitForSelector('.tabs', { timeout: 10000 });
    await settle(600);
    await textOk(page, 'közelgő alkalmak');
    await page.click('.fab');
    await page.waitForSelector('.modal input[name=title]');
    await textOk(page, 'új alkalom űrlap');
    await page.type('.modal input[name=title]', 'Felületi teszt születésnap');
    await page.click('.modal button[type=submit]');
    await waitText(page, 'Felületi teszt születésnap');
    await settle(400);
    await textOk(page, 'alkalom a listán');
    await clickText(page, '.tabs button', 'Beállítások');
    await waitText(page, 'Naptár-feliratkozás');
    await settle(800);
    await textOk(page, 'beállítások (admin résszel)');
  },

  async bevasarlolista(page) {
    await login(page, '/', 'anna@example.com');
    await page.waitForSelector('#add-input', { timeout: 10000 });
    await settle(600);
    await textOk(page, 'lista');
    await page.type('#add-input', 'kenyér, tej meg két kiló alma');
    await page.keyboard.press('Enter');
    await waitText(page, '2 kg');
    await settle(400);
    const names = await page.$$eval('.item-row .n', (els) => els.map((e) => e.textContent));
    for (const n of ['Kenyér', 'Tej', 'Alma']) if (!names.includes(n)) bad('hiányzó tétel a listán: ' + n + ' (' + names.join(', ') + ')');
    await textOk(page, 'tételek kategóriák szerint');
    await page.click('.item-row .tick');
    await waitText(page, 'Kosárban');
    await textOk(page, 'kipipálás');
    await page.click('#menu-btn');
    await page.waitForSelector('.modal .sheet-btn');
    await textOk(page, 'menü');
    await page.keyboard.press('Escape');
    await clickText(page, '.list-bar button', '');
    await waitText(page, 'join=');
    await textOk(page, 'megosztás');
    await page.keyboard.press('Escape');
  },

  async chatbot(page) {
    await login(page, '/admin/');
    await page.waitForSelector('.tabs', { timeout: 10000 });
    await settle(800);
    await textOk(page, 'admin: beszélgetések');
    for (const tab of ['Betanítás', 'Megjelenés és beágyazás', 'Beállítások', 'Fiók']) {
      await clickText(page, '.tabs button', tab);
      await settle(500);
      await textOk(page, 'admin: ' + tab);
    }
    await page.goto(BASE + '/', { waitUntil: 'networkidle0' });
    await page.waitForSelector('#text');
    await page.type('#text', 'Szia, gumicserét szeretnék');
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelectorAll('.msg.bot').length >= 2, { timeout: 15000 });
    await textOk(page, 'chat: válasz megjelent');
  },
  async ugyintezes(page) {
    await login(page, '/admin/');
    await page.waitForSelector('.tabs', { timeout: 10000 });
    await waitText(page, 'aktív előfizetés');
    await settle(600);
    await textOk(page, 'admin: ügyfelek');
    await page.click('.list .item');
    await page.waitForSelector('.modal textarea[name=kb_json]');
    await settle(400);
    await textOk(page, 'admin: ügyfél adatlap (befizetés, beépítő kód)');
    await page.keyboard.press('Escape');
    await page.evaluate(() => document.querySelector('.modal-back')?.remove());
    await clickText(page, '.tabs button', 'Tanítás');
    await page.waitForSelector('#kb-test');
    await page.type('#kb-test', 'mennyibe kerül az időpontfoglaló?');
    await waitText(page, 'Téma szerkesztése');
    await textOk(page, 'tanítás: kipróbálás');
    await clickText(page, 'button', 'Téma szerkesztése');
    await page.waitForSelector('.topic.open textarea');
    await textOk(page, 'tanítás: téma szerkesztése');
    await page.click('#kb-test', { clickCount: 3 });
    await page.type('#kb-test', 'zsiráf');
    await waitText(page, 'Új téma ebből a kérdésből');
    for (const tab of ['Letöltőkódok', 'Beállítások', 'Fiók']) {
      await clickText(page, '.tabs button', tab);
      await settle(500);
      await textOk(page, 'admin: ' + tab);
    }
    // ügyfél-fiók: a tests/ugyintezes.sh által beállított jelszóval
    await login(page, '/fiok/', 'anna@kovacs.hu', 'ugyfel123');
    await page.waitForSelector('#kb-test', { timeout: 10000 });
    await settle(500);
    await textOk(page, 'ügyfél-fiók: tanítás');
    await page.type('#kb-test', 'mikor vagytok nyitva?'); // a tests/ugyintezes.sh végén mentett tudástár
    await waitText(page, 'H–P 8–16');
    await textOk(page, 'ügyfél-fiók: kipróbálás');
    await page.goto(BASE + '/chat.html?u=my-ai', { waitUntil: 'networkidle0' });
    await page.waitForSelector('#q');
    await page.type('#q', 'mennyibe kerül a chatbot?');
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelectorAll('.msg.bot').length >= 2, { timeout: 10000 });
    await textOk(page, 'chat: válasz a tudásbázisból');
  },
};

(async () => {
  if (!flows[app]) { console.error('Ismeretlen app: ' + app); process.exit(2); }
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox', '--disable-gpu', '--lang=hu-HU'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  page.on('pageerror', (e) => jsErrors.push('JS hiba: ' + e.message));
  page.on('console', (m) => {
    if (m.type() !== 'error') return;
    const t = m.text();
    if (/Failed to load resource|favicon|401|404|409|422|429/.test(t)) return; // várt HTTP válaszok (pl. hibás belépés), nem JS-hiba
    jsErrors.push('konzol: ' + t);
  });
  page.on('dialog', (d) => d.accept()); // confirm() ablakok
  console.log('== Felületi teszt: ' + app);
  try {
    await flows[app](page);
  } catch (e) {
    bad('a folyamat megszakadt: ' + e.message);
    try { console.log((await page.evaluate(() => document.body.innerText)).slice(0, 1500)); } catch (x) { /* nincs oldal */ }
  }
  for (const e of jsErrors) bad(e);
  await browser.close();
  if (fails) { console.log(fails + ' hiba'); process.exit(1); }
  console.log('Felületi teszt rendben: ' + app);
})();
