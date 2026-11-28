<?php
// Teendőlista e-mailben: POST JSON {email, company, consent, newsletter, answers, domain, website}
// → a pontszámot és a teendőket a szerver számolja újra (a levélbe csak a saját szövegeink
// kerülnek, így az űrlap nem használható tetszőleges tartalmú levelek küldésére), elmenti az
// érdeklődőt (kiber_leads), elküldi a levelet, és értesíti az info@my-ai.hu-t.
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Budapest');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

require dirname(__DIR__, 2) . '/_inc/kiber-lib.php';

function out(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new KiberError('Csak POST kérés.', 405);
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) throw new KiberError('Hibás kérés.');

    // Robotcsapda: a látogató nem látja ezt a mezőt.
    if (trim((string) ($in['website'] ?? '')) !== '') out(200, ['ok' => true]);

    $email = strtolower(trim((string) ($in['email'] ?? '')));
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@[^@]+\.[a-z]{2,}$/i', $email)) {
        throw new KiberError('Adj meg egy érvényes e-mail-címet.');
    }
    if (($in['consent'] ?? false) !== true) throw new KiberError('A teendőlista küldéséhez el kell fogadnod az adatkezelési tájékoztatót.');
    $company = trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) ($in['company'] ?? '')));
    $company = function_exists('mb_substr') ? mb_substr($company, 0, 120) : substr($company, 0, 120);
    $newsletter = ($in['newsletter'] ?? false) === true;

    $answers = kiber_clean_answers($in['answers'] ?? []);
    $domainName = trim((string) ($in['domain'] ?? '')) !== '' ? dc_normalize_domain((string) $in['domain']) : null;
    if (!$answers && $domainName === null) throw new KiberError('Előbb válaszolj legalább egy kérdésre, vagy vizsgáld meg a domaint.');

    if (!kiber_rate_limit('report', 5, 3600)) throw new KiberError('Túl sok kérés rövid idő alatt. Próbáld újra egy óra múlva.', 429);
    $recent = kiber_all("SELECT COUNT(*) AS n FROM {leads} WHERE email = ? AND created_at > NOW() - INTERVAL 1 DAY", [$email]);
    if ((int) ($recent[0]['n'] ?? 0) >= 3) throw new KiberError('Erre a címre ma már többször küldtünk teendőlistát. Holnap újra kérheted.', 429);

    set_time_limit(60);
    $domain = $domainName !== null ? dc_run_cached($domainName) : null;
    $ev = kiber_evaluate($answers, $domain);
    $nowCount = count(array_filter($ev['todos'], fn ($t) => $t['prio'] === 'now'));

    $token = bin2hex(random_bytes(16));
    kiber_q("INSERT INTO {leads} (created_at, email, company, domain, score, q_score, d_score, answered, todos, todos_now, answers, newsletter, token, ip_hash)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
        $email, $company, $domainName ?? '', $ev['score'], $ev['q_score'], $ev['d_score'], $ev['answered'], count($ev['todos']), $nowCount,
        json_encode($answers), $newsletter ? 1 : 0, $token, kiber_ip_hash(),
    ])->close();
    $id = (int) kiber_db()->insert_id;

    $base = kiber_site_url() . '/kiberbiztonsag/hirlevel/';
    $body = kiber_report_text($ev, $domain, $company, $base . '?a=leiratkozas&t=' . $token, $newsletter ? $base . '?a=megerosites&t=' . $token : null);
    $subject = 'Kiberbiztonsági teendőlistád' . ($ev['score'] !== null ? ' – ' . $ev['score'] . '/100' : '');
    $notify = (string) (kiber_config()['notify'] ?? 'info@my-ai.hu');
    $ok = kiber_mail($email, $subject, $body, $notify);
    kiber_q("UPDATE {leads} SET mail_ok = ? WHERE id = ?", [$ok ? 1 : 0, $id])->close();

    // Értesítés nekünk az új érdeklődőről (válasz gombbal közvetlenül neki lehet írni).
    if ($notify !== '') {
        kiber_mail($notify, 'Új gyorsellenőrzés: ' . ($company !== '' ? $company : $email) . ' – ' . ($ev['score'] ?? '–') . '/100',
            "Új teendőlista-kérés a gyorsellenőrzésen.\n\n"
            . "E-mail: $email\nCég: " . ($company !== '' ? $company : '–') . "\nDomain: " . ($domainName ?? '–') . "\n"
            . 'Pontszám: ' . ($ev['score'] ?? '–') . '/100 (kérdőív: ' . ($ev['q_score'] ?? '–') . ', domain: ' . ($ev['d_score'] ?? '–') . ")\n"
            . 'Teendők: ' . count($ev['todos']) . " (ebből azonnali: $nowCount)\n"
            . 'Hírlevél: ' . ($newsletter ? 'kérte (megerősítésre vár)' : 'nem kérte') . "\n"
            . 'Levél kiküldve: ' . ($ok ? 'igen' : 'NEM – a levelezés hibát adott') . "\n\n"
            . 'Az összes érdeklődő: ' . kiber_site_url() . "/kiberbiztonsag/admin/\n", $email);
    }

    if (!$ok) throw new KiberError('A levelet most nem sikerült elküldeni. Próbáld újra pár perc múlva, vagy írj nekünk: ' . SITE_PHONE_EMAIL['email'], 502);
    out(200, ['ok' => true, 'email' => $email, 'todos' => count($ev['todos'])]);
} catch (KiberError $e) {
    out($e->status, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[kiberbiztonsag] report: ' . $e->getMessage());
    out(500, ['error' => 'Váratlan hiba történt. Próbáld újra később.']);
}
