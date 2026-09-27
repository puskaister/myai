# Tanítható chatbot (Claude)

AI chatbot vállalkozásoknak. Válaszol a cég tudásanyagából, és a „betanított” fő kérdéseket minden beszélgetésben felteszi. A válaszokat külön rögzíti, és amikor minden kötelező adat megvan, összefoglaló emailt küld a cégnek.

- **Látogatóknak:** önálló chat oldal (telefonon appként telepíthető), és egy sorral beágyazható widget bármely weboldalra (`widget.php`).
- **Admin (`/admin/`):**
  - beszélgetések a válaszokkal és az átirattal, CSV export, becsült havi AI-költség
  - betanítás: fő kérdések, tudásanyag, hangnem, zárómondat
  - megjelenés, beágyazási kód, modellválasztás és költségkeretek, jelszó-emlékeztető

## Hogyan működik

- **SDK:** a hivatalos Anthropic PHP SDK-t (`anthropic-ai/sdk`) a deploy Composerrel telepíti a `vendor/` mappába. A tárhelyen nem kell Composer, de **PHP 8.1+** igen.
- **Modell:** alapértelmezés a `claude-opus-5`, alacsony `effort` beállítással (chat: gyors, tömör). Opus 5-nél be van kapcsolva a szerveroldali refusal fallback (`fallbacks: "default"`). Az adminban Sonnet 5-re vagy Haiku 4.5-re lehet váltani.
- **Rendszerprompt:** két részből áll. Az állandó rész (tudásanyag, kérdések, szabályok) cache-elt. A változó rész azt mondja meg, mely kérdésekre van már válasz.
- **Válaszok rögzítése:** a bot a `record_answer` toollal rögzít (`strict`, a kérdéskulcsok enumként). A szerver ellenőrzi a kulcsot, és csak ezután menti a választ.
- **Költségvédelem:**
  - legfeljebb 30 látogatói üzenet beszélgetésenként
  - legfeljebb 300 új beszélgetés naponta
  - IP-alapú korlát: 20 beszélgetés és 60 üzenet óránként
  - mindkét keret az adminban állítható

## Telepítés a my-ai.hu-n

1. Add meg a `ANTHROPIC_API_KEY` GitHub secretet (console.anthropic.com → API Keys).
2. Vegyél fel egy sort a `deploy/chatbot.json`-ba, pl. `{ "dir": "chat", "prefix": "chat_" }`. Az előtag az időpontfoglaló előtagjaitól is különbözzön.
3. Push, majd nyisd meg a `https://my-ai.hu/<dir>/install.php` oldalt.

## Tesztek

A `tests/chatbot.sh` a valódi SDK-val fut, egy ál-Anthropic szerver ellen (`tests/mock-anthropic.php`). Ellenőrzi az elküldött kérést (modell, cache, tool, fallback), a tool loopot, a válaszok rögzítését, a hibakezelést és az admin funkciókat. API kulcs és költség nem kell hozzá.
