<?php
declare(strict_types=1);

final class User {
  public static function findByEmail(string $email): ?array {
    $stmt = db()->prepare('SELECT id, email, password_hash, name, is_admin, timezone, quiet_start, quiet_end, daily_cap_sms, daily_cap_email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function findById(int $id): ?array {
    $stmt = db()->prepare('SELECT id, email, password_hash, name, is_admin, timezone, quiet_start, quiet_end, daily_cap_sms, daily_cap_email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function create(string $email, string $password, ?string $name = null): int {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    // Ensure columns for scheduling settings exist
    try {
      db()->exec('ALTER TABLE users ADD COLUMN timezone VARCHAR(64) NULL');
    } catch (Throwable $e) {}
    try {
      db()->exec('ALTER TABLE users ADD COLUMN quiet_start TIME NULL');
    } catch (Throwable $e) {}
    try {
      db()->exec('ALTER TABLE users ADD COLUMN quiet_end TIME NULL');
    } catch (Throwable $e) {}
    try {
      db()->exec('ALTER TABLE users ADD COLUMN daily_cap_sms INT NULL');
    } catch (Throwable $e) {}
    try {
      db()->exec('ALTER TABLE users ADD COLUMN daily_cap_email INT NULL');
    } catch (Throwable $e) {}

    $stmt = db()->prepare('INSERT INTO users (email, password_hash, name, timezone, quiet_start, quiet_end, daily_cap_sms, daily_cap_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, 'UTC', null, null, null, null]);
    return (int)db()->lastInsertId();
  }
}


