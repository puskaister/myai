#!/usr/bin/env bash
cat > apps/bevasarlolista/api/config.php <<PHP
<?php
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'idopont', 'user' => 'idopont', 'pass' => 'tesztjelszo', 'charset' => 'utf8mb4'],
    'prefix' => 'bev_',
    'session_name' => 'bevasarlolista_test',
    'smtp' => [],
];
PHP
