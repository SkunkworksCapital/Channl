<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Campaigns • channl</title>
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
    <h2>Campaigns</h2>
    <p><a href="/campaigns/new">New Campaign</a></p>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Channel</th>
          <th>Totals</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $c): ?>
        <tr>
          <td><a href="/campaigns/<?= (int)$c['id'] ?>"><?= (int)$c['id'] ?></a></td>
          <td><?= h($c['name']) ?></td>
          <td><?= h($c['channel']) ?></td>
          <td><?= (int)$c['sent'] ?>/<?= (int)$c['total'] ?> (failed: <?= (int)$c['failed'] ?>)</td>
          <td><?= h($c['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  </div>
</body>
</html>


