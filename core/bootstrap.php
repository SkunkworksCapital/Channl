<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Start output buffering early to avoid accidental output breaking headers/redirects
if (!ob_get_level()) { ob_start(); }

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

// Ensure PHP logs actually land in public/error_log so we can inspect in production too
ini_set('log_errors', '1');
if (!ini_get('error_log')) {
  @ini_set('error_log', BASE_PATH . '/public/error_log');
}

// Catch fatal errors and write a last-chance line to the log
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
    error_log('[FATAL] ' . ($e['message'] ?? 'unknown') . ' in ' . ($e['file'] ?? '?') . ':' . ($e['line'] ?? 0));
  }
});

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/twofactor.php';

// Security headers and strict session
set_security_headers();
start_secure_session();
enforce_ip_allowlist_if_authenticated();

// Initialize database connection lazily via db() helper
// Use db() wherever needed; it will create a shared PDO instance.


