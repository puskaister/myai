<?php
// Másold ezt a fájlt config.php néven (ugyanebbe a mappába), és töltsd ki a
// tárhelyed MySQL adataival. A config.php jelszót tartalmaz — ne oszd meg.
return [
    'db' => [
        'host'    => 'localhost',          // a tárhelyszolgáltatód adja meg (néha egy IP-cím)
        'name'    => 'adatbazis_neve',
        'user'    => 'adatbazis_felhasznalo',
        'pass'    => 'adatbazis_jelszo',
        'charset' => 'utf8mb4',
    ],

    // Táblanév-előtag: ha az adatbázisban más is van, így nem akad össze.
    'prefix' => 'evf_',

    'session_name' => 'evfordulok_session',

    // Opcionális: SMTP az emlékeztető levelekhez (megbízhatóbb kézbesítés). Üresen a PHP mail() megy.
    'smtp' => ['host' => '', 'port' => 465, 'username' => '', 'password' => '', 'from' => ''],
];
