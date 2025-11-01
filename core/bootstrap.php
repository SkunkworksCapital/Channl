<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/env.php';
load_env(BASE_PATH . '/.env');

$CONFIG = require __DIR__ . '/config.php';

// Error reporting based on environment
if (($CONFIG['app_env'] ?? 'prod') === 'dev') {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/http.php';

// Security headers and strict session
set_security_headers();
start_secure_session();

// Initialize database connection lazily via db() helper
// Use db() wherever needed; it will create a shared PDO instance.


