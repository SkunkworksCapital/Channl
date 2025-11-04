<?php
declare(strict_types=1);

final class AnalyticsController {
  public static function index(): void {
    if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); }
    $uid = (int)current_user_id();

    $summary = [
      'overall' => [ 'total' => 0, 'sent' => 0, 'failed' => 0, 'received' => 0 ],
      'channels' => [ /* channel => ['sent'=>, 'failed'=>, 'received'=>, 'total'=>] */ ],
      'spend' => [ /* [channel][currency] => sum */ ],
      'providers' => [ /* [channel][provider] => count */ ],
      'daily' => [ /* date => [channel => ['sent'=>, 'failed'=>, 'received'=>]] */ ],
      'lists' => [ /* list_id => { name, metrics: { channel => { sent, failed, replies, total } }, totals } */ ],
    ];

    try {
      // Overall and per-channel status counts
      $q = db()->prepare('SELECT channel, status, COUNT(*) AS c FROM messages WHERE user_id = ? GROUP BY channel, status');
      $q->execute([$uid]);
      while ($row = $q->fetch()) {
        $channel = (string)$row['channel'];
        $status = (string)$row['status'];
        $count = (int)$row['c'];
        if (!isset($summary['channels'][$channel])) { $summary['channels'][$channel] = [ 'sent' => 0, 'failed' => 0, 'received' => 0, 'total' => 0 ]; }
        if ($status === 'sent') { $summary['channels'][$channel]['sent'] += $count; }
        if ($status === 'error') { $summary['channels'][$channel]['failed'] += $count; }
        if ($status === 'received') { $summary['channels'][$channel]['received'] += $count; }
        $summary['channels'][$channel]['total'] += $count;
        $summary['overall']['total'] += $count;
        if ($status === 'sent') { $summary['overall']['sent'] += $count; }
        if ($status === 'error') { $summary['overall']['failed'] += $count; }
        if ($status === 'received') { $summary['overall']['received'] += $count; }
      }

      // Spend by channel and currency
      $sp = db()->prepare('SELECT channel, currency, SUM(price) AS s FROM messages WHERE user_id = ? AND price IS NOT NULL GROUP BY channel, currency');
      $sp->execute([$uid]);
      while ($row = $sp->fetch()) {
        $channel = (string)$row['channel'];
        $currency = (string)($row['currency'] ?? '');
        $sum = (float)($row['s'] ?? 0);
        if (!isset($summary['spend'][$channel])) { $summary['spend'][$channel] = []; }
        $summary['spend'][$channel][$currency !== '' ? $currency : ''] = $sum;
      }

      // Provider breakdown
      $pv = db()->prepare('SELECT channel, provider, COUNT(*) AS c FROM messages WHERE user_id = ? GROUP BY channel, provider');
      $pv->execute([$uid]);
      while ($row = $pv->fetch()) {
        $channel = (string)$row['channel'];
        $provider = (string)$row['provider'];
        $count = (int)$row['c'];
        if (!isset($summary['providers'][$channel])) { $summary['providers'][$channel] = []; }
        $summary['providers'][$channel][$provider] = $count;
      }

      // 30-day daily trend by channel and status
      $dt = db()->prepare('SELECT DATE(created_at) AS d, channel, status, COUNT(*) AS c FROM messages WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY d, channel, status ORDER BY d ASC');
      $dt->execute([$uid]);
      while ($row = $dt->fetch()) {
        $d = (string)$row['d'];
        $channel = (string)$row['channel'];
        $status = (string)$row['status'];
        $count = (int)$row['c'];
        if (!isset($summary['daily'][$d])) { $summary['daily'][$d] = []; }
        if (!isset($summary['daily'][$d][$channel])) { $summary['daily'][$d][$channel] = [ 'sent' => 0, 'failed' => 0, 'received' => 0 ]; }
        if ($status === 'sent') { $summary['daily'][$d][$channel]['sent'] += $count; }
        if ($status === 'error') { $summary['daily'][$d][$channel]['failed'] += $count; }
        if ($status === 'received') { $summary['daily'][$d][$channel]['received'] += $count; }
      }
    } catch (Throwable $e) {
      // leave summary as defaults on error
    }

    // Per-list performance
    try {
      // Load lists
      $lists = [];
      $ql = db()->prepare('SELECT id, name FROM contact_lists WHERE user_id = ? ORDER BY name ASC');
      $ql->execute([$uid]);
      while ($row = $ql->fetch()) {
        $lists[(int)$row["id"]] = [ 'name' => (string)$row['name'], 'metrics' => [], 'totals' => [ 'sent'=>0, 'failed'=>0, 'replies'=>0, 'total'=>0 ] ];
      }
      if (!empty($lists)) {
        // Sent/failed/total by list via link table
        $qs = db()->prepare('SELECT mll.list_id AS lid, m.channel AS ch, m.status AS st, COUNT(*) AS c FROM message_list_links mll JOIN messages m ON m.id = mll.message_id WHERE m.user_id = ? GROUP BY mll.list_id, m.channel, m.status');
        $qs->execute([$uid]);
        while ($row = $qs->fetch()) {
          $lid = (int)$row['lid'];
          if (!isset($lists[$lid])) continue;
          $ch = (string)$row['ch'];
          $st = (string)$row['st'];
          $c = (int)$row['c'];
          if (!isset($lists[$lid]['metrics'][$ch])) { $lists[$lid]['metrics'][$ch] = [ 'sent'=>0, 'failed'=>0, 'replies'=>0, 'total'=>0 ]; }
          if ($st === 'sent') { $lists[$lid]['metrics'][$ch]['sent'] += $c; $lists[$lid]['totals']['sent'] += $c; }
          if ($st === 'error') { $lists[$lid]['metrics'][$ch]['failed'] += $c; $lists[$lid]['totals']['failed'] += $c; }
          $lists[$lid]['metrics'][$ch]['total'] += $c; $lists[$lid]['totals']['total'] += $c;
        }

        // Replies by list (SMS only; email replies not tracked)
        $in = count($lists) ? implode(',', array_map('intval', array_keys($lists))) : '0';
        $sqlReplies = 'SELECT lm.list_id AS lid, COUNT(*) AS c
                       FROM contact_list_members lm
                       JOIN contacts c ON c.id = lm.contact_id
                       JOIN messages r ON r.user_id = ? AND r.channel = "sms" AND r.status = "received" AND r.from_addr = c.phone
                       WHERE lm.list_id IN (' . $in . ')
                       GROUP BY lm.list_id';
        $qr = db()->prepare($sqlReplies);
        $qr->execute([$uid]);
        while ($row = $qr->fetch()) {
          $lid = (int)$row['lid'];
          $c = (int)$row['c'];
          if (!isset($lists[$lid])) continue;
          if (!isset($lists[$lid]['metrics']['sms'])) { $lists[$lid]['metrics']['sms'] = [ 'sent'=>0, 'failed'=>0, 'replies'=>0, 'total'=>0 ]; }
          $lists[$lid]['metrics']['sms']['replies'] += $c;
          $lists[$lid]['totals']['replies'] += $c;
        }
      }
      $summary['lists'] = $lists;
    } catch (Throwable $e) {
      // ignore per-list errors
    }

    view('analytics/index', [ 'summary' => $summary ]);
  }
}

?>

