<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scheduled Jobs • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:1080px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 8px;border-bottom:1px solid #374151;text-align:left}
    .btn{display:inline-block;background:#2563eb;color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
    .btn-danger{background:#b91c1c}
    .err{color:#fca5a5;margin:0 0 8px}
    .ok{color:#86efac;margin:0 0 8px}
    .subtle{color:#9ca3af;font-size:.9em}
  </style>
  </head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
      <div class="container">
        <div class="card">
          <h2>Scheduled Jobs</h2>
          <p class="subtle">Times shown in <?= h($tz) ?>. Pending jobs can be cancelled before they run.</p>
          <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
          <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>

          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Channel</th>
                <th>Mode</th>
                <th>Scheduled (local)</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($items)): ?>
                <tr><td colspan="7" class="subtle">No scheduled jobs.</td></tr>
              <?php else: foreach ($items as $it): ?>
                <tr>
                  <td>#<?= (int)$it['id'] ?></td>
                  <td><?= h(strtoupper($it['channel'])) ?></td>
                  <td><?= h($it['mode']) ?></td>
                  <td title="UTC <?= h($it['scheduled_at_utc']) ?>"><?= h($it['scheduled_at_local']) ?></td>
                  <td><?= h($it['status']) ?></td>
                  <td><?= (int)$it['attempts'] ?></td>
                  <td>
                    <?php if ($it['status'] === 'pending'): ?>
                      <form method="post" action="/scheduled/<?= (int)$it['id'] ?>/cancel" style="display:inline">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <button class="btn btn-danger" type="submit">Cancel</button>
                      </form>
                    <?php else: ?>
                      <span class="subtle">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if (!empty($it['last_error'])): ?>
                <tr><td></td><td colspan="6" class="subtle">Last error: <?= h($it['last_error']) ?></td></tr>
                <?php endif; ?>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>


