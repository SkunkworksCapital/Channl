<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Channl • Connect • Clean • Communicate</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    :root{--bg:#0b0c10;--panel:#111827;--text:#e5e7eb;--muted:#9ca3af;--primary:#2563eb;--accent:#f472b6;--lime:#84cc16}
    body{background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0}
    .hero{max-width:1200px;margin:0 auto;padding:64px 24px;display:grid;grid-template-columns:1.1fr .9fr;gap:24px}
    .h-title{font-size:52px;line-height:1.05;margin:0 0 12px 0;font-weight:900;letter-spacing:.3px}
    .h-sub{color:var(--muted);font-size:18px;margin:0 0 20px 0}
    .cta{display:flex;gap:12px;flex-wrap:wrap}
    .btn{display:inline-block;background:var(--primary);color:#fff;border:none;border-radius:12px;padding:12px 16px;text-decoration:none;font-weight:700}
    .btn.secondary{background:transparent;color:#93c5fd;border:1px solid #334155}
    .hero-card{background:linear-gradient(135deg,#0f172a,#111827);border:1px solid #1f2937;border-radius:16px;padding:18px}
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}
    .metric{background:#0f172a;border:1px solid #1f2937;border-radius:12px;padding:14px;text-align:center}
    .metric b{font-size:24px}
    .section{max-width:1200px;margin:0 auto;padding:40px 24px}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
    .card{background:var(--panel);border:1px solid #374151;border-radius:14px;padding:18px}
    .card h3{margin:0 0 8px 0}
    .tick{display:inline-flex;width:22px;height:22px;border-radius:9999px;border:2px solid var(--lime);align-items:center;justify-content:center;color:var(--lime);font-weight:700;margin-right:8px}
    .strip{background:#0f172a;border-top:1px solid #1f2937;border-bottom:1px solid #1f2937}
    .footer{max-width:1200px;margin:0 auto;padding:24px;color:var(--muted);text-align:center}
  </style>
</head>
<body>
  <div class="hero">
    <div>
      <h1 class="h-title">Turn conversations into trust, at scale.</h1>
      <p class="h-sub">Channl is the ultra‑simple way to message customers by SMS and Email — with templates, lists, and a unified inbox. Built for regulated teams that care about deliverability, security and speed.</p>
      <div class="cta">
        <a class="btn" href="/register">Start Free</a>
        <a class="btn secondary" href="/login">Sign in</a>
      </div>
      <div class="metrics">
        <div class="metric"><b>3 min</b><br><span class="muted">to first message</span></div>
        <div class="metric"><b>99.9%</b><br><span class="muted">infrastructure uptime</span></div>
        <div class="metric"><b>Bank‑ready</b><br><span class="muted">privacy & audit trail</span></div>
      </div>
    </div>
    <div class="hero-card">
      <div class="cards" style="grid-template-columns:1fr;gap:10px">
        <div class="card" style="background:#0b1220;border-color:#22314a">
          <h3>Why teams switch to Channl</h3>
          <p><span class="tick">✓</span>Send 1:1 or to lists in seconds</p>
          <p><span class="tick">✓</span>ALL UK Networks under the hood</p>
          <p><span class="tick">✓</span>Personalization with {{name}} & more</p>
          <p><span class="tick">✓</span>Opt‑out & quiet hours friendly</p>
        </div>
        <div class="card" style="background:#0b1220;border-color:#22314a">
          <h3>Built for trust</h3>
          <p><span class="tick">✓</span>Per‑message ledger & audit log</p>
          <p><span class="tick">✓</span>Data stays in your region</p>
        </div>
      </div>
    </div>
  </div>

  <div class="section strip">
    <div class="section" style="padding:32px 24px">
      <h2 class="heading">What you get</h2>
      <div class="cards">
        <div class="card"><h3>Contacts & Lists</h3><p>Import CSVs, tag customers, and keep clean lists by channel.</p></div>
        <div class="card"><h3>Templates that deliver</h3><p>Library of carrier‑friendly SMS, plus your own SMS & Email templates with variables.</p></div>
        <div class="card"><h3>Unified Inbox</h3><p>See replies across channels; search, filter and respond fast.</p></div>
        <div class="card"><h3>One‑click Compliance</h3><p>STOP handling, consent ledger and exportable audit events.</p></div>
      </div>
    </div>
  </div>

  <div class="section">
    <h2 class="heading">Pricing that scales with you</h2>
    <p class="subtle">Start free. Top up credits as you go. No contracts.</p>
    <div class="cards">
      <div class="card"><h3>Deliver</h3><p>500 messages • £29.95</p><a class="btn" href="/billing">Get started</a></div>
      <div class="card" style="border-color:#f472b6"><h3>Grow</h3><p>2000 messages • £100.50</p><a class="btn" href="/billing">Most popular</a></div>
      <div class="card"><h3>Expand</h3><p>6000 messages • £296</p><a class="btn" href="/billing">Scale up</a></div>
    </div>
  </div>

  <div class="section strip">
    <div class="section" style="padding:32px 24px">
      <h2 class="heading">Ready to connect?</h2>
      <p class="subtle">Spin up your workspace in minutes and send your first message before your coffee cools.</p>
      <div class="cta">
        <a class="btn" href="/register">Create your account</a>
        <a class="btn secondary" href="/login">I already have one</a>
      </div>
    </div>
  </div>

  <div class="footer">© <?= date('Y') ?> channl · Built for secure messaging</div>
</body>
</html>



