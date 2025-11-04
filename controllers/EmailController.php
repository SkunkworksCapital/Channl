<?php
declare(strict_types=1);

require_once BASE_PATH . '/services/SmtpClient.php';
require_once BASE_PATH . '/services/SendgridClient.php';
require_once BASE_PATH . '/core/scheduler.php';

final class EmailController {
  public static function form(): void {
    self::auth();
    view('email/send', ['error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function send(): void {
    self::auth();
    require_csrf_or_400();
    $to = trim((string)($_POST['to'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $redirectParam = trim((string)($_POST['redirect'] ?? ''));
    $returnTo = ($redirectParam !== '' && $redirectParam[0] === '/') ? $redirectParam : '/email/send';
    $listId = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $sendAtLocal = trim((string)($_POST['send_at'] ?? ''));
    $uid = (int)current_user_id();
    if ($listId === 0 && ($to === '' || $subject === '' || $body === '')) {
      flash_set('error', 'To, subject and body are required (or choose a list).');
      redirect($returnTo);
    }
    $cfg = $GLOBALS['CONFIG']['integrations']['email'] ?? [];
    $mode = (string)($cfg['mode'] ?? 'smtp');
    $provider = $mode === 'sendgrid' ? 'sendgrid' : 'smtp';
    $mailer = $mode === 'sendgrid' ? new SendgridClient($cfg) : new SmtpClient($cfg);

    $from = (string)($cfg['from'] ?? 'no-reply@example.com');
    // ensure messages table exists
    db()->exec('CREATE TABLE IF NOT EXISTS messages (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      channel ENUM("sms","whatsapp","email") NOT NULL DEFAULT "sms",
      provider VARCHAR(32) NOT NULL DEFAULT "smtp",
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
    // Ensure unique key for provider-message id to prevent duplicate inserts
    try { db()->exec('CREATE UNIQUE INDEX uq_messages_provider_msg ON messages (provider, provider_message_id)'); } catch (Throwable $e) {}
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
          $payload = json_encode([ 'user_id' => (int)current_user_id(), 'list_id' => $listId, 'subject' => $subject, 'body' => $body ], JSON_UNESCAPED_SLASHES);
          db()->prepare('INSERT INTO approvals (type, payload, requested_by) VALUES ("email_bulk", ?, ?)')->execute([$payload, current_user_id()]);
          audit_log('approval.requested', 'list', $listId, ['type' => 'email_bulk']);
          flash_set('ok', 'Bulk email submitted for approval.');
          redirect($returnTo);
        } catch (Throwable $e) { /* fallback to direct send below if table creation fails */ }
      }
      // ensure list belongs to user and is Email channel
      try {
        $chk = db()->prepare('SELECT id FROM contact_lists WHERE id = ? AND user_id = ? AND channel = "email"');
        $chk->execute([$listId, current_user_id()]);
        if (!$chk->fetch()) { flash_set('error', 'Selected list is not an Email list.'); redirect($returnTo); }
      } catch (Throwable $e) {}
      // If a specific template was chosen, load it
      if ($templateId > 0) {
        try {
          $tq = db()->prepare('SELECT subject, body FROM message_templates WHERE id = ? AND user_id = ? AND type = "email"');
          $tq->execute([$templateId, current_user_id()]);
          $t = $tq->fetch();
          if ($t) { $subject = trim((string)($t['subject'] ?? $subject)); $body = trim((string)($t['body'] ?? $body)); }
        } catch (Throwable $e) { /* ignore */ }
      }
      // Otherwise, if still empty, try to load the list's default template
      if ($subject === '' || $body === '') {
        try {
          $tplQ = db()->prepare('SELECT mt.subject, mt.body FROM contact_lists l JOIN message_templates mt ON mt.id = l.default_email_template_id AND mt.user_id = ? AND mt.type = "email" WHERE l.id = ? AND l.user_id = ?');
          $tplQ->execute([current_user_id(), $listId, current_user_id()]);
          $tplRow = $tplQ->fetch();
          if ($tplRow) {
            if ($subject === '') $subject = trim((string)($tplRow['subject'] ?? ''));
            if ($body === '') $body = trim((string)($tplRow['body'] ?? ''));
          }
        } catch (Throwable $e) { /* ignore */ }
      }
      if ($subject === '' || $body === '') { flash_set('error', 'Please select a template or enter a subject and body.'); redirect($returnTo); }

      // Scheduling for bulk list email
      if ($sendAtLocal !== '') {
        ensure_scheduled_jobs_table();
        $tz = user_timezone($uid);
        $utc = convert_local_to_utc($sendAtLocal, $tz);
        if ($utc && strtotime($utc) > time()) {
          $payload = json_encode([ 'list_id' => $listId, 'subject' => $subject, 'body' => $body ], JSON_UNESCAPED_SLASHES);
          db()->prepare('INSERT INTO scheduled_jobs (user_id, channel, mode, payload, scheduled_at) VALUES (?, "email", "list", ?, ?)')
            ->execute([$uid, $payload, $utc]);
          flash_set('ok', 'Bulk email scheduled for ' . h($sendAtLocal) . ' (' . h($tz) . ').');
          redirect($returnTo);
        }
      }

      // send to members of the selected list only, with personalization
      $q = db()->prepare('SELECT c.id, c.name, c.email, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.email IS NOT NULL AND c.email <> ""');
      $q->execute([$listId]);
      $recipients = $q->fetchAll();
      audit_log('email.bulk_requested', 'list', $listId, ['recipients' => count($recipients)]);
      // credits check
      $rate = rate_for_channel('email');
      $cost = $rate * count($recipients);
      if (!wallet_debit((int)current_user_id(), $cost, 'email_bulk', [ 'list_id' => $listId, 'count' => count($recipients) ])) {
        flash_set('error', 'Insufficient credits. Please top up on Billing.');
        redirect('/billing');
      }
      $sent = 0; $failed = 0;
      // Ensure message_list_links table exists for analytics by list
      db()->exec('CREATE TABLE IF NOT EXISTS message_list_links (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        list_id BIGINT UNSIGNED NOT NULL,
        message_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_mll_user_list (user_id, list_id),
        KEY ix_mll_message (message_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
      foreach ($recipients as $row) {
        $email = (string)$row['email'];
        $name = (string)($row['name'] ?? '');
        $firstName = '';
        if ($name !== '') { $parts = preg_split('/\s+/', trim($name)); if ($parts && isset($parts[0])) $firstName = (string)$parts[0]; }
        $ctx = [ 'name' => $name, 'first_name' => $firstName, 'email' => $email, 'country' => (string)($row['country'] ?? '') ];
        $subjRendered = render_message_template($subject, $ctx);
        $bodyRendered = render_message_template($body, $ctx);
        $token = bin2hex(random_bytes(16));
        $res = $mailer->send($email, $subjRendered, $bodyRendered, [ 'custom_args' => [ 'channl_msg' => $token ], 'open_tracking' => true ]);
        $statusText = $res['ok'] ? 'sent' : 'error';
        $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), created_at = created_at');
        $stmt->execute([current_user_id(), $provider, $email, $from, $subjRendered . "\n\n" . $bodyRendered, $token, $statusText]);
        try {
          $msgId = (int)db()->lastInsertId();
          if ($msgId > 0) {
            db()->prepare('INSERT INTO message_list_links (user_id, list_id, message_id) VALUES (?, ?, ?)')
              ->execute([ (int)current_user_id(), $listId, $msgId ]);
          }
        } catch (Throwable $e) { /* ignore link errors */ }
        if ($res['ok']) $sent++; else $failed++;
      }
      flash_set('ok', 'Bulk email complete. Sent ' . $sent . ', failed ' . $failed . '.');
      audit_log('email.bulk_sent', 'list', $listId, ['sent' => $sent, 'failed' => $failed]);
      redirect('/email/send');
    }

    // single
    $rate = rate_for_channel('email');
    if (!wallet_debit((int)current_user_id(), $rate, 'email_single', [ 'to' => $to ])) {
      flash_set('error', 'Insufficient credits. Please top up on Billing.');
      redirect('/billing');
    }
    // Scheduling for single email
    if ($sendAtLocal !== '') {
      ensure_scheduled_jobs_table();
      $tz = user_timezone($uid);
      $utc = convert_local_to_utc($sendAtLocal, $tz);
      if ($utc && strtotime($utc) > time()) {
        $payload = json_encode([ 'to' => $to, 'subject' => $subject, 'body' => $body ], JSON_UNESCAPED_SLASHES);
        db()->prepare('INSERT INTO scheduled_jobs (user_id, channel, mode, payload, scheduled_at) VALUES (?, "email", "single", ?, ?)')
          ->execute([$uid, $payload, $utc]);
        flash_set('ok', 'Email scheduled for ' . h($sendAtLocal) . ' (' . h($tz) . ').');
        redirect($returnTo);
      }
    }

    // Personalize for single recipient if contact exists
    try {
      $ctx = [ 'name' => '', 'first_name' => '', 'email' => $to, 'country' => '' ];
      $q = db()->prepare('SELECT name, phone, country FROM contacts WHERE user_id = ? AND email = ? LIMIT 1');
      $q->execute([current_user_id(), $to]);
      $row = $q->fetch();
      if ($row) {
        $ctx['name'] = (string)($row['name'] ?? '');
        if ($ctx['name'] !== '') { $parts = preg_split('/\s+/', trim($ctx['name'])); if ($parts && isset($parts[0])) $ctx['first_name'] = (string)$parts[0]; }
        $ctx['country'] = (string)($row['country'] ?? '');
        if (!isset($ctx['phone']) && isset($row['phone'])) { $ctx['phone'] = (string)$row['phone']; }
      }
      $subject = render_message_template($subject, $ctx);
      $body = render_message_template($body, $ctx);
    } catch (Throwable $e) { /* best effort */ }

    $token = bin2hex(random_bytes(16));
    $res = $mailer->send($to, $subject, $body, [ 'custom_args' => [ 'channl_msg' => $token ], 'open_tracking' => true ]);
    $statusText = $res['ok'] ? 'sent' : 'error';
    $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), created_at = created_at');
    $stmt->execute([current_user_id(), $provider, $to, $from, $subject . "\n\n" . $body, $token, $statusText]);
    if ($res['ok']) {
      flash_set('ok', 'Email sent.');
    } else {
      flash_set('error', 'Email failed: ' . ($res['error'] ?? 'unknown'));
    }
    $statusText = $res['ok'] ? 'sent' : 'error';
    audit_log('email.single_sent', 'message', null, ['to' => $to, 'status' => $statusText]);
    redirect($returnTo);
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }

  public static function inbox(): void {
    self::auth();
    try {
      // Best-effort columns for opens
      try { db()->exec('ALTER TABLE messages ADD COLUMN opens INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
      try { db()->exec('ALTER TABLE messages ADD COLUMN last_opened_at DATETIME NULL'); } catch (Throwable $e) {}
      $stmt = db()->prepare('SELECT id, from_addr, to_addr, body, provider_message_id, status, opens, last_opened_at, created_at FROM messages WHERE user_id = ? AND channel = "email" ORDER BY id DESC LIMIT 200');
      $stmt->execute([current_user_id()]);
      $items = $stmt->fetchAll();
    } catch (Throwable $e) {
      $items = [];
    }
    view('email/inbox', ['items' => $items]);
  }

  // SendGrid Inbound Parse webhook (POST multipart/form-data). No auth; SG calls it.
  public static function sendgridInbound(): void {
    // Always 200 quickly
    try {
      $from = trim((string)($_POST['from'] ?? ''));
      $to = trim((string)($_POST['to'] ?? ''));
      $subject = (string)($_POST['subject'] ?? '');
      $text = (string)($_POST['text'] ?? '');
      $html = (string)($_POST['html'] ?? '');
      $headers = (string)($_POST['headers'] ?? '');
      $envelope = (string)($_POST['envelope'] ?? '');

      // Helper to extract clean email address from common formats
      $extractEmail = function(string $v): string {
        $s = trim($v);
        if ($s === '') return '';
        if (preg_match('/<([^>]+)>/', $s, $m)) { $s = trim((string)$m[1]); }
        $s = strtolower($s);
        return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
      };
      // Prefer envelope fields when available
      if ($envelope !== '') {
        $env = json_decode($envelope, true);
        if (is_array($env)) {
          if (empty($from)) { $from = (string)($env['from'] ?? $from); }
          if (empty($to)) {
            $envTo = $env['to'] ?? [];
            if (is_array($envTo) && !empty($envTo)) { $to = (string)$envTo[0]; }
          }
        }
      }
      $fromEmail = $extractEmail($from);
      $toEmail = $extractEmail($to);

      // Try to extract Message-ID from headers
      $providerId = null;
      if ($headers !== '') {
        if (preg_match('/^Message-Id:\s*<([^>]+)>/im', $headers, $m)) {
          $providerId = (string)$m[1];
        } elseif (preg_match('/^Message-ID:\s*<([^>]+)>/im', $headers, $m)) {
          $providerId = (string)$m[1];
        }
      }

      // Determine user_id
      $userId = null;
      // 1) If the inbound address encodes the user (reply+<uid>@), trust it
      if ($toEmail !== '' && preg_match('/^reply\+(\d+)@/i', $toEmail, $mm)) {
        $userId = (int)$mm[1];
      }
      // 2) Else try sender contact match
      if ($fromEmail !== '') {
        try {
          $q1 = db()->prepare('SELECT user_id FROM contacts WHERE email = ? LIMIT 1');
          $q1->execute([$fromEmail]);
          $row = $q1->fetch();
          if ($row && isset($row['user_id'])) { $userId = (int)$row['user_id']; }
        } catch (Throwable $e) {}
      }
      // 3) Else last outbound sent to this sender
      if ($userId === null && $fromEmail !== '') {
        try {
          $q2 = db()->prepare('SELECT user_id FROM messages WHERE channel = "email" AND to_addr = ? ORDER BY id DESC LIMIT 1');
          $q2->execute([$fromEmail]);
          $row2 = $q2->fetch();
          if ($row2 && isset($row2['user_id'])) { $userId = (int)$row2['user_id']; }
        } catch (Throwable $e) {}
      }
      if ($userId === null) { $userId = 0; }

      // Ensure table exists
      db()->exec('CREATE TABLE IF NOT EXISTS messages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        channel ENUM("sms","whatsapp","email") NOT NULL DEFAULT "sms",
        provider VARCHAR(32) NOT NULL DEFAULT "smtp",
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

      if ($text === '' && $html !== '') {
        $text = trim(strip_tags($html));
      }
      $body = ($subject !== '' ? ($subject . "\n\n") : '') . $text;
      $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", "sendgrid", ?, ?, ?, ?, "received")');
      $stmt->execute([$userId, $toEmail !== '' ? $toEmail : $to, $fromEmail !== '' ? $fromEmail : $from, $body, $providerId]);
      audit_log('email.webhook_received', 'message', null, ['from' => $from, 'to' => $to, 'provider' => 'sendgrid']);
    } catch (Throwable $e) {
      // swallow
    }
    header('Content-Type: text/plain');
    echo 'ok';
  }

  // SendGrid Event Webhook (application/json) for deliveries, opens, etc.
  public static function sendgridEvents(): void {
    // Respond 200 quickly
    $raw = file_get_contents('php://input');
    try {
      $events = json_decode((string)$raw, true);
      if (!is_array($events)) { $events = []; }
      // Ensure events/logging tables/columns
      db()->exec('CREATE TABLE IF NOT EXISTS message_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        message_id VARCHAR(64) NULL,
        channel ENUM("sms","whatsapp","email") NOT NULL,
        provider VARCHAR(64) NOT NULL,
        event_type VARCHAR(64) NOT NULL,
        status_code INT NULL,
        request_json JSON NULL,
        response_json JSON NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_events_channel_created (channel, created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
      // Add optional columns for convenience
      try { db()->exec('ALTER TABLE messages ADD COLUMN opens INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
      try { db()->exec('ALTER TABLE messages ADD COLUMN last_opened_at DATETIME NULL'); } catch (Throwable $e) {}

      foreach ($events as $ev) {
        $type = (string)($ev['event'] ?? '');
        $custom = isset($ev['custom_args']) && is_array($ev['custom_args']) ? $ev['custom_args'] : [];
        $token = (string)($custom['channl_msg'] ?? '');
        $ts = isset($ev['timestamp']) ? (int)$ev['timestamp'] : null;
        $when = $ts ? gmdate('Y-m-d H:i:s', $ts) : gmdate('Y-m-d H:i:s');

        if ($type === 'open' && $token !== '') {
          try {
            $u = db()->prepare('UPDATE messages SET opens = opens + 1, last_opened_at = COALESCE(?, last_opened_at) WHERE provider_message_id = ? AND channel = "email"');
            $u->execute([$when, $token]);
          } catch (Throwable $e) {}
        }
        // Log all events
        try {
          $stmt = db()->prepare('INSERT INTO message_events (message_id, channel, provider, event_type, status_code, request_json, response_json) VALUES (?, "email", "sendgrid", ?, NULL, ?, NULL)');
          $stmt->execute([$token !== '' ? $token : null, $type, json_encode($ev, JSON_UNESCAPED_SLASHES)]);
        } catch (Throwable $e) {}
      }
    } catch (Throwable $e) {
      // swallow
    }
    header('Content-Type: text/plain');
    echo 'ok';
  }
}


