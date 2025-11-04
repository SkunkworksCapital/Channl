<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Contact • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:720px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
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
    <p><a href="/contacts/<?= (int)$contact['id'] ?>">← Back to Contact</a></p>
    <h2>Edit Contact</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <form method="post" action="/contacts/<?= (int)$contact['id'] ?>">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" value="<?= h($contact['name'] ?? '') ?>">

      <label for="email">Email</label>
      <input id="email" name="email" type="email" value="<?= h($contact['email'] ?? '') ?>">

      <label for="phone">Phone (E.164)</label>
      <input id="phone" name="phone" type="text" value="<?= h($contact['phone'] ?? '') ?>">

      <label for="country">Country (2-letter)</label>
      <input id="country" name="country" type="text" maxlength="2" value="<?= h($contact['country'] ?? '') ?>">

      <label for="tags">Tags (comma separated)</label>
      <input id="tags" name="tags" type="text" value="<?= h(($contact['tags'] ? implode(', ', (array)(json_decode($contact['tags'], true) ?: [])) : '')) ?>">

      <button class="btn" type="submit">Save Changes</button>
    </form>
  </div>
  </div>
    </div>
  </div>
</body>
</html>


