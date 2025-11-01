<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>New Campaign • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:720px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#10b981;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .err{color:#fca5a5;margin:0 0 8px}
    .ok{color:#86efac;margin:0 0 8px}
    a{color:#93c5fd}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="container">
  <div class="card">
    <h2>New SMS Campaign</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <form method="post" action="/campaigns">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" required>
      <label for="scope">Recipients</label>
      <select id="scope" name="scope">
        <option value="all">All contacts</option>
        <option value="tags">Contacts with tags</option>
        <option value="list">Specific list</option>
      </select>
      <label for="tags">Tags (comma separated, used if scope = tags)</label>
      <input id="tags" name="tags" type="text" placeholder="customer, lead">
      <label for="list_id">List (used if scope = list)</label>
      <select id="list_id" name="list_id">
        <option value="">-- Select list --</option>
        <?php
          $stmt = db()->prepare('SELECT id, name FROM contact_lists WHERE user_id = ? ORDER BY name');
          $stmt->execute([current_user_id()]);
          foreach ($stmt->fetchAll() as $l) {
            echo '<option value="' . (int)$l['id'] . '">' . h($l['name']) . '</option>';
          }
        ?>
      </select>
      <label for="template_id">Template (optional)</label>
      <select id="template_id" name="template_id">
        <option value="">-- Select template --</option>
        <?php
          // ensure table exists
          db()->exec('CREATE TABLE IF NOT EXISTS sms_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_templates_user_name (user_id, name), KEY ix_templates_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
          $stmt2 = db()->prepare('SELECT id, name FROM sms_templates WHERE user_id = ? ORDER BY name');
          $stmt2->execute([current_user_id()]);
          foreach ($stmt2->fetchAll() as $t) {
            echo '<option value="' . (int)$t['id'] . '">' . h($t['name']) . '</option>';
          }
        ?>
      </select>
      <label for="body">Message</label>
      <textarea id="body" name="body" rows="5" required></textarea>
      <button class="btn btn-primary" type="submit">Create Campaign</button>
    </form>
    <p><a href="/campaigns">Back to campaigns</a></p>
  </div>
  </div>
</body>
</html>


