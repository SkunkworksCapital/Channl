<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  </head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
    <div class="card">
      <div class="hero">
        <h1 class="heading">channl</h1>
        <p class="subtle">Communicate • Connect</p>
        <?php if (current_user_id() && isset($stats) && is_array($stats)): ?>
          <div class="btn-group" style="gap:16px;flex-wrap:wrap">
            <div class="card" style="padding:16px;min-width:220px">
              <h3 class="heading" style="margin:0 0 8px 0">Contacts</h3>
              <p style="font-size:28px;margin:0"><?= (int)$stats['contacts'] ?></p>
            </div>
            <div class="card" style="padding:16px;min-width:220px">
              <h3 class="heading" style="margin:0 0 8px 0">Lists</h3>
              <p style="font-size:28px;margin:0"><?= (int)$stats['lists'] ?></p>
            </div>
            <div class="card" style="padding:16px;min-width:220px">
              <h3 class="heading" style="margin:0 0 8px 0">All Messages</h3>
              <p style="margin:0">Total: <?= (int)$stats['messages']['total'] ?></p>
              <p style="margin:0;color:#86efac">Sent: <?= (int)$stats['messages']['sent'] ?></p>
              <p style="margin:0;color:#fca5a5">Failed: <?= (int)$stats['messages']['failed'] ?></p>
            </div>
            <div class="card" style="padding:16px;min-width:220px">
              <h3 class="heading" style="margin:0 0 8px 0">SMS</h3>
              <p style="margin:0;color:#86efac">Sent: <?= (int)($stats['sms']['sent'] ?? 0) ?></p>
              <p style="margin:0;color:#fca5a5">Failed: <?= (int)($stats['sms']['failed'] ?? 0) ?></p>
            </div>
            <div class="card" style="padding:16px;min-width:220px">
              <h3 class="heading" style="margin:0 0 8px 0">Email</h3>
              <p style="margin:0;color:#86efac">Sent: <?= (int)($stats['email']['sent'] ?? 0) ?></p>
              <p style="margin:0;color:#fca5a5">Failed: <?= (int)($stats['email']['failed'] ?? 0) ?></p>
            </div>
          </div>
        <?php else: ?>
          <div class="btn-group">
            <a class="btn btn-primary" href="/login">Login</a>
            <a class="btn btn-secondary" href="/register">Register</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
    </div>
  </div>
</body>
</html>


