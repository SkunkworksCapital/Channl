<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once dirname(__DIR__) . '/core/router.php';

try {
  route_request();
} catch (Throwable $e) {
  error_log('[UNCAUGHT] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
  http_response_code(500);
  if (($GLOBALS['CONFIG']['app_env'] ?? 'prod') === 'dev') {
    echo 'Server error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}


