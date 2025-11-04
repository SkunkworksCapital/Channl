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
    .pill.opened{background:#1d4ed8}
    /* Conversation */
    .thread{display:flex;flex-direction:column;gap:12px;margin-top:12px}
    .bubble{max-width:70%;padding:10px 12px;border-radius:12px;line-height:1.3;white-space:pre-wrap}
    .left{align-self:flex-start;background:#0f172a;border:1px solid #374151}
    .right{align-self:flex-end;background:#1f2937;border:1px solid #374151}
    .meta{font-size:12px;color:#9ca3af;margin-bottom:4px}
    .badge{display:inline-block;font-size:10px;text-transform:uppercase;letter-spacing:.04em;padding:2px 6px;border-radius:9999px;margin-right:6px;background:#334155;color:#cbd5e1}
    .composer{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px}
    .composer form{flex:1;min-width:260px;background:#0f172a;border:1px solid #374151;border-radius:12px;padding:12px}
    .composer textarea,.composer input[type="text"]{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#e5e7eb}
    .composer label{display:block;margin:8px 0 6px}
    .composer h4{margin:0 0 8px 0}
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
    <?php $displayName = trim((string)($contact['name'] ?? '')); if ($displayName === '') { $displayName = !empty($contact['email']) ? (string)$contact['email'] : (!empty($contact['phone']) ? (string)$contact['phone'] : 'contact'); } ?>
    <p>
      <a class="btn" href="#composer-sms">Send SMS to <?= h($displayName) ?></a>
      <a class="btn" href="#composer-email">Send Email to <?= h($displayName) ?></a>
      <a class="btn" href="/contacts/<?= (int)$contact['id'] ?>/edit" style="background:#334155">Edit</a>
    </p>

    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
      <h3 style="margin:0">Conversation</h3>
      <button class="btn" id="toggleConversation" type="button" style="background:#334155">Collapse</button>
    </div>
    <div class="composer">
      <?php if (!empty($contact['phone'])): ?>
      <form id="composer-sms" method="post" action="/sms/send">
        <h4>Send SMS</h4>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="to" value="<?= h($contact['phone']) ?>">
        <input type="hidden" name="redirect" value="/contacts/<?= (int)$contact['id'] ?>">
        <label for="sms_body">Send SMS to <?= h($contact['phone']) ?></label>
        <textarea id="sms_body" name="body" rows="3" placeholder="Type an SMS…"></textarea>
        <div style="margin-top:8px"><button class="btn" type="submit">Send SMS to <?= h($displayName) ?></button></div>
      </form>
      <?php endif; ?>
      <?php if (!empty($contact['email'])): ?>
      <form id="composer-email" method="post" action="/email/send">
        <h4>Send Email</h4>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="to" value="<?= h($contact['email']) ?>">
        <input type="hidden" name="redirect" value="/contacts/<?= (int)$contact['id'] ?>">
        <label for="email_subject">Email subject</label>
        <input id="email_subject" type="text" name="subject" placeholder="Subject">
        <label for="email_body">Email body</label>
        <textarea id="email_body" name="body" rows="4" placeholder="Write your email…"></textarea>
        <div style="margin-top:8px"><button class="btn" type="submit">Send Email to <?= h($displayName) ?></button></div>
      </form>
      <?php endif; ?>
    </div>
    <?php if (empty($messages)): ?>
      <p class="muted">No messages found for this contact.</p>
    <?php else: ?>
      <div class="thread" id="conversationThread">
        <?php $p = $contact['phone'] ?? null; $e = $contact['email'] ?? null; foreach ($messages as $m): ?>
          <?php $isOutbound = (($p && isset($m['to_addr']) && $m['to_addr'] === $p) || ($e && isset($m['to_addr']) && $m['to_addr'] === $e)); ?>
          <div class="bubble <?= $isOutbound ? 'right' : 'left' ?>">
            <div class="meta"><span class="badge"><?= h(strtoupper((string)($m['channel'] ?? ''))) ?></span> <?= h($m['created_at']) ?> • <?= h($isOutbound ? 'Outbound' : 'Inbound') ?> • <span class="pill <?= strtolower((string)$m['status']) === 'sent' ? 'sent' : (strtolower((string)$m['status']) === 'error' ? 'error' : '') ?>"><?= h($m['status']) ?></span><?php if (($m['channel'] ?? '') === 'email' && (int)($m['opens'] ?? 0) > 0): ?> <span class="pill opened">Opened <?= (int)$m['opens'] ?><?= !empty($m['last_opened_at']) ? ' @ ' . h($m['last_opened_at']) : '' ?></span><?php endif; ?></div>
            <div><?= nl2br(h($m['body'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <script>
      (function(){
        var btn = document.getElementById('toggleConversation');
        var thread = document.getElementById('conversationThread');
        if(!btn || !thread) return;
        var key = 'contact:conv:collapsed';
        function setCollapsed(collapsed){
          thread.style.display = collapsed ? 'none' : '';
          btn.textContent = collapsed ? 'Expand' : 'Collapse';
        }
        var saved = localStorage.getItem(key);
        var collapsed = saved === '1';
        setCollapsed(collapsed);
        btn.addEventListener('click', function(){
          collapsed = !collapsed;
          localStorage.setItem(key, collapsed ? '1' : '0');
          setCollapsed(collapsed);
        });
      })();
    </script>
  </div>
  </div>
    </div>
  </div>
</body>
</html>



