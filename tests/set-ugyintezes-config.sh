#!/usr/bin/env bash
cat > apps/ugyintezes/api/config.php <<PHP
<?php
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'idopont', 'user' => 'idopont', 'pass' => 'tesztjelszo', 'charset' => 'utf8mb4'],
    'prefix' => 'seged_',
    'session_name' => 'ugyintezes_test',
    'smtp' => [],
];
PHP
