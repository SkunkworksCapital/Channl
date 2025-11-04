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
.bell{position:relative;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:9999px;background:#111827;color:#cbd5e1;text-decoration:none}
.bell .dot{position:absolute;top:2px;right:2px;min-width:18px;height:18px;padding:0 6px;border-radius:9999px;background:#ef4444;color:#fff;font-size:12px;display:none;align-items:center;justify-content:center}
.bell .label{display:none}
</style>
<div class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="/">channl</a>
    <div class="nav">
      <?php if (current_user_id()): ?>
        <?php ensure_wallet_tables(); $bal = wallet_get_balance((int)current_user_id()); ?>
        <a href="/billing" title="Credits balance" class="badge" id="balanceBadge"><?= number_format((float)$bal, 4, '.', '') ?> credits</a>
        <a href="#" class="bell" id="notifBell" title="New inbound messages">
          <span aria-hidden="true">🔔</span>
          <span class="dot" id="notifDot">0</span>
          <span class="label">Notifications</span>
        </a>
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
  var bell = document.getElementById('notifBell');
  var dot = document.getElementById('notifDot');
  async function refreshBalance(){
    if(!badge) return;
    try{ const r = await fetch('/api/balance', { cache: 'no-store' }); if(!r.ok) return; const j = await r.json(); if(j && j.ok && typeof j.balance === 'number'){ badge.textContent = j.balance.toFixed(4) + ' credits'; } }catch(e){}
  }
  async function refreshNotifications(){
    if(!dot) return;
    try{ const r = await fetch('/api/notifications/count', { cache: 'no-store' }); if(!r.ok) return; const j = await r.json(); if(j && j.ok && typeof j.count === 'number'){ if(j.count > 0){ dot.textContent = String(j.count); dot.style.display = 'inline-flex'; } else { dot.style.display = 'none'; } } }catch(e){}
  }
  async function markRead(){
    try{ await fetch('/api/notifications/read', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'csrf='+encodeURIComponent('<?= h(csrf_token()) ?>') }); if(dot){ dot.style.display='none'; } }catch(e){}
  }
  if(bell){ bell.addEventListener('click', function(e){ e.preventDefault(); markRead(); }); }
  document.addEventListener('visibilitychange', function(){ if(document.visibilityState === 'visible'){ refreshBalance(); refreshNotifications(); }});
  setInterval(refreshBalance, 10000);
  setInterval(refreshNotifications, 8000);
  refreshBalance();
  refreshNotifications();
})();
</script>


