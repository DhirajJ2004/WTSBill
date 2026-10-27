<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
\WTSBill\Core\Env::load(dirname(__DIR__) . '/.env');
\WTSBill\Core\Config::loadFromDirectory(dirname(__DIR__) . '/config');

require_once dirname(__DIR__) . '/database/seeders/RbacPermissionSeeder.php';
$result = \Database\Seeders\RbacPermissionSeeder::run();
echo "=== SEEDER RESULT ===" . PHP_EOL;
print_r($result);
