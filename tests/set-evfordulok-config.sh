#!/usr/bin/env bash
cat > apps/evfordulok/api/config.php <<PHP
<?php
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'idopont', 'user' => 'idopont', 'pass' => 'tesztjelszo', 'charset' => 'utf8mb4'],
    'prefix' => 'evf_',
    'session_name' => 'evfordulok_test',
    'smtp' => [],
];
PHP
