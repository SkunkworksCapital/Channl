<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:720px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#2563eb;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .err{color:#fca5a5;margin:0 0 8px}
    .ok{color:#86efac;margin:0 0 8px}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>User Settings</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <form method="post" action="/settings">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" value="<?= h($user['name'] ?? '') ?>">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= h($user['email'] ?? '') ?>" required>
      <label for="password">New password (optional)</label>
      <input id="password" name="password" type="password">
      <label for="confirm">Confirm password</label>
      <input id="confirm" name="confirm" type="password">
      <hr style="border:none;border-top:1px solid #374151;margin:16px 0">
      <h3>Messaging preferences</h3>
      <label for="timezone">Time zone</label>
      <?php $tz = isset($user['timezone']) && $user['timezone'] ? $user['timezone'] : 'UTC'; ?>
      <select id="timezone" name="timezone">
        <?php
          $tzOpts = ['UTC','America/New_York','America/Los_Angeles','Europe/London','Europe/Berlin','Asia/Kolkata','Asia/Singapore','Australia/Sydney'];
          foreach ($tzOpts as $opt) {
            $sel = ($tz === $opt) ? ' selected' : '';
            echo '<option value="' . h($opt) . '"' . $sel . '>' . h($opt) . '</option>';
          }
        ?>
      </select>
      <div style="display:flex;gap:12px">
        <div style="flex:1">
          <label for="quiet_start">Quiet hours start (local)</label>
          <input id="quiet_start" name="quiet_start" type="time" value="<?= h($user['quiet_start'] ?? '') ?>">
        </div>
        <div style="flex:1">
          <label for="quiet_end">Quiet hours end (local)</label>
          <input id="quiet_end" name="quiet_end" type="time" value="<?= h($user['quiet_end'] ?? '') ?>">
        </div>
      </div>
      <div style="display:flex;gap:12px">
        <div style="flex:1">
          <label for="daily_cap_sms">Daily SMS cap (0 = unlimited)</label>
          <input id="daily_cap_sms" name="daily_cap_sms" type="number" min="0" value="<?= h((string)($user['daily_cap_sms'] ?? '0')) ?>">
        </div>
        <div style="flex:1">
          <label for="daily_cap_email">Daily Email cap (0 = unlimited)</label>
          <input id="daily_cap_email" name="daily_cap_email" type="number" min="0" value="<?= h((string)($user['daily_cap_email'] ?? '0')) ?>">
        </div>
      </div>
      <button class="btn" type="submit">Save</button>
    </form>
  </div>
  <div class="card">
    <h2>Security</h2>
    <p>Two-factor authentication adds an extra layer of security at login.</p>
    <?php $twofaEnabled = twofactor_is_enabled((int)($user['id'] ?? 0)); ?>
    <?php if ($twofaEnabled): ?>
      <form method="post" action="/settings/2fa/disable" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button class="btn" type="submit">Disable 2FA</button>
      </form>
    <?php else: ?>
      <form method="post" action="/settings/2fa/enable" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button class="btn" type="submit">Enable 2FA (via email)</button>
      </form>
    <?php endif; ?>
  </div>
  </div>
    </div>
  </div>
</body>
</html>



