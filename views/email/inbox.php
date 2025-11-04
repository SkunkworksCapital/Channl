<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Inbox • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:1200px;margin:16px auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px 6px;text-align:left;vertical-align:top}
    .muted{color:#9ca3af}
    pre{white-space:pre-wrap;word-wrap:break-word}
  </style>
  </head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>Email Inbox</h2>
    <table>
      <thead>
        <tr>
          <th>When</th>
          <th>From</th>
          <th>To</th>
          <th>Status</th>
          <th>Opens</th>
          <th>Body</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($items ?? []) as $m): ?>
        <tr>
          <td class="muted"><?= h($m['created_at']) ?></td>
          <td><?= h($m['from_addr'] ?? '') ?></td>
          <td><?= h($m['to_addr'] ?? '') ?></td>
          <td><?= h($m['status'] ?? '') ?></td>
          <td><?= isset($m['opens']) ? (int)$m['opens'] : 0 ?><?= isset($m['last_opened_at']) && $m['last_opened_at'] ? ' • ' . h($m['last_opened_at']) : '' ?></td>
          <td><pre><?= h($m['body'] ?? '') ?></pre></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  </div>
    </div>
  </div>
</body>
</html>



