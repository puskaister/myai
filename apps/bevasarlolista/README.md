# Bevásárlólista

Telefonra telepíthető közös bevásárlólista (PWA), magyar szóbeli bevitellel.

- **Szóbeli bevitel:** a mikrofongombot megnyomva elmondható, pl. „kenyér, tej meg két kiló alma”. A böngésző beépített hangfelismerését használja (Web Speech API, `hu-HU`), ez Chrome-ban és Safariban működik. Ahol nem elérhető, a billentyűzet diktálás-gombja működik, a szöveget ugyanaz az értelmező bontja tételekre.
- **Magyar értelmező** (`assets/parse.js`): tételhatárok (vessző, „és”, „meg”, „még”…), mennyiségek („két kiló” → 2 kg, „fél”, „másfél liter”, „20 deka”, „egy csomag”), töltelékszavak („vegyél”, „kell még”, „írd fel:”), tárgyragos alakok („almát” → Alma, „cukrot” → Cukor). Tesztje: `tests/bevasarlolista-parse.test.js`.
- **Kategóriák bolti sorrendben** (`assets/dict.json`): a szerver ez alapján sorolja be a tételeket, a böngésző pedig a szótári alapformákhoz használja. Bővíthető.
- **Közös lista:** meghívó linkkel lehet csatlakozni. A kliensek négy másodpercenként lekérdezik a lista verziószámát, és csak változáskor töltik le újra.
- **Offline:** az utolsó ismert lista megmarad, a módosítások sorba kerülnek, és a kapcsolat visszatérésekor elmennek.
- **Egyéb:** gyakran vett tételek egy koppintással, duplikáció-szűrés (ugyanazt a tételt csak a mennyiségét frissítve veszi fel), „Kosárban” rész, visszavonás.

Új telepítés: vegyél fel egy sort a `deploy/bevasarlolista.json`-ba, pushold, majd nyisd meg a `/<dir>/install.php` oldalt.
