<?php
declare(strict_types=1);

// CLI worker: process due scheduled jobs once
// Usage: php bin/scheduler.php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/core/bootstrap.php';
require_once BASE_PATH . '/core/scheduler.php';
require_once BASE_PATH . '/services/SmsTwilio.php';
require_once BASE_PATH . '/services/SmtpClient.php';
require_once BASE_PATH . '/services/SendgridClient.php';

ensure_scheduled_jobs_table();

function mark_running(array $job): bool {
  try {
    $u = db()->prepare('UPDATE scheduled_jobs SET status = "running", attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = "pending"');
    $u->execute([$job['id']]);
    return $u->rowCount() > 0;
  } catch (Throwable $e) { return false; }
}

function reschedule_job(int $id, DateTimeImmutable $whenUtc, string $reason): void {
  try {
    db()->prepare('UPDATE scheduled_jobs SET scheduled_at = ?, status = "pending", last_error = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
      ->execute([$whenUtc->format('Y-m-d H:i:s'), $reason, $id]);
  } catch (Throwable $e) {}
}

function complete_job(int $id): void {
  try { db()->prepare('UPDATE scheduled_jobs SET status = "done", updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]); } catch (Throwable $e) {}
}

function fail_job(int $id, string $error): void {
  try { db()->prepare('UPDATE scheduled_jobs SET status = "failed", last_error = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$error, $id]); } catch (Throwable $e) {}
}

// Fetch up to 25 due jobs
$jobs = [];
try {
  $q = db()->query('SELECT id, user_id, channel, mode, payload, scheduled_at FROM scheduled_jobs WHERE status = "pending" AND scheduled_at <= UTC_TIMESTAMP() ORDER BY scheduled_at ASC LIMIT 25');
  $jobs = $q->fetchAll();
} catch (Throwable $e) {}

foreach ($jobs as $job) {
  $id = (int)$job['id'];
  if (!mark_running($job)) { continue; }
  $uid = (int)$job['user_id'];
  $channel = (string)$job['channel'];
  $mode = (string)$job['mode'];
  $payload = json_decode((string)$job['payload'], true) ?: [];

  $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  if (is_within_quiet_hours($uid, $nowUtc)) {
    $next = next_quiet_end_utc($uid, $nowUtc) ?: $nowUtc->modify('+1 hour');
    reschedule_job($id, $next, 'Deferred due to quiet hours');
    continue;
  }
  [$capSms, $capEmail] = user_daily_caps($uid);
  $count = user_sent_count_last_24h($uid, $channel);
  $cap = $channel === 'sms' ? $capSms : ($channel === 'email' ? $capEmail : 0);
  if ($cap > 0 && $count >= $cap) {
    // push to next day at quiet end (or +24h)
    $next = next_quiet_end_utc($uid, $nowUtc) ?: $nowUtc->modify('+24 hours');
    reschedule_job($id, $next, 'Deferred due to daily cap');
    continue;
  }

  try {
    if ($channel === 'sms') {
      $cfg = $GLOBALS['CONFIG']['integrations']['sms'] ?? [];
      $svc = new SmsTwilio($cfg);
      $from = (string)($cfg['from'] ?? '');
      if ($mode === 'single') {
        $to = (string)($payload['to'] ?? '');
        $body = (string)($payload['body'] ?? '');
        if ($to === '' || $body === '') throw new RuntimeException('Missing to/body');
        $rate = rate_for_channel('sms');
        if (!wallet_debit($uid, $rate, 'sms_single_scheduled', [ 'to' => $to ])) { throw new RuntimeException('Insufficient credits'); }
        $res = $svc->send($to, $body);
        $statusText = $res['ok'] ? 'sent' : 'error';
        db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?)')
          ->execute([$uid, $to, $from, $body, (string)($res['provider_message_id'] ?? ''), $statusText]);
        if (!$res['ok']) throw new RuntimeException('Provider error');
      } else if ($mode === 'list') {
        $listId = (int)($payload['list_id'] ?? 0);
        $body = (string)($payload['body'] ?? '');
        if ($listId <= 0 || $body === '') throw new RuntimeException('Missing list/body');
        $q = db()->prepare('SELECT c.name, c.email, c.phone, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.phone IS NOT NULL');
        $q->execute([$listId]);
        $recipients = $q->fetchAll();
        $rate = rate_for_channel('sms');
        $cost = $rate * count($recipients);
        if (!wallet_debit($uid, $cost, 'sms_bulk_scheduled', [ 'list_id' => $listId, 'count' => count($recipients) ])) { throw new RuntimeException('Insufficient credits'); }
        // Ensure link table exists
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
          $toNum = (string)$row['phone'];
          $name = (string)($row['name'] ?? '');
          $first = $name !== '' ? explode(' ', trim($name))[0] : '';
          $ctx = [ 'name' => $name, 'first_name' => $first, 'email' => (string)($row['email'] ?? ''), 'phone' => $toNum, 'country' => (string)($row['country'] ?? '') ];
          $msg = render_message_template($body, $ctx);
          $res = $svc->send($toNum, $msg);
          $statusText = $res['ok'] ? 'sent' : 'error';
          db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?)')
            ->execute([$uid, $toNum, $from, $msg, (string)($res['provider_message_id'] ?? ''), $statusText]);
          try {
            $msgId = (int)db()->lastInsertId();
            if ($msgId > 0) {
              db()->prepare('INSERT INTO message_list_links (user_id, list_id, message_id) VALUES (?, ?, ?)')
                ->execute([ $uid, $listId, $msgId ]);
            }
          } catch (Throwable $e) {}
        }
      }
    } else if ($channel === 'email') {
      $cfg = $GLOBALS['CONFIG']['integrations']['email'] ?? [];
      $modeEmail = (string)($cfg['mode'] ?? 'smtp');
      $provider = $modeEmail === 'sendgrid' ? 'sendgrid' : 'smtp';
      $mailer = $modeEmail === 'sendgrid' ? new SendgridClient($cfg) : new SmtpClient($cfg);
      $from = (string)($cfg['from'] ?? 'no-reply@example.com');
      if ($mode === 'single') {
        $to = (string)($payload['to'] ?? '');
        $subject = (string)($payload['subject'] ?? '');
        $body = (string)($payload['body'] ?? '');
        if ($to === '' || $subject === '' || $body === '') throw new RuntimeException('Missing to/subject/body');
        $rate = rate_for_channel('email');
        if (!wallet_debit($uid, $rate, 'email_single_scheduled', [ 'to' => $to ])) { throw new RuntimeException('Insufficient credits'); }
        $res = $mailer->send($to, $subject, $body);
        $statusText = $res['ok'] ? 'sent' : 'error';
        db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", ?, ?, ?, ?, NULL, ?)')
          ->execute([$uid, $provider, $to, $from, $subject . "\n\n" . $body, $statusText]);
        if (!$res['ok']) throw new RuntimeException('Provider error');
      } else if ($mode === 'list') {
        $listId = (int)($payload['list_id'] ?? 0);
        $subject = (string)($payload['subject'] ?? '');
        $body = (string)($payload['body'] ?? '');
        if ($listId <= 0 || $subject === '' || $body === '') throw new RuntimeException('Missing list/subject/body');
        $q = db()->prepare('SELECT c.name, c.email, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.email IS NOT NULL AND c.email <> ""');
        $q->execute([$listId]);
        $recipients = $q->fetchAll();
        $rate = rate_for_channel('email');
        $cost = $rate * count($recipients);
        if (!wallet_debit($uid, $cost, 'email_bulk_scheduled', [ 'list_id' => $listId, 'count' => count($recipients) ])) { throw new RuntimeException('Insufficient credits'); }
        // Ensure link table exists
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
          $first = $name !== '' ? explode(' ', trim($name))[0] : '';
          $ctx = [ 'name' => $name, 'first_name' => $first, 'email' => $email, 'country' => (string)($row['country'] ?? '') ];
          $subj = render_message_template($subject, $ctx);
          $msg = render_message_template($body, $ctx);
          $res = $mailer->send($email, $subj, $msg);
          $statusText = $res['ok'] ? 'sent' : 'error';
          db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "email", ?, ?, ?, ?, NULL, ?)')
            ->execute([$uid, $provider, $email, $from, $subj . "\n\n" . $msg, $statusText]);
          try {
            $msgId = (int)db()->lastInsertId();
            if ($msgId > 0) {
              db()->prepare('INSERT INTO message_list_links (user_id, list_id, message_id) VALUES (?, ?, ?)')
                ->execute([ $uid, $listId, $msgId ]);
            }
          } catch (Throwable $e) {}
        }
      }
    }
    complete_job($id);
  } catch (Throwable $e) {
    fail_job($id, $e->getMessage());
  }
}

echo "Processed " . count($jobs) . " job(s)\n";


