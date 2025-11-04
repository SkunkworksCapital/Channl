<?php
declare(strict_types=1);

final class ApiController {
  public static function balance(): void {
    header('Content-Type: application/json');
    if (!current_user_id()) { http_response_code(401); echo json_encode(['ok' => false]); return; }
    try {
      ensure_wallet_tables();
      $bal = wallet_get_balance((int)current_user_id());
      echo json_encode(['ok' => true, 'balance' => (float)$bal]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false]);
    }
  }

  public static function notificationsCount(): void {
    header('Content-Type: application/json');
    if (!current_user_id()) { http_response_code(401); echo json_encode(['ok' => false]); return; }
    try {
      $uid = (int)current_user_id();
      $since = isset($_SESSION['messages_seen_at']) ? (string)$_SESSION['messages_seen_at'] : null;
      if ($since === null) {
        $_SESSION['messages_seen_at'] = gmdate('Y-m-d H:i:s');
        echo json_encode(['ok' => true, 'count' => 0]);
        return;
      }
      $q = db()->prepare('SELECT COUNT(*) AS c FROM messages WHERE user_id = ? AND status = "received" AND channel IN ("sms","email") AND created_at > ?');
      $q->execute([$uid, $since]);
      $count = (int)($q->fetchColumn() ?: 0);
      echo json_encode(['ok' => true, 'count' => $count]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false]);
    }
  }

  public static function notificationsRecent(): void {
    header('Content-Type: application/json');
    if (!current_user_id()) { http_response_code(401); echo json_encode(['ok' => false]); return; }
    try {
      $uid = (int)current_user_id();
      $sql = 'SELECT m.id, m.channel, m.from_addr, m.to_addr, m.body, m.created_at, c.id AS contact_id
              FROM messages m
              LEFT JOIN contacts c ON c.user_id = m.user_id AND ((m.channel = "sms" AND c.phone = m.from_addr) OR (m.channel = "email" AND c.email = m.from_addr))
              WHERE m.user_id = ? AND m.status = "received" AND m.channel IN ("sms","email")
              ORDER BY m.id DESC LIMIT 10';
      $q = db()->prepare($sql);
      $q->execute([$uid]);
      $rows = $q->fetchAll();
      $items = [];
      foreach ($rows as $r) {
        $body = (string)($r['body'] ?? '');
        $snippet = trim(mb_strimwidth($body, 0, 120, '…'));
        $items[] = [
          'id' => (int)$r['id'],
          'channel' => (string)$r['channel'],
          'from' => (string)($r['from_addr'] ?? ''),
          'to' => (string)($r['to_addr'] ?? ''),
          'snippet' => $snippet,
          'created_at' => (string)$r['created_at'],
          'contact_id' => isset($r['contact_id']) ? (int)$r['contact_id'] : null,
        ];
      }
      echo json_encode(['ok' => true, 'items' => $items]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false]);
    }
  }

  public static function notificationsMarkRead(): void {
    header('Content-Type: application/json');
    if (!current_user_id()) { http_response_code(401); echo json_encode(['ok' => false]); return; }
    try {
      $_SESSION['messages_seen_at'] = gmdate('Y-m-d H:i:s');
      echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false]);
    }
  }
}


