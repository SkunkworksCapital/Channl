<?php
declare(strict_types=1);

require_once BASE_PATH . '/controllers/SmsController.php';

final class CampaignsController {
  public static function index(): void {
    self::auth();
    $stmt = db()->prepare('SELECT id, name, channel, total, sent, failed, created_at FROM campaigns WHERE user_id = ? ORDER BY id DESC LIMIT 100');
    $stmt->execute([current_user_id()]);
    view('campaigns/index', ['items' => $stmt->fetchAll()]);
  }

  public static function new(): void {
    self::auth();
    self::ensureListTables();
    view('campaigns/new', ['error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function create(): void {
    self::auth();
    require_csrf_or_400();
    $name = trim((string)($_POST['name'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $scope = (string)($_POST['scope'] ?? 'all');
    $listId = isset($_POST['list_id']) ? (int)$_POST['list_id'] : 0;
    $tags = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? '')))));
    if ($templateId > 0 && $body === '') {
      // if a template is chosen and body is empty, load template
      db()->exec('CREATE TABLE IF NOT EXISTS sms_templates (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id BIGINT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_templates_user_name (user_id, name), KEY ix_templates_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
      $t = db()->prepare('SELECT body FROM sms_templates WHERE id = ? AND user_id = ?');
      $t->execute([$templateId, $userId]);
      $rowT = $t->fetch();
      if ($rowT && !empty($rowT['body'])) {
        $body = (string)$rowT['body'];
      }
    }
    if ($name === '' || $body === '') {
      flash_set('error', 'Name and body required.');
      redirect('/campaigns/new');
    }

    $userId = current_user_id();
    $targets = [];
    if ($scope === 'list' && $listId > 0) {
      $stmt = db()->prepare('SELECT c.id, c.phone FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? AND c.phone IS NOT NULL');
      $stmt->execute([$listId]);
      $targets = $stmt->fetchAll();
    } elseif ($scope === 'tags' && !empty($tags)) {
      $placeholders = implode(',', array_fill(0, count($tags), '?'));
      $sql = 'SELECT id, phone FROM contacts WHERE user_id = ? AND phone IS NOT NULL AND JSON_OVERLAPS(tags, JSON_ARRAY(' . $placeholders . '))';
      $stmt = db()->prepare($sql);
      $params = array_merge([$userId], $tags);
      $stmt->execute($params);
      $targets = $stmt->fetchAll();
    } else {
      $stmt = db()->prepare('SELECT id, phone FROM contacts WHERE user_id = ? AND phone IS NOT NULL');
      $stmt->execute([$userId]);
      $targets = $stmt->fetchAll();
      $scope = 'all';
    }

    $tagsJson = !empty($tags) ? json_encode($tags) : null;
    // Insert JSON as a plain parameter (no CAST) for wider MySQL/MariaDB compatibility
    $stmt = db()->prepare('INSERT INTO campaigns (user_id, name, channel, scope, tags, body, total) VALUES (?, ?, "sms", ?, ?, ?, ?)');
    $stmt->execute([$userId, $name, $scope, $tagsJson, $body, count($targets)]);
    $campaignId = (int)db()->lastInsertId();

    // Send synchronously for MVP; later swap to queue/cron
    $cfg = $GLOBALS['CONFIG']['integrations']['sms'] ?? [];
    $svc = new SmsTwilio($cfg);
    $sent = 0; $failed = 0;
    foreach ($targets as $t) {
      $to = (string)$t['phone'];
      $res = $svc->send($to, $body);
      $status = $res['ok'] ? 'sent' : 'error';
      $err = $res['ok'] ? null : json_encode($res['response']);
      $msgId = null;
      try {
        $ins = db()->prepare('INSERT INTO messages (user_id, channel, provider, to_addr, from_addr, body, provider_message_id, status) VALUES (?, "sms", "twilio", ?, ?, ?, ?, ?)');
        $from = (string)($cfg['from'] ?? '');
        $ins->execute([$userId, $to, $from, $body, (string)($res['provider_message_id'] ?? ''), $status]);
        $msgId = (int)db()->lastInsertId();
      } catch (Throwable $e) {}
      $cm = db()->prepare('INSERT INTO campaign_messages (campaign_id, contact_id, message_id, to_addr, status, error) VALUES (?, ?, ?, ?, ?, ?)');
      $cm->execute([$campaignId, (int)$t['id'], $msgId, $to, $status, $err]);
      if ($res['ok']) $sent++; else $failed++;
    }

    db()->prepare('UPDATE campaigns SET sent = ?, failed = ? WHERE id = ?')->execute([$sent, $failed, $campaignId]);
    flash_set('ok', 'Campaign created. Sent ' . $sent . ', failed ' . $failed . '.');
    redirect('/campaigns');
  }

  public static function view(int $id): void {
    self::auth();
    $stmt = db()->prepare('SELECT id, name, channel, scope, body, total, sent, failed, created_at FROM campaigns WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, current_user_id()]);
    $campaign = $stmt->fetch();
    if (!$campaign) { http_response_code(404); echo 'Not Found'; return; }
    $msgs = db()->prepare('SELECT to_addr, status, error, created_at FROM campaign_messages WHERE campaign_id = ? ORDER BY id DESC LIMIT 500');
    $msgs->execute([$id]);
    view('campaigns/view', ['c' => $campaign, 'msgs' => $msgs->fetchAll()]);
  }

  private static function auth(): void {
    if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); }
  }

  private static function ensureListTables(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS contact_lists (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      name VARCHAR(255) NOT NULL,
      description VARCHAR(500) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_lists_user_name (user_id, name),
      KEY ix_lists_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    db()->exec('CREATE TABLE IF NOT EXISTS contact_list_members (
      list_id BIGINT UNSIGNED NOT NULL,
      contact_id BIGINT UNSIGNED NOT NULL,
      added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (list_id, contact_id),
      KEY ix_members_contact (contact_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  }
}


