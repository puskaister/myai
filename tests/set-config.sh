#!/usr/bin/env bash
# Teszt config.php a CI MySQL-hez, a megadott táblanév-előtaggal (üres = nincs előtag).
cat > apps/idopontfoglalo/api/config.php <<PHP
<?php
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'idopont', 'user' => 'idopont', 'pass' => 'tesztjelszo', 'charset' => 'utf8mb4'],
    'prefix' => '${1:-}',
    'session_name' => 'idopont_test_${1:-none}',
    'smtp' => [],
    // havi díj nyilvántartás (élesben a Tanítható chatbot seged_hosting táblája); amíg nincs tábla, nincs felfüggesztés
    'hosting' => ['table' => 'kozos_hosting', 'key' => 'teszt'],
];
PHP
