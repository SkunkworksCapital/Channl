<?php
declare(strict_types=1);

function env_get(string $key, ?string $default = null): ?string {
  $val = getenv($key);
  if ($val === false) {
    return $default;
  }
  return $val;
}

function env_get_int(string $key, int $default = 0): int {
  $val = env_get($key, null);
  if ($val === null) return $default;
  if (is_numeric($val)) return (int)$val;
  return $default;
}

function env_get_float(string $key, float $default = 0.0): float {
  $val = env_get($key, null);
  if ($val === null) return $default;
  if (is_numeric($val)) return (float)$val;
  return $default;
}

function env_get_bool(string $key, bool $default = false): bool {
  $val = env_get($key, null);
  if ($val === null) return $default;
  $val = strtolower(trim($val));
  return in_array($val, ['1','true','yes','on'], true);
}

function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void {
  if (!headers_sent()) {
    header('Location: ' . $path, true, 302);
  }
  exit;
}

function flash_set(string $key, string $message): void {
  $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
  if (!isset($_SESSION['flash'][$key])) return null;
  $msg = $_SESSION['flash'][$key];
  unset($_SESSION['flash'][$key]);
  return $msg;
}

function current_user_id(): ?int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_csrf_or_400(): void {
  $token = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
  if (!csrf_validate(is_string($token) ? $token : '')) {
    http_response_code(400);
    echo 'Bad Request';
    exit;
  }
}


function render_message_template(string $template, array $context): string {
  $tpl = (string)$template;
  return (string)preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($context) {
    $k = $m[1];
    if (array_key_exists($k, $context)) return (string)$context[$k];
    return $m[0];
  }, $tpl);
}


