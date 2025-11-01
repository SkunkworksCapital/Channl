<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Send Email • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#0b0c10;color:#e5e7eb;margin:0}
    .card{max-width:960px;margin:2rem auto;background:#111827;border:1px solid #374151;border-radius:12px;padding:24px}
    input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    label{display:block;margin:12px 0 6px}
    .btn{display:inline-block;margin-top:12px;background:#2563eb;color:white;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
    .err{color:#fca5a5;margin:0 0 8px}
    .ok{color:#86efac;margin:0 0 8px}
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
    <h2>Send Email</h2>
    <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
    <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>

    <?php
      db()->exec('CREATE TABLE IF NOT EXISTS message_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, type ENUM("sms","email") NOT NULL DEFAULT "sms", subject VARCHAR(255) NULL, body TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
      try { db()->exec('ALTER TABLE contact_lists ADD COLUMN channel ENUM("sms","email") NOT NULL DEFAULT "sms" AFTER user_id'); } catch (Throwable $e) {}
      $stmt = db()->prepare('SELECT l.id, l.name, mt.name AS tpl_name, mt.subject AS tpl_subject, mt.body AS tpl_body FROM contact_lists l LEFT JOIN message_templates mt ON mt.id = l.default_email_template_id AND mt.user_id = ? AND mt.type = "email" WHERE l.user_id = ? AND l.channel = "email" ORDER BY l.name');
      $stmt->execute([current_user_id(), current_user_id()]);
      $listRows = $stmt->fetchAll();
      $listTplMap = [];
      foreach ($listRows as $l) { if (!empty($l['tpl_body']) || !empty($l['tpl_subject'])) { $listTplMap[(int)$l['id']] = ['name' => $l['tpl_name'], 'subject' => $l['tpl_subject'], 'body' => $l['tpl_body']]; } }
      $tplStmt = db()->prepare('SELECT id, name, subject, body FROM message_templates WHERE user_id = ? AND type = "email" ORDER BY name');
      $tplStmt->execute([current_user_id()]);
      $tplRows = $tplStmt->fetchAll();
      $tplMap = [];
      foreach ($tplRows as $t) { $tplMap[(int)$t['id']] = ['name' => $t['name'], 'subject' => $t['subject'], 'body' => $t['body']]; }
    ?>

    <h3>Single test email</h3>
    <form method="post" action="/email/send" style="margin-bottom:24px">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <div id="toRow">
        <label for="to">To (email)</label>
        <input id="to" name="to" type="email" required>
      </div>
      <label for="subject_test">Subject</label>
      <input id="subject_test" name="subject" type="text" required>
      <label for="body_test">Message</label>
      <textarea id="body_test" name="body" rows="6" required></textarea>
      <button class="btn" type="submit">Send test</button>
    </form>

    <h3>Send from template to a list</h3>
    <form method="post" action="/email/send">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label for="list_id">List</label>
      <div style="display:flex;gap:8px;align-items:center">
      <select id="list_id" name="list_id" required style="flex:1">
        <option value="">-- Select list --</option>
        <?php foreach ($listRows as $l) { echo '<option value="' . (int)$l['id'] . '\">' . h($l['name']) . '</option>'; } ?>
      </select>
      <button type="button" class="btn" id="previewListBtn">Preview recipients</button>
      </div>
      <label for="template_id">Template</label>
      <select id="template_id" name="template_id">
        <option value="">-- Select template (optional) --</option>
        <?php foreach ($tplRows as $t) { echo '<option value="' . (int)$t['id'] . '\">' . h($t['name']) . '</option>'; } ?>
      </select>
      <div id="tplPreview" class="subtle" style="margin:8px 0 0 0;display:none"></div>
      <label for="subject_bulk">Subject</label>
      <input id="subject_bulk" name="subject" type="text" required readonly>
      <label for="body_bulk">Message</label>
      <textarea id="body_bulk" name="body" rows="6" required readonly></textarea>
      <button class="btn" type="submit">Send to list</button>
      <p class="subtle">Tip: Selecting a template will populate subject and message. If none is selected, the list's default template is used when available.</p>
    </form>
  </div>
  <script>
    (function(){
      var list = document.getElementById('list_id');
      var preview = document.getElementById('tplPreview');
      var templateSel = document.getElementById('template_id');
      var subjBulk = document.getElementById('subject_bulk');
      var bodyBulk = document.getElementById('body_bulk');
      var previewBtn = document.getElementById('previewListBtn');
      var listTplMap = <?php echo json_encode($listTplMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      var tplMap = <?php echo json_encode($tplMap ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      function sync(){
        var tpl = null;
        if (templateSel && templateSel.value) { tpl = tplMap[templateSel.value] || tplMap[parseInt(templateSel.value,10)]; }
        if (!tpl && list && list.value) { tpl = listTplMap[list.value] || listTplMap[parseInt(list.value,10)]; }
        if (tpl){ if (subjBulk) subjBulk.value = tpl.subject || ''; if (bodyBulk) bodyBulk.value = tpl.body || ''; }
        if (tpl && preview){ preview.style.display='block'; preview.textContent = 'Using template: ' + (tpl.name || ''); } else { if (preview){ preview.style.display='none'; preview.textContent=''; } }
      }
      async function fetchTemplate(id){ try{ const res = await fetch('/templates/' + id + '/json'); if(!res.ok) return; const data = await res.json(); if(data && data.ok && data.template){ subjBulk.value = data.template.subject || ''; bodyBulk.value = data.template.body || ''; } }catch(e){} }
      if(list){ list.addEventListener('change', function(){ sync(); }); }
      if(templateSel){ templateSel.addEventListener('change', function(){ var v = templateSel.value; if(v){ fetchTemplate(v); } sync(); }); }
      if(previewBtn){ previewBtn.addEventListener('click', async function(){
        if(!list || !list.value){ alert('Select a list first.'); return; }
        const id = list.value;
        try{
          const res = await fetch('/lists/' + id + '/members.json');
          const data = await res.json();
          showRecipientsModal(data && data.members ? data.members : [], data && typeof data.count==='number' ? data.count : null);
        }catch(e){ showRecipientsModal([], null); }
      }); }

      function showRecipientsModal(members, total){
        var overlay = document.createElement('div');
        overlay.style.position='fixed'; overlay.style.left='0'; overlay.style.top='0'; overlay.style.right='0'; overlay.style.bottom='0'; overlay.style.background='rgba(0,0,0,0.6)'; overlay.style.zIndex='1000';
        var modal = document.createElement('div');
        modal.style.maxWidth='720px'; modal.style.margin='60px auto'; modal.style.background='#111827'; modal.style.border='1px solid #374151'; modal.style.borderRadius='12px'; modal.style.padding='16px';
        var h = document.createElement('h3'); h.textContent='Recipients'; modal.appendChild(h);
        var p = document.createElement('p'); p.className='subtle'; p.textContent = (total!=null?('Total: '+total+'. Showing up to '+members.length+'.') : ('Showing '+members.length+'.')); modal.appendChild(p);
        var table = document.createElement('table'); table.style.width='100%'; table.style.borderCollapse='collapse';
        var thead = document.createElement('thead'); var trh=document.createElement('tr'); ['Name','Email','Phone','Country'].forEach(function(t){ var th=document.createElement('th'); th.textContent=t; th.style.borderBottom='1px solid #374151'; th.style.textAlign='left'; th.style.padding='8px 6px'; trh.appendChild(th); }); thead.appendChild(trh); table.appendChild(thead);
        var tbody = document.createElement('tbody');
        members.forEach(function(m){ var tr=document.createElement('tr'); ['name','email','phone','country'].forEach(function(k){ var td=document.createElement('td'); td.textContent = (m && m[k]) ? m[k] : ''; td.style.borderBottom='1px solid #374151'; td.style.padding='8px 6px'; tr.appendChild(td); }); tbody.appendChild(tr); });
        table.appendChild(tbody); modal.appendChild(table);
        var close = document.createElement('button'); close.className='btn'; close.textContent='Close'; close.style.marginTop='12px'; close.onclick=function(){ document.body.removeChild(overlay); };
        modal.appendChild(close);
        overlay.appendChild(modal);
        overlay.addEventListener('click', function(e){ if(e.target===overlay){ document.body.removeChild(overlay); } });
        document.body.appendChild(overlay);
      }
      sync();
    })();
  </script>
  </div>
    </div>
  </div>
</body>
</html>


