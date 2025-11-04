<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Template • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    .card{max-width:1200px;margin:16px auto}
    input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    .btn-group{display:flex;gap:12px;flex-wrap:wrap}
    .btn-group select{min-width:200px}
    .err{color:#fca5a5;margin:0 0 8px}
    .ok{color:#86efac;margin:0 0 8px}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
    <div class="card">
      <h2 class="heading">Edit Template</h2>
      <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
      <form method="post" action="/templates/<?= (int)$tpl['id'] ?>">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="btn-group">
          <input style="flex:1" name="name" placeholder="Template name" required value="<?= h($tpl['name']) ?>">
        </div>
        <p></p>
        <div class="btn-group">
          <select name="type" id="tplType" style="width:200px">
            <option value="sms" <?= ($tpl['type']==='sms'?'selected':'') ?>>SMS</option>
            <option value="email" <?= ($tpl['type']==='email'?'selected':'') ?>>Email</option>
          </select>
          <input id="tplSubject" name="subject" placeholder="Subject (email only)" value="<?= h($tpl['subject'] ?? '') ?>">
        </div>
        <p></p>
        <textarea name="body" rows="8" style="width:100%" placeholder="Your message text…" required><?= h($tpl['body']) ?></textarea>
        <p class="subtle" style="margin-top:8px">Personalization tags available: {{name}}, {{first_name}}, {{email}}, {{phone}}, {{country}}.</p>
        <p></p>
        <div class="btn-group">
          <button class="btn btn-primary" type="submit">Save Changes</button>
          <a class="btn" href="/templates<?= ($tpl['type']==='email'?'/email':($tpl['type']==='sms'?'/sms':'')) ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
    </div>
  </div>
  <script>
    (function(){
      var typeSel = document.getElementById('tplType');
      var subj = document.getElementById('tplSubject');
      function sync(){ if(!typeSel||!subj) return; var isEmail = typeSel.value === 'email'; subj.style.display = isEmail ? '' : 'none'; if(!isEmail){ subj.value = subj.value; } }
      if(typeSel){ typeSel.addEventListener('change', sync); }
      sync();
    })();
  </script>
</body>
</html>


