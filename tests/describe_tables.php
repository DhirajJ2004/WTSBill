<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
require_once ROOT_PATH . '/app/Core/helpers.php';
\WTSBill\Core\Env::load(ROOT_PATH . '/.env');
\WTSBill\Core\Config::loadFromDirectory(ROOT_PATH . '/config');

print_r(\WTSBill\Core\Database::select("DESCRIBE journal_entry_lines"));
print_r(\WTSBill\Core\Database::select("SELECT * FROM chart_of_accounts LIMIT 2"));
