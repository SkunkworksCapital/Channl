<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>List • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:960px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px 6px;text-align:left}
    a{color:#93c5fd}
    input,select{padding:8px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    .btn{display:inline-block;background:#2563eb;color:white;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
    .err{color:#fca5a5}
    .ok{color:#86efac}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>List #<?= (int)$list['id'] ?> — <?= h($list['name']) ?></h2>
    <p class="subtle">Channel: <?= h($list['channel'] ?? 'sms') ?></p>
    <p><?= h($list['description'] ?? '') ?></p>
    <form method="post" action="/lists/<?= (int)$list['id'] ?>/templates" style="margin-bottom:16px">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label>Default templates</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php $channel = $list['channel'] ?? 'sms'; if ($channel === 'sms'): ?>
          <select name="default_sms_template_id">
            <option value="">SMS: None</option>
            <?php
              db()->exec('CREATE TABLE IF NOT EXISTS message_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, type ENUM("sms","email") NOT NULL DEFAULT "sms", subject VARCHAR(255) NULL, body TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
              $t = db()->prepare('SELECT id, name FROM message_templates WHERE user_id = ? AND type = "sms" ORDER BY name');
              $t->execute([current_user_id()]);
              foreach ($t->fetchAll() as $tpl) {
                $sel = isset($list['default_sms_template_id']) && (int)$list['default_sms_template_id'] === (int)$tpl['id'] ? ' selected' : '';
                echo '<option value="' . (int)$tpl['id'] . '"' . $sel . '>' . h($tpl['name']) . '</option>';
              }
            ?>
          </select>
        <?php else: ?>
          <select name="default_email_template_id">
            <option value="">Email: None</option>
            <?php
              $t2 = db()->prepare('SELECT id, name FROM message_templates WHERE user_id = ? AND type = "email" ORDER BY name');
              $t2->execute([current_user_id()]);
              foreach ($t2->fetchAll() as $tpl) {
                $sel = isset($list['default_email_template_id']) && (int)$list['default_email_template_id'] === (int)$tpl['id'] ? ' selected' : '';
                echo '<option value="' . (int)$tpl['id'] . '"' . $sel . '>' . h($tpl['name']) . '</option>';
              }
            ?>
          </select>
        <?php endif; ?>
        <button class="btn" type="submit">Save</button>
      </div>
    </form>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <form method="post" action="/lists/<?= (int)$list['id'] ?>/members">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label>Add contact</label>
      <select name="contact_id">
        <?php
          $stmt = db()->prepare('SELECT id, name, phone, email FROM contacts WHERE user_id = ? ORDER BY id DESC LIMIT 500');
          $stmt->execute([current_user_id()]);
          foreach ($stmt->fetchAll() as $c) {
            $label = ($c['name'] ?: 'No Name') . ' — ' . ($c['phone'] ?: $c['email']);
            echo '<option value="' . (int)$c['id'] . '">' . h($label) . '</option>';
          }
        ?>
      </select>
      <button class="btn" type="submit">Add</button>
    </form>
    <h3>Members</h3>
    <table>
      <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Country</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= (int)$m['id'] ?></td>
          <td><?= h($m['name'] ?? '') ?></td>
          <td><?= h($m['email'] ?? '') ?></td>
          <td><?= h($m['phone'] ?? '') ?></td>
          <td><?= h($m['country'] ?? '') ?></td>
          <td>
            <form method="post" action="/lists/<?= (int)$list['id'] ?>/members/remove" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="contact_id" value="<?= (int)$m['id'] ?>">
              <button class="btn" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><a href="/lists">Back to lists</a></p>
  </div>
  </div>
    </div>
  </div>
</body>
</html>


