<?php
declare(strict_types=1);

function set_security_headers(): void {
  if (headers_sent()) return;
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header('Referrer-Policy: no-referrer');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
  header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
  if (!empty($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
  }
}

function start_secure_session(): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  $headersAlreadySent = headers_sent();
  if (!$headersAlreadySent) {
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'domain' => '',
      'secure' => $secure,
      'httponly' => true,
      'samesite' => 'Strict',
    ]);
    session_name('CHANNLSESSID');
  }
  // Attempt to start a session even if headers already sent (suppresses warnings)
  $started = @session_start();
  if ($started && empty($_SESSION['__init'])) {
    $_SESSION['__init'] = time();
    @session_regenerate_id(true);
  }
}

function ip_in_cidr(string $ip, string $cidr): bool {
  if (strpos($cidr, '/') === false) return $ip === $cidr;
  [$subnet, $mask] = explode('/', $cidr, 2);
  $mask = (int)$mask;
  $ipLong = ip2long($ip);
  $subnetLong = ip2long($subnet);
  if ($ipLong === false || $subnetLong === false) return false;
  $maskLong = -1 << (32 - $mask);
  return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

function enforce_ip_allowlist_if_authenticated(): void {
  if (!function_exists('current_user_id')) return;
  $uid = current_user_id();
  if (!$uid) return;
  $list = getenv('IP_ALLOWLIST');
  if ($list === false || trim($list) === '') return;
  $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  if ($ip === '') return;
  $allowed = false;
  foreach (explode(',', $list) as $entry) {
    $entry = trim($entry);
    if ($entry === '') continue;
    if (ip_in_cidr($ip, $entry)) { $allowed = true; break; }
  }
  if (!$allowed) {
    if (function_exists('audit_log')) { audit_log('security.ip_block', 'user', $uid, ['ip' => $ip]); }
    // Logout and block
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

function csrf_validate(?string $token): bool {
  return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}


