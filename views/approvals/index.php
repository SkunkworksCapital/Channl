<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Approvals • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:1000px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px;text-align:left;vertical-align:top}
    .btn{background:#2563eb;color:white;padding:6px 10px;border-radius:8px;border:none;cursor:pointer}
    .btn.danger{background:#ef4444}
    .actions{display:flex;gap:8px}
    .muted{color:#9ca3af}
    pre{white-space:pre-wrap;word-break:break-word;background:#0f172a;border:1px solid #374151;border-radius:8px;padding:8px}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
      <div class="card">
        <h2>Approvals</h2>
        <?php if (!empty($error)): ?><p style="color:#fca5a5"><?= h($error) ?></p><?php endif; ?>
        <?php if (!empty($ok)): ?><p style="color:#86efac"><?= h($ok) ?></p><?php endif; ?>
        <table>
          <thead>
            <tr><th>ID</th><th>Type</th><th>Status</th><th>Requested By</th><th>Created</th><th>Payload</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= (int)$it['id'] ?></td>
              <td><?= h($it['type']) ?></td>
              <td><?= h($it['status']) ?></td>
              <td><?= (int)$it['requested_by'] ?></td>
              <td class="muted"><?= h($it['created_at']) ?></td>
              <td><pre><?= h($it['payload']) ?></pre></td>
              <td>
                <?php if ($it['status'] === 'pending'): ?>
                  <div class="actions">
                    <form method="post" action="/approvals/<?= (int)$it['id'] ?>/approve">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <button class="btn" type="submit">Approve</button>
                    </form>
                    <form method="post" action="/approvals/<?= (int)$it['id'] ?>/reject">
                      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                      <button class="btn danger" type="submit">Reject</button>
                    </form>
                  </div>
                <?php else: ?>
                  <span class="muted">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>

