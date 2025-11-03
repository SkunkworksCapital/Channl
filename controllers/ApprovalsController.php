<?php
declare(strict_types=1);

require_once BASE_PATH . '/services/SmsTwilio.php';
require_once BASE_PATH . '/services/SmtpClient.php';
require_once BASE_PATH . '/services/SendgridClient.php';

final class ApprovalsController {
  private static function ensureTable(): void {
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
  }

  public static function index(): void {
    self::authAdmin();
    self::ensureTable();
    $stmt = db()->query('SELECT id, type, status, requested_by, decided_by, decided_at, created_at, payload FROM approvals ORDER BY id DESC LIMIT 200');
    $items = $stmt->fetchAll() ?: [];
    view('approvals/index', [ 'items' => $items, 'error' => flash_get('error'), 'ok' => flash_get('ok') ]);
  }

  public static function approve(int $id): void {
    self::authAdmin();
    require_csrf_or_400();
    self::ensureTable();
    $q = db()->prepare('SELECT id, type, status, payload, requested_by FROM approvals WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $row = $q->fetch();
    if (!$row || $row['status'] !== 'pending') { flash_set('error', 'Approval not found or not pending.'); redirect('/approvals'); }
    $payload = json_decode((string)$row['payload'], true) ?: [];
    $type = (string)$row['type'];
    $result = [ 'ok' => false, 'sent' => 0, 'failed' => 0 ];
    try {
      if ($type === 'sms_bulk') {
        $result = self::processSmsBulk($payload);
      } elseif ($type === 'email_bulk') {
        $result = self::processEmailBulk($payload);
      } else {
        throw new RuntimeException('Unsupported approval type');
      }
      db()->prepare('UPDATE approvals SET status = "approved", decided_by = ?, decided_at = NOW() WHERE id = ?')->execute([current_user_id(), $id]);
      audit_log('approval.approved', 'approval', $id, ['type' => $type, 'sent' => $result['sent'] ?? 0, 'failed' => $result['failed'] ?? 0]);
      flash_set('ok', 'Approved and processed. Sent ' . ($result['sent'] ?? 0) . ', failed ' . ($result['failed'] ?? 0) . '.');
    } catch (Throwable $e) {
      flash_set('error', 'Processing failed: ' . $e->getMessage());
    }
    redirect('/approvals');
  }

  public static function reject(int $id): void {
    self::authAdmin();
    require_csrf_or_400();
    self::ensureTable();
    db()->prepare('UPDATE approvals SET status = "rejected", decided_by = ?, decided_at = NOW() WHERE id = ? AND status = "pending"')->execute([current_user_id(), $id]);
    audit_log('approval.rejected', 'approval', $id);
    flash_set('ok', 'Approval rejected.');
    redirect('/approvals');
  }

  private static function processSmsBulk(array $payload): array {
    $userId = (int)($payload['user_id'] ?? 0);
    $listId = (int)($payload['list_id'] ?? 0);
    $body = (string)($payload['body'] ?? '');
    $cfg = $GLOBALS['CONFIG']['integrations']['sms'] ?? [];
    $svc = new SmsTwilio($cfg);
    $from = trim((string)($cfg['from'] ?? ''));
    if (strpos($from, 'MG') !== 0) {
      $from = preg_replace('/\s*[#;].*$/', '', $from);
      $from = preg_replace('/\s+.*/', '', $from);
    }
    $q = db()->prepare('SELECT c.id, c.name, c.email, c.phone, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.phone IS NOT NULL');
    $q->execute([$listId]);
    $recipients = $q->fetchAll();
    $rate = rate_for_channel('sms');
    $cost = $rate * count($recipients);
    if (!wallet_debit($userId, $cost, 'sms_bulk', [ 'list_id' => $listId, 'count' => count($recipients) ])) {
      throw new RuntimeException('Insufficient credits.');
    }
    $sent = 0; $failed = 0;
    foreach ($recipients as $row) {
      $toNum = (string)$row['phone'];
      $name = (string)($row['name'] ?? '');
      $firstName = '';
      if ($name !== '') { $parts = preg_split('/\s+/', trim($name)); if ($parts && isset($parts[0])) $firstName = (string)$parts[0]; }
      $ctx = [ 'name' => $name, 'first_name' => $firstName, 'email' => (string)($row['email'] ?? ''), 'phone' => $toNum, 'country' => (string)($row['country'] ?? '') ];
      $personalized = render_message_template($body, $ctx);
      $res = $svc->send($toNum, $personalized);
      $statusText = $res['ok'] ? 'sent' : 'error';
      $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?)');
      $stmt->execute([$userId, $toNum, $from, $personalized, (string)($res['provider_message_id'] ?? ''), $statusText]);
      if ($res['ok']) $sent++; else $failed++;
    }
    return [ 'ok' => true, 'sent' => $sent, 'failed' => $failed ];
  }

  private static function processEmailBulk(array $payload): array {
    $userId = (int)($payload['user_id'] ?? 0);
    $listId = (int)($payload['list_id'] ?? 0);
    $subject = (string)($payload['subject'] ?? '');
    $body = (string)($payload['body'] ?? '');
    $cfg = $GLOBALS['CONFIG']['integrations']['email'] ?? [];
    $mode = (string)($cfg['mode'] ?? 'smtp');
    $provider = $mode === 'sendgrid' ? 'sendgrid' : 'smtp';
    $mailer = $mode === 'sendgrid' ? new SendgridClient($cfg) : new SmtpClient($cfg);
    $from = (string)($cfg['from'] ?? 'no-reply@example.com');
    $q = db()->prepare('SELECT c.id, c.name, c.email, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.email IS NOT NULL AND c.email <> ""');
    $q->execute([$listId]);
    $recipients = $q->fetchAll();
    $rate = rate_for_channel('email');
    $cost = $rate * count($recipients);
    if (!wallet_debit($userId, $cost, 'email_bulk', [ 'list_id' => $listId, 'count' => count($recipients) ])) {
      throw new RuntimeException('Insufficient credits.');
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
      $stmt->execute([$userId, $provider, $email, $from, $subjRendered . "\n\n" . $bodyRendered, $statusText]);
      if ($res['ok']) $sent++; else $failed++;
    }
    return [ 'ok' => true, 'sent' => $sent, 'failed' => $failed ];
  }

  private static function authAdmin(): void {
    if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); }
    if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }
  }
}

?>

