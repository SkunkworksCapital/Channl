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
    // Drop any buffered output so headers can be sent cleanly
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Location: ' . $path, true, 302);
    exit;
  }
  $f = ''; $l = 0; headers_sent($f, $l);
  error_log('[REDIRECT_FAIL_HEADERS_SENT] to ' . $path . ' (first output at ' . $f . ':' . $l . ')');
  // Fallback client-side redirect
  echo '<!doctype html><meta http-equiv="refresh" content="0;url=' . h($path) . '"><script>location.replace(' . json_encode($path) . ');</script>';
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

function current_user(): ?array {
  static $cached = null;
  $uid = current_user_id();
  if (!$uid) return null;
  if (is_array($cached) && isset($cached['id']) && (int)$cached['id'] === (int)$uid) return $cached;
  require_once BASE_PATH . '/models/User.php';
  $u = User::findById((int)$uid);
  if (is_array($u)) { $cached = $u; return $u; }
  return null;
}

function is_admin(): bool {
  $u = current_user();
  return is_array($u) && !empty($u['is_admin']);
}

function require_admin(): void {
  if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }
}

function require_csrf_or_400(): void {
  $token = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
  // Only enforce CSRF for authenticated actions; allow public endpoints (login/register) without blocking
  $enforce = function_exists('current_user_id') && current_user_id();
  if ($enforce && !csrf_validate(is_string($token) ? $token : '')) {
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

// Wallet / credits
function ensure_wallet_tables(): void {
  db()->exec('CREATE TABLE IF NOT EXISTS wallets (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    balance DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  db()->exec('CREATE TABLE IF NOT EXISTS wallet_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,4) NOT NULL,
    type ENUM("credit","debit") NOT NULL,
    reason VARCHAR(255) NULL,
    meta JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_wallet_tx_user (user_id),
    KEY ix_wallet_tx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
}

function wallet_get_balance(int $userId): float {
  ensure_wallet_tables();
  $q = db()->prepare('SELECT balance FROM wallets WHERE user_id = ?');
  $q->execute([$userId]);
  $row = $q->fetch();
  return $row ? (float)$row['balance'] : 0.0;
}

function wallet_credit(int $userId, float $amount, string $reason = '', $meta = null): void {
  ensure_wallet_tables();
  db()->prepare('INSERT INTO wallet_transactions (user_id, amount, type, reason, meta) VALUES (?, ?, "credit", ?, ?)')
    ->execute([$userId, $amount, $reason, json_encode($meta)]);
  db()->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)')
    ->execute([$userId, $amount]);
}

function wallet_debit(int $userId, float $amount, string $reason = '', $meta = null): bool {
  ensure_wallet_tables();
  $bal = wallet_get_balance($userId);
  if ($amount <= 0) return true;
  if ($bal + 1e-9 < $amount) return false;
  db()->prepare('INSERT INTO wallet_transactions (user_id, amount, type, reason, meta) VALUES (?, ?, "debit", ?, ?)')
    ->execute([$userId, $amount, $reason, json_encode($meta)]);
  db()->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ?')
    ->execute([$amount, $userId]);
  return true;
}

function rate_for_channel(string $channel): float {
  $rates = $GLOBALS['CONFIG']['rates'] ?? [];
  if ($channel === 'sms') return (float)($rates['sms'] ?? 0.03);
  if ($channel === 'email') return (float)($rates['email'] ?? 0.002);
  if ($channel === 'whatsapp') return (float)($rates['whatsapp'] ?? 0.02);
  return 0.0;
}

function compute_conversation_id(int $userId, string $channel, string $addr): string {
  return sha1($userId . ':' . strtolower($channel) . ':' . trim($addr));
}


