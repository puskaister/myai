<?php
// Másold config.php néven, és töltsd ki. SOSE commitold — jelszót és API kulcsot tartalmaz.
// GitHub Actions deploynál a workflow a repó secretjeiből maga hozza létre.
return [
    'db' => [
        'host'    => '172.30.50.11', // DiMa: a MySQL külön szerveren van
        'name'    => 'adatbazis_neve',
        'user'    => 'adatbazis_felhasznalo',
        'pass'    => 'valtoztasd-meg',
        'charset' => 'utf8mb4',
    ],
    'prefix' => 'chat_',                 // táblanév-előtag (közös adatbázisban)
    'session_name' => 'chatbot_session',
    'anthropic' => [
        'api_key'  => 'sk-ant-...',      // https://console.anthropic.com
        'base_url' => '',                // csak tesztekhez (ál-API); élesben üres
    ],
    'smtp' => ['host' => '', 'port' => 465, 'username' => '', 'password' => '', 'from' => ''],
];
