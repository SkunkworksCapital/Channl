<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0;padding:2rem}
    .card{max-width:420px;margin:4rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    input{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#10b981;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .err{color:#fca5a5;margin:0 0 8px}
    a{color:#93c5fd}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="card">
    <h2>Register</h2>
    <?php if (!empty($error)): ?>
      <p class="err"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/register">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" autocomplete="name">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="email" required>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required>
      <button class="btn" type="submit">Create account</button>
    </form>
    <p>Already have an account? <a href="/login">Login</a></p>
  </div>
</body>
</html>


