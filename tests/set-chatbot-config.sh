#!/usr/bin/env bash
# Teszt config.php a chatbothoz: CI MySQL + ál-Anthropic API (tests/mock-anthropic.php).
cat > apps/chatbot/api/config.php <<PHP
<?php
return [
    'db' => ['host' => '127.0.0.1', 'name' => 'idopont', 'user' => 'idopont', 'pass' => 'tesztjelszo', 'charset' => 'utf8mb4'],
    'prefix' => 'chat_',
    'session_name' => 'chatbot_test',
    'anthropic' => ['api_key' => 'test-key', 'base_url' => 'http://127.0.0.1:8001'],
    'smtp' => [],
];
PHP
