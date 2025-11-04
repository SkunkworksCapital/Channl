<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lists • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:960px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px 6px;text-align:left}
    a{color:#93c5fd}
    .err{color:#fca5a5}
    .ok{color:#86efac}
    input,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#10b981;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .boxes{display:grid;grid-template-columns:1fr;gap:16px;margin-top:16px}
    @media (min-width: 900px){ .boxes{grid-template-columns:1fr 1fr} }
    .box{padding:16px}
    .box-heading{margin:0 0 8px 0}
    .box-sms{border-left:4px solid #60a5fa}
    .box-email{border-left:4px solid #a78bfa}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>Contact Lists</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <form method="post" action="/lists">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="name">List name</label>
      <input id="name" name="name" type="text" required>
      <label for="description">Description (optional)</label>
      <input id="description" name="description" type="text">
      <label for="channel">Channel</label>
      <select id="channel" name="channel">
        <option value="sms">SMS</option>
        <option value="email">Email</option>
      </select>
      <label for="default_sms_template_id">Default SMS Template (optional)</label>
      <select id="default_sms_template_id" name="default_sms_template_id">
        <option value="">None</option>
        <?php
          db()->exec('CREATE TABLE IF NOT EXISTS message_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, type ENUM("sms","email") NOT NULL DEFAULT "sms", subject VARCHAR(255) NULL, body TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
          $t = db()->prepare('SELECT id, name FROM message_templates WHERE user_id = ? AND type = "sms" ORDER BY name');
          $t->execute([current_user_id()]);
          foreach ($t->fetchAll() as $tpl) {
            echo '<option value="' . (int)$tpl['id'] . '">' . h($tpl['name']) . '</option>';
          }
        ?>
      </select>
      <button class="btn" type="submit">Create list</button>
    </form>
    <?php
      try { db()->exec('ALTER TABLE contact_lists ADD COLUMN channel ENUM("sms","email") NOT NULL DEFAULT "sms" AFTER user_id'); } catch (Throwable $e) {}
      $sms = []; $email = [];
      foreach ($items as $l) { if (($l['channel'] ?? 'sms') === 'email') $email[] = $l; else $sms[] = $l; }
      function renderListTable($rows){
        if (empty($rows)) { echo '<p class="subtle">None</p>'; return; }
        echo '<table><thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Created</th><th></th></tr></thead><tbody>';
        foreach ($rows as $l){
          echo '<tr>';
          echo '<td><a href="/lists/' . (int)$l['id'] . '\">' . (int)$l['id'] . '</a></td>';
          echo '<td>' . h($l['name']) . '</td>';
          echo '<td>' . h($l['description'] ?? '') . '</td>';
          echo '<td>' . h($l['created_at']) . '</td>';
          echo '<td style="text-align:right">'
             . '<a class="btn" href="/lists/' . (int)$l['id'] . '" style="background:#2563eb;margin-right:8px">View</a>'
             . '<form method="post" action="/lists/' . (int)$l['id'] . '/delete" onsubmit="return confirm(\'Delete this list?\');" style="display:inline">'
             . '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'
             . '<button class="btn" type="submit" style="background:#ef4444">Delete</button>'
             . '</form>'
             . '</td>';
          echo '</tr>';
        }
        echo '</tbody></table>';
      }
    ?>
  </div>
  <div class="boxes">
    <div class="card box box-sms">
      <h3 class="box-heading">SMS Lists</h3>
      <?php renderListTable($sms); ?>
    </div>
    <div class="card box box-email">
      <h3 class="box-heading">Email Lists</h3>
      <?php renderListTable($email); ?>
    </div>
  </div>
  </div>
    </div>
  </div>
</body>
</html>


