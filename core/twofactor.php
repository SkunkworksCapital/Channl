<?php
declare(strict_types=1);

function twofactor_ensure_tables(): void {
  static $done = false;
  if ($done) return;
  try {
    db()->exec('CREATE TABLE IF NOT EXISTS user_twofactor (
      user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      enabled TINYINT(1) NOT NULL DEFAULT 0,
      method ENUM("email") NOT NULL DEFAULT "email",
      last_enabled_at TIMESTAMP NULL,
      last_verified_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    db()->exec('CREATE TABLE IF NOT EXISTS user_login_otps (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      code_hash CHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      attempts INT NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY ix_otp_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    $done = true;
  } catch (Throwable $e) { /* ignore */ }
}

function twofactor_is_enabled(int $userId): bool {
  twofactor_ensure_tables();
  try {
    $q = db()->prepare('SELECT enabled FROM user_twofactor WHERE user_id = ?');
    $q->execute([$userId]);
    $row = $q->fetch();
    return $row ? ((int)$row['enabled'] === 1) : false;
  } catch (Throwable $e) { return false; }
}

function twofactor_set_enabled(int $userId, bool $enabled): void {
  twofactor_ensure_tables();
  try {
    if ($enabled) {
      db()->prepare('INSERT INTO user_twofactor (user_id, enabled, last_enabled_at) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), last_enabled_at = NOW()')->execute([$userId]);
    } else {
      db()->prepare('INSERT INTO user_twofactor (user_id, enabled) VALUES (?, 0) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)')->execute([$userId]);
    }
  } catch (Throwable $e) { /* ignore */ }
}

//

function twofactor_generate_and_send(int $userId, string $email): bool {
  twofactor_ensure_tables();
  $code = strval(random_int(100000, 999999));
  $hash = hash('sha256', $code);
  $expiresAt = date('Y-m-d H:i:s', time() + 5 * 60);
  try {
    db()->prepare('DELETE FROM user_login_otps WHERE user_id = ?')->execute([$userId]);
    db()->prepare('INSERT INTO user_login_otps (user_id, code_hash, expires_at) VALUES (?, ?, ?)')->execute([$userId, $hash, $expiresAt]);
  } catch (Throwable $e) { return false; }
  $subject = 'Your verification code';
  $body = "Your verification code is: $code\nThis code expires in 5 minutes.";
  $send = send_email_via_config($email, $subject, $body);
  audit_log('auth.2fa_code_sent', 'user', $userId, ['provider' => $send['provider'] ?? 'email']);
  return (bool)($send['ok'] ?? false);
}

function twofactor_verify_code(int $userId, string $code): bool {
  twofactor_ensure_tables();
  $hash = hash('sha256', $code);
  try {
    $q = db()->prepare('SELECT id, code_hash, expires_at, attempts FROM user_login_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $q->execute([$userId]);
    $row = $q->fetch();
    if (!$row) return false;
    $expired = strtotime((string)$row['expires_at']) < time();
    $ok = !$expired && hash_equals((string)$row['code_hash'], $hash);
    $attempts = (int)$row['attempts'] + 1;
    db()->prepare('UPDATE user_login_otps SET attempts = ? WHERE id = ?')->execute([$attempts, (int)$row['id']]);
    if ($ok) {
      db()->prepare('DELETE FROM user_login_otps WHERE user_id = ?')->execute([$userId]);
      db()->prepare('UPDATE user_twofactor SET last_verified_at = NOW() WHERE user_id = ?')->execute([$userId]);
      audit_log('auth.2fa_verified', 'user', $userId);
      return true;
    }
  } catch (Throwable $e) { return false; }
  audit_log('auth.2fa_failed', 'user', $userId);
  return false;
}


