<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Billing • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:1200px;margin:16px auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
    .bundle{background:#0f172a;border:1px solid #374151;border-radius:16px;padding:18px;position:relative}
    .bundle.popular{border-color:#f472b6}
    .ribbon{position:absolute;top:12px;right:-8px;background:#f472b6;color:#0b0c10;padding:4px 10px;border-radius:9999px;font-weight:700;font-size:12px}
    .title{font-size:22px;font-weight:700;margin:0 0 8px 0}
    .price{font-size:20px;font-weight:800;margin:0 0 4px 0}
    .price .was{color:#fca5a5;text-decoration:line-through;font-weight:600;margin-right:6px}
    .incl{color:#cbd5e1;margin:0 0 12px 0}
    ul.features{list-style:none;padding:0;margin:0 0 12px 0}
    ul.features li{display:flex;align-items:center;gap:8px;margin:8px 0}
    .tick{display:inline-flex;width:22px;height:22px;border-radius:9999px;border:2px solid #86efac;align-items:center;justify-content:center;color:#86efac;font-weight:700}
    .btn{display:inline-block;background:#2563eb;color:#fff;border:none;border-radius:9999px;padding:10px 14px;cursor:pointer;text-decoration:none;font-weight:700}
    .badge{display:inline-block;background:#2563eb;color:#fff;border-radius:9999px;padding:2px 8px;font-size:12px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #374151;padding:8px 6px;text-align:left}
    .muted{color:#9ca3af}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
  <div class="card">
    <h2>Billing</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
    <p>Balance: <span class="badge"><?= number_format((float)$balance, 4) ?> credits</span></p>
    <h3>Pricing by bundle</h3>
    <div class="grid">
      <div class="bundle">
        <div class="title">Deliver</div>
        <div class="price"><span class="was">£29.95</span> / month</div>
        <p class="incl">500 messages</p>
        <ul class="features">
          <li><span class="tick">✓</span> Unlimited Phone and Chat Support</li>
          <li><span class="tick">✓</span> Unlimited Contacts</li>
          <li><span class="tick">✓</span> API Access</li>
          <li><span class="tick">✓</span> Virtual Mobile Number</li>
          <li><span class="tick">✓</span> Reporting</li>
        </ul>
        <a class="btn" href="/billing/buy?package=deliver">BUY NOW</a>
      </div>
      <div class="bundle popular">
        <div class="ribbon">Most Popular!</div>
        <div class="title">Grow</div>
        <div class="price"><span class="was">£100.50</span> / month</div>
        <p class="incl">2000 messages</p>
        <ul class="features">
          <li><span class="tick">✓</span> Unlimited Phone and Chat support</li>
          <li><span class="tick">✓</span> Unlimited contacts</li>
          <li><span class="tick">✓</span> API access</li>
          <li><span class="tick">✓</span> Virtual Mobile Number</li>
          <li><span class="tick">✓</span> Reporting</li>
          <li><span class="tick">✓</span> SMS Analytics</li>
          <li><span class="tick">✓</span> Auto Top-up</li>
        </ul>
        <a class="btn" href="/billing/buy?package=grow">BUY NOW</a>
      </div>
      <div class="bundle">
        <div class="title">Expand</div>
        <div class="price"><span class="was">£296</span> / month</div>
        <p class="incl">6000 messages</p>
        <ul class="features">
          <li><span class="tick">✓</span> Unlimited Phone and Chat Support</li>
          <li><span class="tick">✓</span> Unlimited Contacts</li>
          <li><span class="tick">✓</span> API Access</li>
          <li><span class="tick">✓</span> Virtual Mobile Number</li>
          <li><span class="tick">✓</span> Reporting</li>
          <li><span class="tick">✓</span> SMS Analytics</li>
          <li><span class="tick">✓</span> Auto Top-up</li>
        </ul>
        <a class="btn" href="/billing/buy?package=expand">BUY NOW</a>
      </div>
      <div class="bundle">
        <div class="title">Scale</div>
        <div class="price">However many messages you need</div>
        <p class="incl">A Custom Bundle</p>
        <ul class="features">
          <li><span class="tick">✓</span> Looking for a larger bundle?</li>
          <li><span class="tick">✓</span> No problem, build your own perfect bundle.</li>
        </ul>
        <a class="btn" href="mailto:sales@yourbrand.com">Contact Us</a>
      </div>
    </div>

    <h3 style="margin-top:16px">Recent activity</h3>
    <table>
      <thead><tr><th>When</th><th>Type</th><th>Amount</th><th>Reason</th></tr></thead>
      <tbody>
        <?php foreach (($tx ?? []) as $t): ?>
          <tr>
            <td class="muted"><?= h($t['created_at']) ?></td>
            <td><?= h($t['type']) ?></td>
            <td><?= h(number_format((float)$t['amount'], 4)) ?></td>
            <td><?= h($t['reason'] ?? '') ?></td>
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



