<?php
// Simple top navigation bar shared across pages
?>
<link rel="stylesheet" href="/assets/app.css">
<style>
.topbar{position:sticky;top:0;z-index:50;background:#0f172a;border-bottom:1px solid #374151}
.topbar-inner{max-width:1200px;margin:0 auto;padding:10px 16px;display:flex;align-items:center;gap:16px}
.brand{color:#e5e7eb;text-decoration:none;font-weight:700;letter-spacing:.5px}
.nav{display:flex;gap:12px;margin-left:auto}
.nav a{color:#93c5fd;text-decoration:none}
.nav form{display:inline}
.nav button{background:#ef4444;color:#fff;border:none;border-radius:8px;padding:6px 10px;cursor:pointer}
</style>
<div class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="/">channl</a>
    <div class="nav">
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


