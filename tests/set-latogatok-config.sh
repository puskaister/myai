#!/usr/bin/env bash
cat > apps/latogatok/api/config.php <<PHP
<?php
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'idopont', 'user' => 'idopont', 'pass' => 'tesztjelszo', 'charset' => 'utf8mb4'],
    'prefix' => 'stat_',
    'session_name' => 'latogatok_test',
    'smtp' => [],
];
PHP
