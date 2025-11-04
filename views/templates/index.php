<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Templates • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  <style>
    /* Page-local tweaks to ensure controls match dark theme and spacing */
    .card{max-width:1200px;margin:16px auto}
    input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb}
    .btn-group{display:flex;gap:12px;flex-wrap:wrap}
    .btn-group select{min-width:200px}
  </style>
</head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
    <div class="card">
      <h2 class="heading">Templates</h2>
      <?php if (!empty($error)): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
      <?php if (!empty($ok)): ?><p class="ok"><?= h($ok) ?></p><?php endif; ?>
      <form method="post" action="/templates">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="btn-group">
          <input style="flex:1" name="name" placeholder="Template name" required>
        </div>
        <p></p>
        <div class="btn-group">
          <?php if (!empty($filterType)): ?>
            <input type="hidden" name="type" value="<?= h($filterType) ?>">
            <?php if ($filterType === 'email'): ?>
              <input name="subject" placeholder="Subject (email only)" required>
            <?php else: ?>
              <input name="subject" placeholder="Subject (email only)">
            <?php endif; ?>
          <?php else: ?>
            <select name="type" style="width:200px">
              <option value="sms">SMS</option>
              <option value="email">Email</option>
            </select>
            <input name="subject" placeholder="Subject (email only)">
          <?php endif; ?>
        </div>
        <p></p>
        <textarea name="body" rows="4" style="width:100%" placeholder="Your message text…" required></textarea>
        <p></p>
        <button class="btn btn-primary" type="submit">Save Template</button>
      </form>
      <p class="subtle" style="margin-top:8px">Personalization tags you can use in body/subject: {{name}}, {{first_name}}, {{email}}, {{phone}}, {{country}}. Tags are replaced from each contact when sending.</p>
      <h3>Saved <?= !empty($filterType) ? strtoupper($filterType) : '' ?></h3>
      <table class="table table-striped">
        <thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Subject</th><th>Preview</th><th>Created</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($items as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td><?= h($t['name']) ?></td>
              <td><?= h($t['type']) ?></td>
              <td><?= h($t['subject'] ?? '') ?></td>
              <td><?= h(mb_strimwidth($t['body'], 0, 80, '…')) ?></td>
              <td><?= h($t['created_at']) ?></td>
              <td style="text-align:right">
                <a class="btn" href="/templates/<?= (int)$t['id'] ?>/edit">Edit</a>
                <form method="post" action="/templates/<?= (int)$t['id'] ?>/delete" onsubmit="return confirm('Delete this template?');" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <button class="btn" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!empty($filterType) && ($filterType === 'sms' || $filterType === 'email')): ?>
        <h3 style="margin-top:24px">Library</h3>
        <form method="post" action="/templates/import" class="btn-group" style="align-items:flex-start">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="library_type" value="<?= h($filterType) ?>">
          <select name="library_slug" style="min-width:280px">
            <option value="">-- Choose a library <?= h(strtoupper($filterType)) ?> template --</option>
            <?php foreach (($library ?? []) as $lib) { 
              $label = $lib['name'];
              if ($filterType === 'email' && !empty($lib['subject'])) { $label .= ' — Subject: ' . $lib['subject']; }
              echo '<option value="' . h($lib['slug']) . '">' . h($label) . '</option>'; 
            } ?>
          </select>
          <button class="btn btn-primary" type="submit">Import</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
    </div>
  </div>
</body>
</html>


