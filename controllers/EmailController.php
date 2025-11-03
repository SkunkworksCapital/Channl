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
    $listId = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $sendAtLocal = trim((string)($_POST['send_at'] ?? ''));
    $uid = (int)current_user_id();
    if ($listId === 0 && ($to === '' || $subject === '' || $body === '')) {
      flash_set('error', 'To, subject and body are required (or choose a list).');
      redirect('/email/send');
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
          redirect('/email/send');
        } catch (Throwable $e) { /* fallback to direct send below if table creation fails */ }
      }
      // ensure list belongs to user and is Email channel
      try {
        $chk = db()->prepare('SELECT id FROM contact_lists WHERE id = ? AND user_id = ? AND channel = "email"');
        $chk->execute([$listId, current_user_id()]);
        if (!$chk->fetch()) { flash_set('error', 'Selected list is not an Email list.'); redirect('/email/send'); }
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
      if ($subject === '' || $body === '') { flash_set('error', 'Please select a template or enter a subject and body.'); redirect('/email/send'); }

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
          redirect('/email/send');
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
      foreach ($recipients as $row) {
        $email = (string)$row['email'];
        $name = (string)($row['name'] ?? '');
        $firstName = '';
        if ($name !== '') { $parts = preg_split('/\s+/', trim($name)); if ($parts && isset($parts[0])) $firstName = (string)$parts[0]; }
        $ctx = [ 'name' => $name, 'first_name' => $firstName, 'email' => $email, 'country' => (string)($row['country'] ?? '') ];
        $subjRendered = render_message_template($subject, $ctx);
        $bodyRendered = render_message_template($body, $ctx);
        $res = $mailer->send($email, $subjRendered, $bodyRendered);
        $statusText = $res['ok'] ? 'sent' : 'error';
        $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", ?, ?, ?, ?, NULL, ?)');
        $stmt->execute([current_user_id(), $provider, $email, $from, $subjRendered . "\n\n" . $bodyRendered, $statusText]);
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
        redirect('/email/send');
      }
    }

    $res = $mailer->send($to, $subject, $body);
    $statusText = $res['ok'] ? 'sent' : 'error';
    $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", ?, ?, ?, ?, NULL, ?)');
    $stmt->execute([current_user_id(), $provider, $to, $from, $subject . "\n\n" . $body, $statusText]);
    if ($res['ok']) {
      flash_set('ok', 'Email sent.');
    } else {
      flash_set('error', 'Email failed: ' . ($res['error'] ?? 'unknown'));
    }
    $statusText = $res['ok'] ? 'sent' : 'error';
    audit_log('email.single_sent', 'message', null, ['to' => $to, 'status' => $statusText]);
    redirect('/email/send');
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }

  public static function inbox(): void {
    self::auth();
    try {
      $stmt = db()->prepare('SELECT id, from_addr, to_addr, body, provider_message_id, status, created_at FROM messages WHERE user_id = ? AND channel = "email" ORDER BY id DESC LIMIT 200');
      $stmt->execute([current_user_id()]);
      $items = $stmt->fetchAll();
    } catch (Throwable $e) {
      $items = [];
    }
    view('email/inbox', ['items' => $items]);
  }
}


