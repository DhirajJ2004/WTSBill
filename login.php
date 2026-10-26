<?php

declare(strict_types=1);

// Route legacy/direct script request to public front controller
$_SERVER['REQUEST_URI'] = str_replace('login.php', 'login', $_SERVER['REQUEST_URI'] ?? '/login');
require_once __DIR__ . '/public/index.php';
