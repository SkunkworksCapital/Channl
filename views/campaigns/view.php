<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Campaign • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:960px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px 6px;text-align:left}
    a{color:#93c5fd}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="container">
  <div class="card">
    <h2>Campaign #<?= (int)$c['id'] ?> — <?= h($c['name']) ?></h2>
    <p>Channel: <?= h($c['channel']) ?> | Scope: <?= h($c['scope']) ?></p>
    <p>Totals: <?= (int)$c['sent'] ?>/<?= (int)$c['total'] ?> (failed: <?= (int)$c['failed'] ?>)</p>
    <h3>Messages</h3>
    <table>
      <thead>
        <tr>
          <th>To</th>
          <th>Status</th>
          <th>Error</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($msgs as $m): ?>
        <tr>
          <td><?= h($m['to_addr']) ?></td>
          <td><?= h($m['status']) ?></td>
          <td><?= h($m['error'] ?? '') ?></td>
          <td><?= h($m['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><a href="/campaigns">Back</a></p>
  </div>
  </div>
</body>
</html>


