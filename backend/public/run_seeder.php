<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, '"\' ');
    }
}

use App\Database\Database;
use App\Database\SchemaManager;
use App\Database\DatabaseSeeder;
use Illuminate\Database\Capsule\Manager as DB;

Database::init();

try {
    // Fresh Migration
    DB::schema()->dropAllTables();
    SchemaManager::migrate();

    // Seed
    DatabaseSeeder::seed();

    echo "SEEDED_SUCCESSFULLY: " . \App\Models\User::count() . " users.";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
