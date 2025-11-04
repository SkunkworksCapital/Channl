<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contacts • channl</title>
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
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>Contacts</h2>
    <p>
      <a href="/contacts/new" class="btn" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:6px 10px;margin-right:6px;text-decoration:none">Add Contact</a>
      <a href="/contacts/upload">Upload CSV</a>
    </p>
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Country</th>
          <th>Tags</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($contacts as $c): ?>
        <tr>
          <td><?= (int)$c['id'] ?></td>
          <td><?= h($c['name'] ?? '') ?></td>
          <td><?= h($c['email'] ?? '') ?></td>
          <td><?= h($c['phone'] ?? '') ?></td>
          <td><?= h($c['country'] ?? '') ?></td>
          <td>
            <?php $tags = $c['tags'] ? json_decode($c['tags'], true) : []; if (is_array($tags)) echo h(implode(', ', $tags)); ?>
          </td>
          <td><?= h($c['created_at']) ?></td>
          <td style="text-align:right">
            <a href="/contacts/<?= (int)$c['id'] ?>" class="btn" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:6px 10px;margin-right:6px;text-decoration:none">View</a>
            <a href="/contacts/<?= (int)$c['id'] ?>/edit" class="btn" style="background:#334155;color:#fff;border:none;border-radius:8px;padding:6px 10px;margin-right:6px;text-decoration:none">Edit</a>
            <form method="post" action="/contacts/<?= (int)$c['id'] ?>/delete" onsubmit="return confirm('Delete this contact?');" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <button class="btn" type="submit" style="background:#ef4444;color:#fff;border:none;border-radius:8px;padding:6px 10px;cursor:pointer">Delete</button>
            </form>
          </td>
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


