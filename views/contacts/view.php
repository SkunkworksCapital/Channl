<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contact • channl</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:960px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    .muted{color:#9ca3af}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px 6px;text-align:left;vertical-align:top}
    .btn{display:inline-block;background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;cursor:pointer;text-decoration:none}
    a{color:#93c5fd}
    .pill{display:inline-block;padding:2px 8px;border-radius:9999px;font-size:12px}
    .pill.sent{background:#065f46}
    .pill.error{background:#7f1d1d}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <p><a href="/contacts">← Back to Contacts</a></p>
    <h2>Contact</h2>
    <p>
      <strong><?= h($contact['name'] ?? '') ?></strong><br>
      <?php if (!empty($contact['email'])): ?>Email: <?= h($contact['email']) ?><br><?php endif; ?>
      <?php if (!empty($contact['phone'])): ?>Phone: <?= h($contact['phone']) ?><br><?php endif; ?>
      <?php if (!empty($contact['country'])): ?>Country: <?= h($contact['country']) ?><br><?php endif; ?>
      <span class="muted">Created: <?= h($contact['created_at']) ?></span>
    </p>
    <p>
      <a class="btn" href="/sms/send">Send SMS</a>
      <a class="btn" href="/email/send">Send Email</a>
    </p>

    <h3>Messages</h3>
    <?php if (empty($messages)): ?>
      <p class="muted">No messages found for this contact.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>When</th>
            <th>Direction</th>
            <th>Status</th>
            <th>From</th>
            <th>To</th>
            <th>Body</th>
            <th>Provider ID</th>
            <th>Price</th>
          </tr>
        </thead>
        <tbody>
        <?php $p = $contact['phone'] ?? null; foreach ($messages as $m): ?>
          <?php $direction = ($p && isset($m['to_addr']) && $m['to_addr'] === $p) ? 'Outbound' : 'Inbound'; ?>
          <tr>
            <td><?= h($m['created_at']) ?></td>
            <td><?= h($direction) ?></td>
            <td>
              <?php $st = strtolower((string)$m['status']); $cls = $st === 'sent' ? 'sent' : ($st === 'error' ? 'error' : ''); ?>
              <span class="pill <?= h($cls) ?>"><?= h($m['status']) ?></span>
            </td>
            <td><?= h($m['from_addr'] ?? '') ?></td>
            <td><?= h($m['to_addr'] ?? '') ?></td>
            <td><?= nl2br(h($m['body'] ?? '')) ?></td>
            <td class="muted" style="font-size:12px;"><?= h($m['provider_message_id'] ?? '') ?></td>
            <td><?= isset($m['price']) ? h((string)$m['price'] . ($m['currency'] ? ' ' . $m['currency'] : '')) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  </div>
    </div>
  </div>
</body>
</html>



