<?php
declare(strict_types=1);

require_once BASE_PATH . '/services/SmsTwilio.php';
require_once BASE_PATH . '/core/scheduler.php';

final class SmsController {
  public static function sendForm(): void {
    self::requireAuth();
    view('sms/send', ['error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function inbox(): void {
    self::requireAuth();
    try {
      $stmt = db()->prepare('SELECT id, from_addr, to_addr, body, provider_message_id, status, created_at FROM messages WHERE user_id = ? AND channel = "sms" AND status = "received" ORDER BY id DESC LIMIT 200');
      $stmt->execute([current_user_id()]);
      $items = $stmt->fetchAll();
    } catch (Throwable $e) {
      $items = [];
    }
    view('sms/inbox', ['items' => $items]);
  }

  public static function twilioInbound(): void {
    // Twilio inbound webhook (POST form-encoded). No auth check; Twilio calls it.
    // Minimize work and always return 200.
    try {
      $from = trim((string)($_POST['From'] ?? ''));
      $to = trim((string)($_POST['To'] ?? ''));
      $body = (string)($_POST['Body'] ?? '');
      $sid = (string)($_POST['MessageSid'] ?? '');

      // Determine user_id by matching contact phone first, fallback to last message to that phone
      $userId = null;
      if ($from !== '') {
        $q1 = db()->prepare('SELECT user_id FROM contacts WHERE phone = ? LIMIT 1');
        $q1->execute([$from]);
        $row = $q1->fetch();
        if ($row && isset($row['user_id'])) { $userId = (int)$row['user_id']; }
      }
      if ($userId === null && $from !== '') {
        $q2 = db()->prepare('SELECT user_id FROM messages WHERE channel = "sms" AND to_addr = ? ORDER BY id DESC LIMIT 1');
        $q2->execute([$from]);
        $row2 = $q2->fetch();
        if ($row2 && isset($row2['user_id'])) { $userId = (int)$row2['user_id']; }
      }
      if ($userId === null) { $userId = 0; }

      // Ensure table exists
      db()->exec('CREATE TABLE IF NOT EXISTS messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        channel ENUM("sms","whatsapp","email") NOT NULL DEFAULT "sms",
        provider VARCHAR(32) NOT NULL DEFAULT "twilio",
        to_addr VARCHAR(64) NOT NULL,
        from_addr VARCHAR(64) NULL,
        body TEXT NOT NULL,
        provider_message_id VARCHAR(128) NULL,
        status VARCHAR(32) NOT NULL DEFAULT "queued",
        price DECIMAL(8,4) NULL,
        currency VARCHAR(8) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_messages_user (user_id),
        KEY ix_messages_status (status),
        KEY ix_messages_created (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

      $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, "received")');
      $stmt->execute([$userId, $to, $from, $body, $sid]);
      audit_log('sms.webhook_received', 'message', null, ['from' => $from, 'to' => $to, 'sid' => $sid]);
    } catch (Throwable $e) {
      // swallow
    }
    header('Content-Type: text/plain');
    echo 'ok';
  }

  public static function syncFromTwilio(): void {
    self::requireAuth();
    $cfg = $GLOBALS['CONFIG']['integrations']['sms'] ?? [];
    $svc = new SmsTwilio($cfg);

    // Build query: fetch recent inbound messages to our number
    $from = (string)($cfg['from'] ?? '');
    $fromClean = trim($from);
    if (strpos($fromClean, 'MG') !== 0) {
      $fromClean = preg_replace('/\s*[#;].*$/', '', $fromClean);
      $fromClean = preg_replace('/\s+.*/', '', $fromClean);
    }
    $ourNumber = $fromClean;

    // Determine since date: last received message time or 7 days ago
    $since = null;
    try {
      $q = db()->prepare('SELECT DATE(created_at) AS d FROM messages WHERE user_id = ? AND channel = "sms" AND status = "received" ORDER BY id DESC LIMIT 1');
      $q->execute([current_user_id()]);
      $row = $q->fetch();
      if ($row && !empty($row['d'])) { $since = (string)$row['d']; }
    } catch (Throwable $e) {}
    if ($since === null) { $since = date('Y-m-d', time() - 7*24*60*60); }

    $query = [ 'PageSize' => 50 ];
    if ($ourNumber !== '' && strpos($ourNumber, 'MG') !== 0) {
      $query['To'] = $ourNumber; // messages sent to our number (i.e., inbound)
    }
    if ($since) { $query['DateSent>='] = $since; }

    $api = $svc->listMessages($query);
    if (!($api['ok'] ?? false)) {
      flash_set('error', 'Failed to fetch from Twilio (HTTP ' . (int)($api['status'] ?? 0) . ').');
      redirect('/sms/inbox');
    }

    $resp = $api['response'] ?? [];
    $messages = isset($resp['messages']) && is_array($resp['messages']) ? $resp['messages'] : [];
    $inserted = 0;
    foreach ($messages as $m) {
      $sid = (string)($m['sid'] ?? '');
      if ($sid === '') continue;
      try {
        // Skip if already present
        $chk = db()->prepare('SELECT id FROM messages WHERE provider_message_id = ? LIMIT 1');
        $chk->execute([$sid]);
        if ($chk->fetch()) continue;

        $dir = (string)($m['direction'] ?? '');
        $fromAddr = (string)($m['from'] ?? '');
        $toAddr = (string)($m['to'] ?? '');
        $body = (string)($m['body'] ?? '');
        $status = strtolower($dir) === 'inbound' ? 'received' : 'sent';
        $price = isset($m['price']) ? (float)$m['price'] : null;
        $currency = isset($m['price_unit']) ? (string)$m['price_unit'] : null;

        $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status, price, currency) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([current_user_id(), $toAddr, $fromAddr, $body, $sid, $status, $price, $currency]);
        $inserted++;
      } catch (Throwable $e) { /* ignore and continue */ }
    }
    flash_set('ok', 'Synced ' . $inserted . ' messages from Twilio.');
    audit_log('sms.sync_completed', 'messages', null, ['inserted' => $inserted, 'since' => $since, 'to' => $ourNumber]);
    redirect('/sms/inbox');
  }

  public static function send(): void {
    self::requireAuth();
    require_csrf_or_400();
    $to = trim((string)($_POST['to'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $listId = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $sendAtLocal = trim((string)($_POST['send_at'] ?? ''));
    $uid = (int)current_user_id();
    $cfg = $GLOBALS['CONFIG']['integrations']['sms'] ?? [];
    $svc = new SmsTwilio($cfg);

    // ensure messages table exists
    db()->exec('CREATE TABLE IF NOT EXISTS messages (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      channel ENUM("sms","whatsapp","email") NOT NULL DEFAULT "sms",
      provider VARCHAR(32) NOT NULL DEFAULT "twilio",
      to_addr VARCHAR(64) NOT NULL,
      from_addr VARCHAR(64) NULL,
      body TEXT NOT NULL,
      provider_message_id VARCHAR(128) NULL,
      status VARCHAR(32) NOT NULL DEFAULT "queued",
      price DECIMAL(8,4) NULL,
      currency VARCHAR(8) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY ix_messages_user (user_id),
      KEY ix_messages_status (status),
      KEY ix_messages_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

    $from = (string)($cfg['from'] ?? '');
    $fromClean = trim($from);
    if (strpos($fromClean, 'MG') !== 0) {
      $fromClean = preg_replace('/\s*[#;].*$/', '', $fromClean);
      $fromClean = preg_replace('/\s+.*/', '', $fromClean);
    }
    $from = $fromClean;

    if ($listId > 0) {
      // If approval required, create approval request and exit early
      $requireApproval = (bool)($GLOBALS['CONFIG']['security']['require_bulk_approval'] ?? false);
      if ($requireApproval) {
        try {
          db()->exec('CREATE TABLE IF NOT EXISTS approvals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(32) NOT NULL,
            payload JSON NOT NULL,
            status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
            requested_by BIGINT UNSIGNED NOT NULL,
            decided_by BIGINT UNSIGNED NULL,
            decided_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ix_approvals_status_created (status, created_at)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
          $payload = json_encode([ 'user_id' => (int)current_user_id(), 'list_id' => $listId, 'body' => $body ], JSON_UNESCAPED_SLASHES);
          db()->prepare('INSERT INTO approvals (type, payload, requested_by) VALUES ("sms_bulk", ?, ?)')->execute([$payload, current_user_id()]);
          audit_log('approval.requested', 'list', $listId, ['type' => 'sms_bulk']);
          flash_set('ok', 'Bulk SMS submitted for approval.');
          redirect('/sms/send');
        } catch (Throwable $e) { /* fallback to direct send below if table creation fails */ }
      }
      // ensure list belongs to user and is SMS channel
      try {
        $chk = db()->prepare('SELECT id FROM contact_lists WHERE id = ? AND user_id = ? AND channel = "sms"');
        $chk->execute([$listId, current_user_id()]);
        if (!$chk->fetch()) { flash_set('error', 'Selected list is not an SMS list.'); redirect('/sms/send'); }
      } catch (Throwable $e) {}
      // If a specific template was chosen, load it
      if ($templateId > 0) {
        try {
          $tq = db()->prepare('SELECT body FROM message_templates WHERE id = ? AND user_id = ? AND type = "sms"');
          $tq->execute([$templateId, current_user_id()]);
          $t = $tq->fetch();
          if ($t && !empty($t['body'])) { $body = (string)$t['body']; }
        } catch (Throwable $e) { /* ignore */ }
      }
      // Otherwise, if still empty, try to load the list's default template
      if ($body === '') {
        try {
          $tplQ = db()->prepare('SELECT mt.body FROM contact_lists l JOIN message_templates mt ON mt.id = l.default_sms_template_id AND mt.user_id = ? AND mt.type = "sms" WHERE l.id = ? AND l.user_id = ?');
          $tplQ->execute([current_user_id(), $listId, current_user_id()]);
          $tplRow = $tplQ->fetch();
          if ($tplRow && !empty($tplRow['body'])) { $body = (string)$tplRow['body']; }
        } catch (Throwable $e) { /* ignore */ }
      }
      if ($body === '') { flash_set('error', 'Please select a template or enter a message.'); redirect('/sms/send'); }

      // Scheduling for bulk list send
      if ($sendAtLocal !== '') {
        ensure_scheduled_jobs_table();
        $tz = user_timezone($uid);
        $utc = convert_local_to_utc($sendAtLocal, $tz);
        if ($utc && strtotime($utc) > time()) {
          $payload = json_encode([ 'list_id' => $listId, 'body' => $body ], JSON_UNESCAPED_SLASHES);
          db()->prepare('INSERT INTO scheduled_jobs (user_id, channel, mode, payload, scheduled_at) VALUES (?, "sms", "list", ?, ?)')
            ->execute([$uid, $payload, $utc]);
          flash_set('ok', 'Bulk SMS scheduled for ' . h($sendAtLocal) . ' (' . h($tz) . ').');
          redirect('/sms/send');
        }
      }
      $q = db()->prepare('SELECT c.id, c.name, c.email, c.phone, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.phone IS NOT NULL');
      $q->execute([$listId]);
      $recipients = $q->fetchAll();
      audit_log('sms.bulk_requested', 'list', $listId, ['recipients' => count($recipients)]);
      // credits check
      $rate = rate_for_channel('sms');
      $cost = $rate * count($recipients);
      if (!wallet_debit((int)current_user_id(), $cost, 'sms_bulk', [ 'list_id' => $listId, 'count' => count($recipients) ])) {
        flash_set('error', 'Insufficient credits. Please top up on Billing.');
        redirect('/billing');
      }
      $sent = 0; $failed = 0;
      foreach ($recipients as $row) {
        $toNum = (string)$row['phone'];
        $name = (string)($row['name'] ?? '');
        $firstName = '';
        if ($name !== '') { $parts = preg_split('/\s+/', trim($name)); if ($parts && isset($parts[0])) $firstName = (string)$parts[0]; }
        $ctx = [
          'name' => $name,
          'first_name' => $firstName,
          'email' => (string)($row['email'] ?? ''),
          'phone' => $toNum,
          'country' => (string)($row['country'] ?? ''),
        ];
        $personalized = render_message_template($body, $ctx);
        $res = $svc->send($toNum, $personalized);
        if (!$res['ok'] && ($res['status'] ?? 0) === 0 && ($res['response'] ?? null) === []) {
          flash_set('error', 'cURL not available on server. Please enable PHP cURL extension.');
          redirect('/sms/send');
        }
        $statusText = $res['ok'] ? 'sent' : 'error';
        $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?)');
        $stmt->execute([current_user_id(), $toNum, $from, $personalized, (string)($res['provider_message_id'] ?? ''), $statusText]);
        if ($res['ok']) $sent++; else $failed++;
      }
      flash_set('ok', 'Bulk send complete. Sent ' . $sent . ', failed ' . $failed . '.');
      audit_log('sms.bulk_sent', 'list', $listId, ['sent' => $sent, 'failed' => $failed]);
      redirect('/sms/send');
    }

    if ($to === '' || $body === '') {
      flash_set('error', 'To and body are required.');
      redirect('/sms/send');
    }
    // Scheduling for single send
    if ($sendAtLocal !== '') {
      ensure_scheduled_jobs_table();
      $tz = user_timezone($uid);
      $utc = convert_local_to_utc($sendAtLocal, $tz);
      if ($utc && strtotime($utc) > time()) {
        $payload = json_encode([ 'to' => $to, 'body' => $body ], JSON_UNESCAPED_SLASHES);
        db()->prepare('INSERT INTO scheduled_jobs (user_id, channel, mode, payload, scheduled_at) VALUES (?, "sms", "single", ?, ?)')
          ->execute([$uid, $payload, $utc]);
        flash_set('ok', 'SMS scheduled for ' . h($sendAtLocal) . ' (' . h($tz) . ').');
        redirect('/sms/send');
      }
    }
    // single send credits
    $rate = rate_for_channel('sms');
    if (!wallet_debit((int)current_user_id(), $rate, 'sms_single', [ 'to' => $to ])) {
      flash_set('error', 'Insufficient credits. Please top up on Billing.');
      redirect('/billing');
    }
    $res = $svc->send($to, $body);
    if (!$res['ok'] && ($res['status'] ?? 0) === 0 && ($res['response'] ?? null) === []) {
      flash_set('error', 'cURL not available on server. Please enable PHP cURL extension.');
      redirect('/sms/send');
    }
    $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?)');
    $statusText = $res['ok'] ? 'sent' : 'error';
    $stmt->execute([current_user_id(), $to, $from, $body, (string)($res['provider_message_id'] ?? ''), $statusText]);

    if ($res['ok']) {
      flash_set('ok', 'SMS sent.');
    } else {
      $code = (int)$res['status'];
      $detail = '';
      if (is_array($res['response']) && isset($res['response']['message'])) {
        $detail = ' ' . (string)$res['response']['message'];
        if (isset($res['response']['code'])) { $detail .= ' (code ' . (string)$res['response']['code'] . ')'; }
      }
      flash_set('error', 'Failed to send (HTTP ' . $code . ').' . $detail);
    }
    audit_log('sms.single_sent', 'message', null, ['to' => $to, 'status' => $statusText]);
    redirect('/sms/send');
  }

  private static function requireAuth(): void {
    if (!current_user_id()) {
      flash_set('error', 'Please login.');
      redirect('/login');
    }
  }
}


