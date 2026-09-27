#!/usr/bin/env bash
# Végponttól végpontig teszt a chatbothoz: valódi Anthropic PHP SDK, ál-API
# szerverrel (tests/mock-anthropic.php), valódi MySQL-lel.
# BASE: a chatbot címe; MOCK_LOG: az ál-API kérésnaplója.
set -u
BASE="${BASE:-http://127.0.0.1:8002}"
DB_PASS="${DB_PASS:?DB_PASS kell}"
MOCK_LOG="${MOCK_LOG:?MOCK_LOG kell}"
JAR="$(mktemp)"
FAILS=0
H=(-H 'X-Requested-With: fetch' -H 'Content-Type: application/json')

pass() { echo "  ok  $1"; }
fail() { echo "  HIBA $1"; echo "::error title=Chatbot teszt hiba::$1"; FAILS=$((FAILS + 1)); }
check() { if eval "$2"; then pass "$1"; else fail "$1"; fi; }
api_get()  { curl -s "$BASE/api/?r=$1" -b "$JAR" -c "$JAR"; }
api_post() { curl -s -w '\n%{http_code}' "$BASE/api/?r=$1" -b "$JAR" -c "$JAR" "${H[@]}" -d "$2"; }
body()   { sed '$d' <<<"$1"; }
status() { tail -n1 <<<"$1"; }
last_req() { tail -n1 "$MOCK_LOG"; }

echo "== Telepítés"
check "telepítés előtt átirányít" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/")" = 302 ]'
out=$(curl -s "$BASE/install.php" --data-urlencode business='Teszt Gumi' -d preset=gumiszerviz -d name=Admin \
  -d email=admin@example.com -d password=titkos123 -d password2=titkos123 --data-urlencode dbpass="$DB_PASS")
check "telepítés sikerül" 'grep -q "Kész!" <<<"$out"'

echo "== Oldalak"
check "chat oldal betölt" 'curl -s "$BASE/" | grep -q "window.BOOT"'
check "beágyazott változat" 'curl -s "$BASE/?embed=1" | grep -q "close-chat"'
check "widget JS" 'curl -s "$BASE/widget.php" | grep -q "myai-chat-btn"'
check "manifest" 'curl -s "$BASE/manifest.php" | jq -e ".name == \"Teszt Gumi\"" >/dev/null'
curl -s -o "$JAR.icon" "$BASE/icon.php?s=512&m=1"
check "ikon PNG" 'head -c 8 "$JAR.icon" | od -An -tx1 | grep -q "89 50 4e 47"'
check "admin oldal" 'curl -s "$BASE/admin/" | grep -q "admin.js"'

echo "== Beszélgetés (SDK + ál-API)"
cfg=$(api_get config)
check "config: 6 kötelező kérdés, tudásanyag nem szivárog ki" 'jq -e ".settings.questions_total == 6" <<<"$cfg" >/dev/null && ! grep -q "Nyitvatartás" <<<"$cfg"'
check "POST egyedi fejléc nélkül tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=start" -d "{}")" = 400 ]'
r=$(api_post start '{"source":"https://pelda.hu/kapcsolat"}')
TOKEN=$(body "$r" | jq -r .token)
check "beszélgetés indul" '[ "$(status "$r")" = 200 ] && [ ${#TOKEN} = 32 ]'

r=$(api_post message "{\"token\":\"$TOKEN\",\"text\":\"Szia! Mennyibe kerül nálatok?\"}")
check "sima kérdésre szöveges válasz" '[ "$(status "$r")" = 200 ] && jq -e ".reply | length > 0" <<<"$(body "$r")" >/dev/null'
req=$(last_req)
check "SDK: API kulcs a fejlécben" 'jq -e ".api_key == \"test-key\"" <<<"$req" >/dev/null'
check "SDK: claude-opus-5, low effort" 'jq -e ".body.model == \"claude-opus-5\" and .body.output_config.effort == \"low\"" <<<"$req" >/dev/null'
check "SDK: refusal fallback (default + beta fejléc)" 'jq -e ".body.fallbacks == \"default\"" <<<"$req" >/dev/null && jq -r .beta <<<"$req" | grep -q "server-side-fallback-2026-07-01"'
check "SDK: cache-elt rendszerprompt + állapot blokk" 'jq -e ".body.system[0].cache_control.type == \"ephemeral\" and (.body.system[1].text | contains(\"Still missing\"))" <<<"$req" >/dev/null'
check "SDK: tudásanyag a promptban" 'jq -e ".body.system[0].text | contains(\"Nyitvatartás\")" <<<"$req" >/dev/null'
check "SDK: record_answer tool, strict, kulcs-enum" 'jq -e ".body.tools[0].name == \"record_answer\" and .body.tools[0].strict == true and (.body.tools[0].input_schema.properties.question_key.enum | index(\"tyre\"))" <<<"$req" >/dev/null'

r=$(api_post message "{\"token\":\"$TOKEN\",\"text\":\"Gumicserét szeretnék egy Opel Astra autóra, 205/55 R16, holnap jó lenne.\"}")
check "több válasz egy üzenetben → 4/6 rögzítve" 'jq -e ".progress.answered == 4 and .progress.total == 6 and .complete == false" <<<"$(body "$r")" >/dev/null'
check "a tool eredmények visszamentek (tool_result + tool_use_id)" 'last_req | jq -e "[.body.messages[-1].content[] | select(.type == \"tool_result\" and .tool_use_id == \"toolu_mock_car\")] | length == 1" >/dev/null'
check "a bot szöveges válasza a tool kör után" 'jq -e ".reply | contains(\"feljegyeztem (4 adat)\")" <<<"$(body "$r")" >/dev/null'

r=$(api_post message "{\"token\":\"$TOKEN\",\"text\":\"Kiss Péter vagyok, +36 30 123 4567\"}")
check "minden kötelező adat megvan → complete" 'jq -e ".progress.answered == 6 and .complete == true" <<<"$(body "$r")" >/dev/null'
conv=$(api_get "conversation&token=$TOKEN")
check "folytatás: 6 üzenet visszatölthető" 'jq -e "(.messages | length) == 6 and .status == \"complete\"" <<<"$conv" >/dev/null'

r=$(api_post message "{\"token\":\"$TOKEN\",\"text\":\"HIBA529\"}")
check "túlterhelt API → barátságos 503" '[ "$(status "$r")" = 503 ] && jq -e ".error | contains(\"sokan írnak\")" <<<"$(body "$r")" >/dev/null'
r=$(api_post message '{"token":"00000000000000000000000000000000","text":"x"}')
check "ismeretlen beszélgetés 404" '[ "$(status "$r")" = 404 ]'

echo "== Admin"
check "belépés nélkül tiltott" '[ "$(curl -s -o /dev/null -w "%{http_code}" "$BASE/api/?r=admin/conversations")" = 401 ]'
r=$(api_post login '{"email":"admin@example.com","password":"titkos123"}')
check "belépés" '[ "$(status "$r")" = 200 ]'
list=$(api_get admin/conversations)
check "admin: 1 teljes beszélgetés" 'jq -e "(.conversations | length) == 1 and .conversations[0].status == \"complete\"" <<<"$list" >/dev/null'
CID=$(jq '.conversations[0].id' <<<"$list")
check "admin: válaszok és átirat" 'api_get "admin/conversation&id=$CID" | jq -e ".conversation.answers.car == \"Opel Astra\" and .conversation.answers.tyre == \"205/55 R16\" and (.messages | length) >= 6" >/dev/null'
check "admin: CSV export" 'api_get admin/export | grep -q "Opel Astra"'
check "admin: költségbecslés" 'api_get admin/usage | jq -e ".replies >= 3 and .usd > 0" >/dev/null'

r=$(api_post admin/settings '{"group":"questions","value":[{"key":"car","question":"Milyen autó?","hint":"","required":true},{"question":"Új kérdés kulcs nélkül","required":false}]}')
check "admin: kérdések mentése (kulcs generálás)" '[ "$(status "$r")" = 200 ] && api_get admin/settings | jq -e "(.settings.questions | length) == 2 and (.settings.questions[1].key | length) > 0" >/dev/null'
r=$(api_post admin/settings '{"group":"ai","value":{"model":"claude-haiku-4-5","max_user_messages":30,"daily_limit":300}}')
check "admin: modellváltás Haiku-ra" '[ "$(status "$r")" = 200 ]'
r=$(api_post start '{}'); T2=$(body "$r" | jq -r .token)
api_post message "{\"token\":\"$T2\",\"text\":\"Szia\"}" >/dev/null
check "Haiku: nincs effort és fallback a kérésben" 'last_req | jq -e ".body.model == \"claude-haiku-4-5\" and .body.output_config == null and .body.fallbacks == null" >/dev/null'
r=$(api_post admin/settings '{"group":"ai","value":{"model":"gpt-4","max_user_messages":30,"daily_limit":300}}')
check "admin: ismeretlen modell elutasítva" '[ "$(status "$r")" = 400 ]'

echo
if [ "$FAILS" -gt 0 ]; then echo "$FAILS teszt HIBÁS"; exit 1; fi
echo "Chatbot: minden teszt rendben."
