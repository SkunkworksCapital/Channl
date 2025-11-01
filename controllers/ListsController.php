<?php
declare(strict_types=1);

final class ListsController {
  public static function index(): void {
    self::auth();
    self::ensureTables();
    // Ensure channel column exists
    self::ensureChannelColumn();
    $stmt = db()->prepare('SELECT id, name, description, channel, created_at FROM contact_lists WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([current_user_id()]);
    view('lists/index', ['items' => $stmt->fetchAll(), 'ok' => flash_get('ok'), 'error' => flash_get('error')]);
  }

  public static function create(): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTables();
    self::ensureChannelColumn();
    self::ensureTemplateColumns();
    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $channel = in_array(($_POST['channel'] ?? 'sms'), ['sms','email'], true) ? (string)$_POST['channel'] : 'sms';
    $smsId = isset($_POST['default_sms_template_id']) && $_POST['default_sms_template_id'] !== '' ? (int)$_POST['default_sms_template_id'] : null;
    if ($name === '') { flash_set('error', 'Name required.'); redirect('/lists'); }
    $stmt = db()->prepare('INSERT INTO contact_lists (user_id, name, description, channel) VALUES (?, ?, ?, ?)');
    $stmt->execute([current_user_id(), $name, $desc !== '' ? $desc : null, $channel]);
    $listId = (int)db()->lastInsertId();
    if ($smsId) {
      $q = db()->prepare('SELECT id FROM message_templates WHERE id = ? AND user_id = ? AND type = "sms"');
      $q->execute([$smsId, current_user_id()]);
      if ($q->fetch()) {
        $u = db()->prepare('UPDATE contact_lists SET default_sms_template_id = ? WHERE id = ? AND user_id = ?');
        $u->execute([$smsId, $listId, current_user_id()]);
      }
    }
    flash_set('ok', 'List created.');
    redirect('/lists');
  }

  public static function view(int $id): void {
    self::auth();
    self::ensureTables();
    // Ensure optional template columns exist
    self::ensureTemplateColumns();
    $stmt = db()->prepare('SELECT id, name, description, default_sms_template_id, default_email_template_id FROM contact_lists WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, current_user_id()]);
    $list = $stmt->fetch();
    if (!$list) { http_response_code(404); echo 'Not Found'; return; }
    $members = db()->prepare('SELECT c.id, c.name, c.email, c.phone, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? ORDER BY c.id DESC');
    $members->execute([$id]);
    view('lists/view', ['list' => $list, 'members' => $members->fetchAll(), 'ok' => flash_get('ok'), 'error' => flash_get('error')]);
  }

  public static function addMember(int $id): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTables();
    $contactId = (int)($_POST['contact_id'] ?? 0);
    if ($contactId <= 0) { flash_set('error', 'Select a contact.'); redirect('/lists/' . $id); }
    $stmt = db()->prepare('INSERT IGNORE INTO contact_list_members (list_id, contact_id) VALUES (?, ?)');
    $stmt->execute([$id, $contactId]);
    flash_set('ok', 'Added to list.');
    redirect('/lists/' . $id);
  }

  public static function removeMember(int $id): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTables();
    $contactId = (int)($_POST['contact_id'] ?? 0);
    $stmt = db()->prepare('DELETE FROM contact_list_members WHERE list_id = ? AND contact_id = ?');
    $stmt->execute([$id, $contactId]);
    flash_set('ok', 'Removed.');
    redirect('/lists/' . $id);
  }

  public static function membersJson(int $id): void {
    self::auth();
    self::ensureTables();
    header('Content-Type: application/json');
    try {
      // Ensure the list belongs to the user
      $chk = db()->prepare('SELECT id FROM contact_lists WHERE id = ? AND user_id = ?');
      $chk->execute([$id, current_user_id()]);
      if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        return;
      }
      $q = db()->prepare('SELECT c.id, c.name, c.email, c.phone, c.country FROM contact_list_members m JOIN contacts c ON c.id = m.contact_id WHERE m.list_id = ? ORDER BY c.id DESC LIMIT 500');
      $q->execute([$id]);
      $rows = $q->fetchAll();
      echo json_encode(['ok' => true, 'count' => count($rows), 'members' => $rows]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'server_error']);
    }
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }

  private static function ensureTables(): void {
    // contact_lists
    db()->exec('CREATE TABLE IF NOT EXISTS contact_lists (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      channel ENUM("sms","email") NOT NULL DEFAULT "sms",
      name VARCHAR(255) NOT NULL,
      description VARCHAR(500) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_lists_user_name (user_id, name),
      KEY ix_lists_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    // contact_list_members
    db()->exec('CREATE TABLE IF NOT EXISTS contact_list_members (
      list_id BIGINT UNSIGNED NOT NULL,
      contact_id BIGINT UNSIGNED NOT NULL,
      added_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (list_id, contact_id),
      KEY ix_members_contact (contact_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
  }

  private static function ensureChannelColumn(): void {
    try { db()->exec('ALTER TABLE contact_lists ADD COLUMN channel ENUM("sms","email") NOT NULL DEFAULT "sms" AFTER user_id'); } catch (Throwable $e) {}
  }

  private static function ensureTemplateColumns(): void {
    try { db()->exec('ALTER TABLE contact_lists ADD COLUMN default_sms_template_id BIGINT UNSIGNED NULL AFTER description'); } catch (Throwable $e) {}
    try { db()->exec('ALTER TABLE contact_lists ADD COLUMN default_email_template_id BIGINT UNSIGNED NULL AFTER default_sms_template_id'); } catch (Throwable $e) {}
  }

  public static function setTemplates(int $id): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTables();
    self::ensureTemplateColumns();
    $smsId = isset($_POST['default_sms_template_id']) && $_POST['default_sms_template_id'] !== '' ? (int)$_POST['default_sms_template_id'] : null;
    $emailId = isset($_POST['default_email_template_id']) && $_POST['default_email_template_id'] !== '' ? (int)$_POST['default_email_template_id'] : null;
    if ($smsId) {
      $q = db()->prepare('SELECT id FROM message_templates WHERE id = ? AND user_id = ? AND type = "sms"');
      $q->execute([$smsId, current_user_id()]);
      if (!$q->fetch()) { $smsId = null; }
    }
    if ($emailId) {
      $q = db()->prepare('SELECT id FROM message_templates WHERE id = ? AND user_id = ? AND type = "email"');
      $q->execute([$emailId, current_user_id()]);
      if (!$q->fetch()) { $emailId = null; }
    }
    $u = db()->prepare('UPDATE contact_lists SET default_sms_template_id = ?, default_email_template_id = ? WHERE id = ? AND user_id = ?');
    $u->execute([$smsId, $emailId, $id, current_user_id()]);
    flash_set('ok', 'Templates saved for list.');
    redirect('/lists/' . $id);
  }

  public static function delete(int $id): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTables();
    // remove members first, then the list
    $delM = db()->prepare('DELETE FROM contact_list_members WHERE list_id = ?');
    $delM->execute([$id]);
    $delL = db()->prepare('DELETE FROM contact_lists WHERE id = ? AND user_id = ?');
    $delL->execute([$id, current_user_id()]);
    flash_set('ok', 'List deleted.');
    redirect('/lists');
  }
}


