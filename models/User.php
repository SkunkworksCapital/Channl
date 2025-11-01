<?php
declare(strict_types=1);

final class User {
  public static function findByEmail(string $email): ?array {
    $stmt = db()->prepare('SELECT id, email, password_hash, name, is_admin FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function findById(int $id): ?array {
    $stmt = db()->prepare('SELECT id, email, password_hash, name, is_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
  }

  public static function create(string $email, string $password, ?string $name = null): int {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
    $stmt->execute([$email, $hash, $name]);
    return (int)db()->lastInsertId();
  }
}


