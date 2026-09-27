<?php
// Ál-Anthropic API a CI-hoz (php -S router). A Messages API válaszformátumát
// utánozza, így a chatbot a valódi SDK-n keresztül, API kulcs és költség
// nélkül tesztelhető. Minden kérést a MOCK_LOG fájlba naplóz.
declare(strict_types=1);

$log = getenv('MOCK_LOG') ?: sys_get_temp_dir() . '/mock-anthropic.jsonl';
$raw = (string) file_get_contents('php://input');
$req = json_decode($raw, true) ?: [];
$headers = function_exists('getallheaders') ? array_change_key_case(getallheaders()) : [];

file_put_contents($log, json_encode([
    'path'    => $_SERVER['REQUEST_URI'] ?? '',
    'beta'    => $headers['anthropic-beta'] ?? '',
    'api_key' => $headers['x-api-key'] ?? '',
    'body'    => $req,
], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

header('Content-Type: application/json');
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/v1/messages') === false) {
    http_response_code(404);
    echo '{"type":"error","error":{"type":"not_found_error","message":"mock: unknown path"}}';
    exit;
}

$messages = $req['messages'] ?? [];
$last = end($messages) ?: [];
$lastContent = $last['content'] ?? '';

function reply(array $content, string $stop): void {
    echo json_encode([
        'id' => 'msg_mock_' . bin2hex(random_bytes(4)),
        'type' => 'message',
        'role' => 'assistant',
        'model' => $GLOBALS['req']['model'] ?? 'claude-opus-5',
        'content' => $content,
        'stop_reason' => $stop,
        'stop_sequence' => null,
        'usage' => ['input_tokens' => 120, 'output_tokens' => 40, 'cache_read_input_tokens' => 900, 'cache_creation_input_tokens' => 0],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tool eredmények érkeztek → zárjuk a kört egy szöveges válasszal.
if (is_array($lastContent)) {
    $saved = count(array_filter($lastContent, fn ($b) => ($b['type'] ?? '') === 'tool_result'));
    reply([['type' => 'text', 'text' => "Köszönöm, feljegyeztem ($saved adat)."]], 'end_turn');
}

$text = (string) $lastContent;
if (strpos($text, 'HIBA529') !== false) {
    http_response_code(529);
    echo '{"type":"error","error":{"type":"overloaded_error","message":"Overloaded (mock)"}}';
    exit;
}

// A felismert "válaszokból" record_answer hívások — csak a kérésben szereplő kulcsokra.
$enum = $req['tools'][0]['input_schema']['properties']['question_key']['enum'] ?? [];
$rules = [
    'service' => ['/gumicser/iu', 'gumicsere'],
    'car'     => ['/opel astra/iu', 'Opel Astra'],
    'tyre'    => ['/\d{3}\/\d{2} ?R\d{2}/u', null],
    'when'    => ['/holnap/iu', 'holnap délelőtt'],
    'name'    => ['/kiss péter/iu', 'Kiss Péter'],
    'phone'   => ['/\+36[\d ]{9,}/u', null],
];
$calls = [];
foreach ($rules as $key => [$re, $value]) {
    if (!in_array($key, $enum, true) || !preg_match($re, $text, $m)) continue;
    $calls[] = ['type' => 'tool_use', 'id' => 'toolu_mock_' . $key, 'name' => 'record_answer',
        'input' => ['question_key' => $key, 'answer' => $value ?? trim($m[0])]];
}
if ($calls) reply(array_merge([['type' => 'text', 'text' => 'Rögzítem.']], $calls), 'tool_use');

reply([['type' => 'text', 'text' => 'Szívesen segítek! Milyen autóról van szó?']], 'end_turn');
