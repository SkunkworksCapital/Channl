<?php
declare(strict_types=1);

function audit_ensure_table(): void {
  static $done = false;
  if ($done) return;
  $sql = 'CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    resource_type VARCHAR(64) NULL,
    resource_id VARCHAR(128) NULL,
    ip VARBINARY(16) NULL,
    user_agent VARCHAR(255) NULL,
    meta JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_audit_actor_created (actor_user_id, created_at),
    KEY ix_audit_action_created (action, created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
  try { db()->exec($sql); $done = true; } catch (Throwable $e) { /* ignore */ }
}

function audit_log(string $action, ?string $resourceType = null, $resourceId = null, array $meta = []): void {
  try {
    audit_ensure_table();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ipPacked = '';
    if ($ip !== '') {
      $packed = @inet_pton($ip);
      if ($packed !== false) { $ipPacked = $packed; }
    }
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $m = json_encode($meta, JSON_UNESCAPED_SLASHES);
    $stmt = db()->prepare('INSERT INTO audit_logs (actor_user_id, action, resource_type, resource_id, ip, user_agent, meta) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([ current_user_id(), $action, $resourceType, is_null($resourceId) ? null : (string)$resourceId, $ipPacked, $ua, $m ]);
  } catch (Throwable $e) {
    // swallow
  }
}


