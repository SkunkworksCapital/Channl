<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Analytics • channl</title>
  <link rel="stylesheet" href="/assets/app.css">
  </head>
<body>
  <?php require BASE_PATH . '/views/partials/topbar.php'; ?>
  <div class="layout">
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
    <div class="content">
  <div class="container">
    <div class="card">
      <div class="hero">
        <h1 class="heading">Analytics</h1>
        <p class="subtle">Usage across SMS, Email, and more</p>
      </div>
      <?php $s = is_array($summary ?? null) ? $summary : ['overall'=>['total'=>0,'sent'=>0,'failed'=>0,'received'=>0],'channels'=>[],'spend'=>[],'providers'=>[],'daily'=>[],'lists'=>[]]; ?>

      <div class="btn-group" style="gap:16px;flex-wrap:wrap">
        <div class="card" style="padding:16px;min-width:220px">
          <h3 class="heading" style="margin:0 0 8px 0">All Messages</h3>
          <p style="margin:0">Total: <?= (int)$s['overall']['total'] ?></p>
          <p style="margin:0;color:#86efac">Sent: <?= (int)$s['overall']['sent'] ?></p>
          <p style="margin:0;color:#fca5a5">Failed: <?= (int)$s['overall']['failed'] ?></p>
          <p style="margin:0;color:#93c5fd">Replies: <?= (int)$s['overall']['received'] ?></p>
          <?php
            // overall spend across all channels aggregated by currency
            $overallSpend = [];
            foreach (($s['spend'] ?? []) as $ch => $cur) {
              if (!is_array($cur)) continue;
              foreach ($cur as $currency => $amt) {
                $k = (string)$currency;
                if (!isset($overallSpend[$k])) $overallSpend[$k] = 0.0;
                $overallSpend[$k] += (float)$amt;
              }
            }
            if (!empty($overallSpend)) {
              $parts = [];
              foreach ($overallSpend as $cur => $amt) {
                $label = $cur !== '' ? h($cur) . ' ' : '';
                $parts[] = $label . number_format((float)$amt, 4);
              }
              echo '<p class="subtle" style="margin:4px 0 0 0">Spend: ' . implode(' • ', $parts) . '</p>';
            }
          ?>
        </div>
        <?php foreach ($s['channels'] as $ch => $row): ?>
        <div class="card" style="padding:16px;min-width:220px">
          <h3 class="heading" style="margin:0 0 8px 0"><?= h(strtoupper($ch)) ?></h3>
          <p style="margin:0">Total: <?= (int)$row['total'] ?></p>
          <p style="margin:0;color:#86efac">Sent: <?= (int)$row['sent'] ?></p>
          <p style="margin:0;color:#fca5a5">Failed: <?= (int)$row['failed'] ?></p>
          <p style="margin:0;color:#93c5fd">Replies: <?= (int)$row['received'] ?></p>
          <?php $sendable = in_array($ch, ['sms','whatsapp'], true) ? max(1, (int)$row['sent']) : 0; ?>
          <?php if ($sendable > 0): $rr = $row['received'] * 100.0 / $sendable; ?>
            <p class="subtle" style="margin:4px 0 0 0">Reply rate: <?= number_format($rr, 1) ?>%</p>
          <?php endif; ?>
          <?php if (isset($s['spend'][$ch]) && is_array($s['spend'][$ch]) && !empty($s['spend'][$ch])): ?>
            <?php $parts = []; foreach ($s['spend'][$ch] as $cur => $amt) { $label = $cur !== '' ? h($cur) . ' ' : ''; $parts[] = $label . number_format((float)$amt, 4); } ?>
            <p class="subtle" style="margin:4px 0 0 0">Spend: <?= implode(' • ', $parts) ?></p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="grid" style="margin-top:16px">
        <div class="card" style="padding:16px">
          <h3 class="heading" style="margin:0 0 8px 0">Lists Performance</h3>
          <div style="overflow:auto">
            <table class="table">
              <thead>
                <tr>
                  <th>List</th>
                  <th>Channel</th>
                  <th>Sent</th>
                  <th>Failed</th>
                  <th>Replies</th>
                  <th>Total</th>
                  <th>Reply rate</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($s['lists'] as $lid => $info): ?>
                  <?php $name = (string)($info['name'] ?? ('List #'.$lid)); ?>
                  <?php foreach (($info['metrics'] ?? []) as $ch => $m): ?>
                    <?php $sendable = in_array($ch, ['sms','whatsapp'], true) ? max(1, (int)$m['sent']) : 0; ?>
                    <?php $rr = $sendable > 0 ? ($m['replies'] * 100.0 / $sendable) : 0; ?>
                    <tr>
                      <td><?= h($name) ?></td>
                      <td><?= h($ch) ?></td>
                      <td><?= (int)$m['sent'] ?></td>
                      <td><?= (int)$m['failed'] ?></td>
                      <td><?= (int)$m['replies'] ?></td>
                      <td><?= (int)$m['total'] ?></td>
                      <td><?= number_format($rr, 1) ?>%</td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (empty($s['lists'])): ?>
                <tr><td colspan="7" class="subtle">No list activity yet</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="padding:16px">
          <h3 class="heading" style="margin:0 0 8px 0">Channel Breakdown</h3>
          <div style="overflow:auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Channel</th>
                  <th>Sent</th>
                  <th>Failed</th>
                  <th>Replies</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($s['channels'] as $ch => $row): ?>
                <tr>
                  <td><?= h($ch) ?></td>
                  <td><?= (int)$row['sent'] ?></td>
                  <td><?= (int)$row['failed'] ?></td>
                  <td><?= (int)$row['received'] ?></td>
                  <td><?= (int)$row['total'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($s['channels'])): ?>
                <tr><td colspan="5" class="subtle">No data yet</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="padding:16px">
          <h3 class="heading" style="margin:0 0 8px 0">Spend</h3>
          <div style="overflow:auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Channel</th>
                  <th>Currency</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($s['spend'] as $ch => $cur): ?>
                  <?php foreach ($cur as $currency => $amt): ?>
                    <tr>
                      <td><?= h($ch) ?></td>
                      <td><?= $currency !== '' ? h($currency) : '-' ?></td>
                      <td><?= number_format((float)$amt, 4) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (empty($s['spend'])): ?>
                <tr><td colspan="3" class="subtle">No spend recorded</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="padding:16px">
          <h3 class="heading" style="margin:0 0 8px 0">Providers</h3>
          <div style="overflow:auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Channel</th>
                  <th>Provider</th>
                  <th>Messages</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($s['providers'] as $ch => $prov): ?>
                  <?php foreach ($prov as $name => $cnt): ?>
                    <tr>
                      <td><?= h($ch) ?></td>
                      <td><?= h($name) ?></td>
                      <td><?= (int)$cnt ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (empty($s['providers'])): ?>
                <tr><td colspan="3" class="subtle">No provider data</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card" style="padding:16px">
          <h3 class="heading" style="margin:0 0 8px 0">Last 30 Days</h3>
          <div style="overflow:auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Channel</th>
                  <th>Sent</th>
                  <th>Failed</th>
                  <th>Replies</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($s['daily'] as $date => $perCh): ?>
                  <?php foreach ($perCh as $ch => $row): ?>
                    <tr>
                      <td><?= h($date) ?></td>
                      <td><?= h($ch) ?></td>
                      <td><?= (int)$row['sent'] ?></td>
                      <td><?= (int)$row['failed'] ?></td>
                      <td><?= (int)$row['received'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (empty($s['daily'])): ?>
                <tr><td colspan="5" class="subtle">No recent activity</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <p class="subtle" style="margin-top:12px">Notes: Replies reflect inbound messages (e.g., SMS/WhatsApp status = "received"). Email replies are not tracked here.</p>
    </div>
  </div>
    </div>
  </div>
</body>
</html>


