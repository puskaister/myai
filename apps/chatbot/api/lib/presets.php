<?php
declare(strict_types=1);

// Telepítéskor választható kiinduló csomagok (utána minden átírható).

function presets(): array {
    $q = fn (string $key, string $question, string $hint = '', bool $required = true) => compact('key', 'question', 'hint', 'required');

    return [
        'gumiszerviz' => [
            'label'     => 'Gumiszerviz',
            'primary'   => '#e4572e',
            'bot'       => 'Gumiszerviz asszisztens',
            'greeting'  => 'Szia! Gumicsere, javítás vagy új gumi? Írd meg, miben segíthetek, és egyeztetünk!',
            'knowledge' => "Nyitvatartás: hétfő–péntek 8–17, szombat 8–12.\nSzolgáltatások: szezonális gumicsere, gumiszerelés és centírozás, defektjavítás, gumitárolás, új és használt gumik értékesítése.\n(Írd ide az árakat, a címet és a gyakori kérdéseket.)",
            'questions' => [
                $q('service', 'Milyen szolgáltatásra van szükséged?', 'pl. gumicsere, defektjavítás, új gumi vásárlás, tárolás'),
                $q('car', 'Milyen autóról van szó?', 'márka és típus'),
                $q('tyre', 'Mekkora a gumiméret?', 'pl. 205/55 R16 — ha nem tudja, a gumi oldalán található; ha nem tudja megnézni, írd be, hogy "nem tudja"'),
                $q('when', 'Mikor jönnél legszívesebben?', 'nap és napszak'),
                $q('name', 'Mi a neved?'),
                $q('phone', 'Milyen telefonszámon érünk el?', 'magyar telefonszám'),
            ],
        ],
        'erdeklodo' => [
            'label'     => 'Általános érdeklődő-felmérés',
            'primary'   => '#2563eb',
            'bot'       => 'Asszisztens',
            'greeting'  => 'Szia! Miben segíthetek?',
            'knowledge' => '(Írd ide, mivel foglalkozik a vállalkozás, a nyitvatartást, az árakat és a gyakori kérdéseket.)',
            'questions' => [
                $q('topic', 'Miben segíthetünk?', 'az érdeklődés rövid leírása'),
                $q('deadline', 'Mikorra lenne rá szükséged?'),
                $q('name', 'Mi a neved?'),
                $q('contact', 'Milyen telefonszámon vagy email címen érünk el?'),
            ],
        ],
        'fejlesztes' => [
            'label'     => 'Egyedi web- és appfejlesztés (ajánlatkérés)',
            'primary'   => '#4f46e5',
            'bot'       => 'my-ai asszisztens',
            'greeting'  => 'Szia! Weboldalt, appot vagy egyedi rendszert szeretnél? Mesélj róla pár szóban, és segítek összeszedni, mire van szükséged!',
            'knowledge' => "Egyedi webes és alkalmazásigényeket fejlesztünk rövid határidővel: weboldalak, webshopok, admin felületek, ügyfélkapuk, valamint telefonon és weben is működő appok (App Store nélkül telepíthetők).\nKész megoldás: online időpontfoglaló (élő demó: my-ai.hu/foglalas).\nÁrakat konkrét ajánlatban adunk, az igények felmérése után.",
            'questions' => [
                $q('type', 'Milyen megoldásra van szükséged?', 'pl. weboldal, webshop, app, egyedi rendszer'),
                $q('goal', 'Mit kellene tudnia, mi a fő cél?', 'néhány mondatos leírás'),
                $q('deadline', 'Mikorra lenne rá szükséged?'),
                $q('budget', 'Van már elképzelésed a keretről?', 'nem kötelező, ne erőltesd', false),
                $q('name', 'Mi a neved?'),
                $q('contact', 'Milyen email címen vagy telefonszámon érünk el?'),
            ],
        ],
    ];
}

function apply_preset(string $key, string $businessName, string $adminEmail): void {
    $p = presets()[$key] ?? presets()['erdeklodo'];
    $d = default_settings();
    save_setting_group('business', array_merge($d['business'], ['name' => $businessName, 'email' => $adminEmail]));
    save_setting_group('bot', array_merge($d['bot'], ['name' => $p['bot'], 'greeting' => $p['greeting'], 'knowledge' => $p['knowledge']]));
    save_setting_group('questions', $p['questions']);
    save_setting_group('theme', ['primary' => $p['primary']]);
    save_setting_group('notify', ['admin_email' => $adminEmail, 'send_admin' => true]);
    save_setting_group('ai', $d['ai']);
}
