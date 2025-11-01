<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Send WhatsApp • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:960px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#2563eb;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .err{color:#fca5a5;margin:0 0 8px}
    .ok{color:#86efac;margin:0 0 8px}
    a{color:#93c5fd}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>Send WhatsApp</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <form method="post" action="/whatsapp/send">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="to">To (WhatsApp number, E.164)</label>
      <input id="to" name="to" type="text" placeholder="+15551234567" required>
      <label for="body">Message</label>
      <textarea id="body" name="body" rows="4" required></textarea>
      <button class="btn" type="submit">Send</button>
    </form>
    <p><a href="/whatsapp/inbox">Read WhatsApp</a></p>
  </div>
  </div>
    </div>
  </div>
</body>
</html>



