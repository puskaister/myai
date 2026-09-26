<?php
declare(strict_types=1);

// Email értesítések a foglalás életciklusára (ügyfélnek és a cégnek).

const HU_MONTHS = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
const HU_DAYS = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];

function hu_date(int $ts): string {
    return date('Y', $ts) . '. ' . HU_MONTHS[(int) date('n', $ts) - 1] . ' ' . date('j', $ts) . '., ' . HU_DAYS[(int) date('w', $ts)];
}

function booking_when(array $b): string {
    $start = strtotime((string) $b['start_at']);
    $end = strtotime((string) $b['end_at']);
    return hu_date($start) . ' ' . date('H:i', $start) . '–' . date('H:i', $end);
}

function booking_manage_url(array $b): string {
    return app_base_url() . '/?foglalas=' . $b['token'];
}

function booking_details_text(array $b, array $settings): string {
    $lines = [
        'Szolgáltatás: ' . $b['service_name'],
        'Időpont: ' . booking_when($b),
    ];
    if ($settings['business']['address'] !== '') $lines[] = 'Helyszín: ' . $settings['business']['address'];
    $lines[] = 'Név: ' . $b['name'];
    if ($b['phone'] !== '') $lines[] = 'Telefon: ' . $b['phone'];
    if ($b['email'] !== '') $lines[] = 'Email: ' . $b['email'];

    $values = json_decode((string) ($b['fields'] ?? ''), true) ?: [];
    foreach ($settings['fields'] as $f) {
        $v = trim((string) ($values[$f['key']] ?? ''));
        if ($v !== '') $lines[] = $f['label'] . ': ' . $v;
    }
    if (trim((string) $b['note']) !== '') $lines[] = 'Megjegyzés: ' . trim((string) $b['note']);
    return implode("\n", $lines);
}

function business_signature(array $settings): string {
    $biz = $settings['business'];
    $parts = array_filter([$biz['phone'], $biz['email'], $biz['website']]);
    return $biz['name'] . ($parts ? "\n" . implode(' · ', $parts) : '');
}

// $event: created | confirmed | rejected | cancelled_customer | cancelled_admin
function notify_booking(array $b, string $event): void {
    $settings = get_settings();
    $config = app_config() ?? [];
    $biz = $settings['business']['name'];
    $when = booking_when($b);
    $details = booking_details_text($b, $settings);

    // --- ügyfél ---
    if ($settings['notify']['send_customer'] && filter_var($b['email'], FILTER_VALIDATE_EMAIL)) {
        $manage = "A foglalásod bármikor megnézheted itt:\n" . booking_manage_url($b);
        switch ($event) {
            case 'created':
                if ($b['status'] === 'pending') {
                    $subject = "Foglalási kérés beérkezett – $biz";
                    $intro = "Megkaptuk a foglalási kérésedet. Hamarosan visszaigazoljuk emailben.";
                } else {
                    $subject = "Foglalás visszaigazolva – $when";
                    $intro = "Rögzítettük és visszaigazoltuk a foglalásodat.";
                }
                break;
            case 'confirmed':
                $subject = "Foglalás visszaigazolva – $when";
                $intro = "Visszaigazoltuk a foglalásodat, várunk!";
                break;
            case 'rejected':
                $subject = "A kért időpont nem elérhető – $biz";
                $intro = "Sajnos a kért időpontot nem tudjuk vállalni. Kérjük, válassz másik időpontot:\n" . app_base_url() . '/';
                $manage = '';
                break;
            case 'cancelled_customer':
                $subject = "Foglalás lemondva – $biz";
                $intro = "A foglalásodat lemondtad. Ha mégis szeretnél jönni, új időpontot itt foglalhatsz:\n" . app_base_url() . '/';
                $manage = '';
                break;
            case 'cancelled_admin':
                $subject = "Foglalás lemondva – $biz";
                $intro = "Sajnos a foglalásodat le kellett mondanunk.";
                $manage = "Új időpontot itt foglalhatsz:\n" . app_base_url() . '/';
                break;
            default:
                $subject = '';
        }
        if ($subject !== '') {
            $adminNote = trim((string) ($b['admin_note'] ?? ''));
            $body = "Kedves {$b['name']}!\n\n$intro\n\n"
                . ($adminNote !== '' && in_array($event, ['confirmed', 'rejected', 'cancelled_admin'], true) ? "Üzenetünk: $adminNote\n\n" : '')
                . "$details\n\n"
                . ($manage !== '' ? "$manage\n\n" : '')
                . business_signature($settings);
            send_app_email($config, $b['email'], $subject, $body);
        }
    }

    // --- cég ---
    $adminEmail = $settings['notify']['admin_email'];
    if ($settings['notify']['send_admin'] && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)
        && in_array($event, ['created', 'cancelled_customer'], true) && ($b['source'] ?? 'online') === 'online') {
        if ($event === 'created') {
            $subject = ($b['status'] === 'pending' ? 'Jóváhagyásra vár: ' : 'Új foglalás: ') . "$when – {$b['name']}";
            $intro = $b['status'] === 'pending'
                ? 'Új foglalási kérés érkezett, jóváhagyásra vár.'
                : 'Új foglalás érkezett (automatikusan visszaigazolva).';
        } else {
            $subject = "Lemondta: $when – {$b['name']}";
            $intro = 'Az ügyfél lemondta a foglalását, az időpont felszabadult.';
        }
        $body = "$intro\n\n$details\n\nAdmin felület:\n" . app_base_url() . '/admin/';
        send_app_email($config, $adminEmail, $subject, $body);
    }
}
