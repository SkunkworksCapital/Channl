<?php
declare(strict_types=1);

require_once BASE_PATH . '/models/User.php';

final class SettingsController {
  public static function profile(): void {
    self::auth();
    $user = User::findById((int)current_user_id());
    view('settings/index', [ 'user' => $user, 'ok' => flash_get('ok'), 'error' => flash_get('error') ]);
  }

  public static function update(): void {
    self::auth();
    require_csrf_or_400();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      flash_set('error', 'Provide a valid email.');
      redirect('/settings');
    }
    // ensure email unique if changed
    $u = User::findById((int)current_user_id());
    if (!$u) { flash_set('error', 'User not found.'); redirect('/settings'); }
    if (strcasecmp($u['email'], $email) !== 0) {
      $chk = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $chk->execute([$email, current_user_id()]);
      if ($chk->fetch()) { flash_set('error', 'Email already in use.'); redirect('/settings'); }
    }

    $params = [ $name !== '' ? $name : null, $email, current_user_id() ];
    db()->prepare('UPDATE users SET name = COALESCE(?, name), email = ? WHERE id = ?')->execute($params);
    audit_log('user.profile_updated', 'user', current_user_id(), [ 'old_email' => $u['email'], 'new_email' => $email, 'old_name' => $u['name'], 'new_name' => ($name !== '' ? $name : $u['name']) ]);

    if ($password !== '') {
      if ($password !== $confirm) { flash_set('error', 'Passwords do not match.'); redirect('/settings'); }
      $hash = password_hash($password, PASSWORD_BCRYPT);
      db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, current_user_id()]);
      audit_log('user.password_changed', 'user', current_user_id());
    }

    flash_set('ok', 'Profile updated.');
    redirect('/settings');
  }

  public static function enable2fa(): void {
    self::auth();
    require_csrf_or_400();
    $uid = (int)current_user_id();
    twofactor_set_enabled($uid, true);
    audit_log('user.2fa_enabled', 'user', $uid);
    flash_set('ok', 'Two-factor authentication enabled.');
    redirect('/settings');
  }

  public static function disable2fa(): void {
    self::auth();
    require_csrf_or_400();
    $uid = (int)current_user_id();
    twofactor_set_enabled($uid, false);
    audit_log('user.2fa_disabled', 'user', $uid);
    flash_set('ok', 'Two-factor authentication disabled.');
    redirect('/settings');
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }
}



