<?php
// Másold ezt a fájlt config.php néven (ugyanebbe a mappába), és töltsd ki
// a tárhely MySQL adataival. A config.php-t SOSE commitold — jelszót tartalmaz.
// GitHub Actions deploynál a workflow a repó secretjeiből maga hozza létre.
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'adatbazis_neve',
        'user'    => 'adatbazis_felhasznalo',
        'pass'    => 'valtoztasd-meg',
        'charset' => 'utf8mb4',
    ],

    // Több telepítés ugyanazon a domainen (pl. /foglalas és /foglalas2)
    // ne ossza meg a munkamenetet — ezért telepítésenként egyedi név.
    'session_name' => 'idopont_session',

    // Opcionális: ha a tárhely natív mail() függvénye nem kézbesít
    // megbízhatóan, töltsd ki egy postafiók SMTP adataival. Üresen hagyva a
    // rendszer a natív mail()-t használja.
    'smtp' => [
        'host'     => '',
        'port'     => 465,
        'username' => '',
        'password' => '',
        'from'     => '',
    ],
];
