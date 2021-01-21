<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/analytics.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define("RABBIT_HOST", $_ENV['RABBIT_HOST'] ?? '');
define('RABBIT_PORT', $_ENV['RABBIT_PORT'] ?? '');
define('RABBIT_LOGIN', $_ENV['RABBIT_LOGIN'] ?? '');
define('RABBIT_PSWD', $_ENV['RABBIT_PSWD'] ?? '');
define('RABBIT_CHANNEL_NAME', $_ENV['RABBIT_CHANNEL_NAME'] ?? '');
define('RABBIT_V_HOST', $_ENV['RABBIT_V_HOST'] ?? '');

define("ANALYTICS_DB_HOST", $_ENV['ANALYTICS_DB_HOST'] ?? '');
define('ANALYTICS_DB_PORT', $_ENV['ANALYTICS_DB_PORT'] ?? '');
define('ANALYTICS_DB_NAME', $_ENV['ANALYTICS_DB_NAME'] ?? '');
define('ANALYTICS_DB_LOGIN', $_ENV['ANALYTICS_DB_LOGIN'] ?? '');
define('ANALYTICS_DB_PSWD', $_ENV['ANALYTICS_DB_PSWD'] ?? '');

Analytics::consumerNewUser();
exit;