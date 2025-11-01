<?php
declare(strict_types=1);

require_once BASE_PATH . '/services/WhatsappCloud.php';

final class WhatsappController {
  public static function sendForm(): void {
    self::auth();
    view('whatsapp/send', ['error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function send(): void {
    self::auth();
    require_csrf_or_400();
    $to = trim((string)($_POST['to'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    if ($to === '' || $body === '') { flash_set('error', 'To and body are required.'); redirect('/whatsapp/send'); }
    $cfg = $GLOBALS['CONFIG']['integrations']['whatsapp'] ?? [];
    $svc = new WhatsappCloud($cfg);

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

    $res = $svc->sendText($to, $body);
    $statusText = ($res['ok'] ?? false) ? 'sent' : 'error';
    $stmt = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "whatsapp", "meta", ?, NULL, ?, ?, ?)');
    $stmt->execute([current_user_id(), $to, $body, (string)($res['provider_message_id'] ?? ''), $statusText]);

    if ($res['ok']) { flash_set('ok', 'WhatsApp message sent.'); } else { flash_set('error', 'Failed (HTTP ' . (int)($res['status'] ?? 0) . ').'); }
    redirect('/whatsapp/send');
  }

  public static function inbox(): void {
    self::auth();
    try {
      $stmt = db()->prepare('SELECT id, from_addr, to_addr, body, provider_message_id, status, created_at FROM messages WHERE user_id = ? AND channel = "whatsapp" ORDER BY id DESC LIMIT 200');
      $stmt->execute([current_user_id()]);
      $items = $stmt->fetchAll();
    } catch (Throwable $e) { $items = []; }
    view('whatsapp/inbox', ['items' => $items]);
  }

  public static function webhook(): void {
    // Meta webhook for WhatsApp (verification + message notifications)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
      $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
      $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
      $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
      $expected = getenv('WHATSAPP_VERIFY_TOKEN') ?: '';
      if ($mode === 'subscribe' && $token !== '' && $token === $expected) {
        header('Content-Type: text/plain'); echo (string)$challenge; return;
      }
      http_response_code(403); echo 'forbidden'; return;
    }

    // POST notifications
    try {
      $raw = file_get_contents('php://input');
      $data = json_decode($raw, true);
      if (!is_array($data)) $data = [];
      // parse entries
      if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
        $msg = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $from = (string)($msg['from'] ?? '');
        $to = (string)($data['entry'][0]['changes'][0]['value']['metadata']['display_phone_number'] ?? '');
        $type = (string)($msg['type'] ?? '');
        $body = '';
        if ($type === 'text') { $body = (string)($msg['text']['body'] ?? ''); }
        $sid = (string)($msg['id'] ?? '');
        // find user by last outbound to this number
        $userId = null;
        if ($from !== '') {
          $q2 = db()->prepare('SELECT user_id FROM messages WHERE channel = "whatsapp" AND to_addr = ? ORDER BY id DESC LIMIT 1');
          $q2->execute([$from]);
          $row2 = $q2->fetch();
          if ($row2 && isset($row2['user_id'])) { $userId = (int)$row2['user_id']; }
        }
        if ($userId === null) $userId = 0;
        db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "whatsapp", "meta", ?, ?, ?, ?, "received")')
          ->execute([$userId, $to, $from, $body, $sid]);
      }
    } catch (Throwable $e) { /* ignore */ }
    header('Content-Type: text/plain'); echo 'ok';
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }
}



