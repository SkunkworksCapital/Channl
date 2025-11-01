<?php
declare(strict_types=1);

require_once BASE_PATH . '/models/User.php';

final class AuthController {
  public static function showLogin(): void {
    view('auth/login', [
      'error' => flash_get('error'),
    ]);
  }

  public static function login(): void {
    require_csrf_or_400();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
      flash_set('error', 'Email and password are required.');
      redirect('/login');
    }
    $user = User::findByEmail($email);
    if (!$user || !password_verify($password, $user['password_hash'])) {
      flash_set('error', 'Invalid credentials.');
      redirect('/login');
    }
    $_SESSION['user_id'] = (int)$user['id'];
    session_regenerate_id(true);
    redirect('/');
  }

  public static function showRegister(): void {
    view('auth/register', [
      'error' => flash_get('error'),
    ]);
  }

  public static function register(): void {
    require_csrf_or_400();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set('error', 'Valid email required.');
      redirect('/register');
    }
    if (strlen($password) < 8) {
      flash_set('error', 'Password must be at least 8 characters.');
      redirect('/register');
    }
    try {
      self::ensureUsersTable();
      if (User::findByEmail($email)) {
        flash_set('error', 'Email already registered.');
        redirect('/register');
      }
      $id = User::create($email, $password, $name !== '' ? $name : null);
      $_SESSION['user_id'] = $id;
      session_regenerate_id(true);
      redirect('/');
    } catch (PDOException $e) {
      $code = $e->getCode();
      if ($code === '23000') {
        flash_set('error', 'Email already registered.');
      } else {
        flash_set('error', 'Registration failed. Please try again.');
      }
      redirect('/register');
    } catch (Throwable $e) {
      flash_set('error', 'Registration failed.');
      redirect('/register');
    }
  }

  public static function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect('/');
  }

  private static function ensureUsersTable(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS users (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      email VARCHAR(255) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      name VARCHAR(255) NULL,
      is_admin TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  }
}


