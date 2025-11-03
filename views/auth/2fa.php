<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Two-Factor Verification • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:420px;margin:4rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    input{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#2563eb;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer;width:100%}
    .err{color:#fca5a5;margin:0 0 8px}
  </style>
  </head>
  <body>
    <div class="card">
      <h2>Enter verification code</h2>
      <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      <form method="post" action="/login/2fa">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label for="code">6-digit code</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" placeholder="123456" required>
        <button class="btn" type="submit">Verify</button>
      </form>
    </div>
  </body>
  </html>

