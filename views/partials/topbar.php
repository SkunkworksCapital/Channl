<?php
// Simple top navigation bar shared across pages
?>
<link rel="stylesheet" href="/assets/app.css">
<style>
.topbar{position:sticky;top:0;z-index:50;background:#0f172a;border-bottom:1px solid #374151}
.topbar-inner{max-width:1200px;margin:0 auto;padding:10px 16px;display:flex;align-items:center;gap:16px}
.brand{color:#e5e7eb;text-decoration:none;font-weight:700;letter-spacing:.5px}
.nav{display:flex;gap:12px;margin-left:auto;align-items:center}
.nav a{color:#93c5fd;text-decoration:none}
.nav form{display:inline}
.nav button{background:#ef4444;color:#fff;border:none;border-radius:8px;padding:6px 10px;cursor:pointer}
.badge{display:inline-block;background:#2563eb;color:#fff;border-radius:9999px;padding:2px 8px;font-size:12px}
</style>
<div class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="/">channl</a>
    <div class="nav">
      <?php if (current_user_id()): ?>
        <?php ensure_wallet_tables(); $bal = wallet_get_balance((int)current_user_id()); ?>
        <a href="/billing" title="Credits balance" class="badge" id="balanceBadge"><?= number_format((float)$bal, 4, '.', '') ?> credits</a>
      <?php endif; ?>
      <?php if (current_user_id()): ?>
        <form method="post" action="/logout">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <button type="submit">Logout</button>
        </form>
      <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
      <?php endif; ?>
    </div>
  </div>
  </div>

<script>
(function(){
  var badge = document.getElementById('balanceBadge');
  if(!badge) return;
  var refreshing = false;
  async function refresh(){
    if(refreshing) return; refreshing = true;
    try{ const r = await fetch('/api/balance', { cache: 'no-store' }); if(!r.ok) return; const j = await r.json(); if(j && j.ok && typeof j.balance === 'number'){ badge.textContent = j.balance.toFixed(4) + ' credits'; } }catch(e){} finally{ refreshing = false; }
  }
  document.addEventListener('visibilitychange', function(){ if(document.visibilityState === 'visible'){ refresh(); }});
  setInterval(refresh, 10000);
  // Trigger an immediate refresh on page load so balance reflects latest debits/credits
  refresh();
})();
</script>


