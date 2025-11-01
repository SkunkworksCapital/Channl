<?php
declare(strict_types=1);

final class TemplatesController {
  public static function index(): void {
    self::auth();
    self::ensureTable();
    $stmt = db()->prepare('SELECT id, name, type, subject, body, created_at FROM message_templates WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([current_user_id()]);
    // Load library manifest for quick import
    $library = [];
    $manifestPath = BASE_PATH . '/content/sms_library/manifest.json';
    if (is_file($manifestPath)) {
      $json = file_get_contents($manifestPath);
      $arr = json_decode($json, true);
      if (is_array($arr)) $library = $arr;
    }
    view('templates/index', ['items' => $stmt->fetchAll(), 'library' => $library, 'ok' => flash_get('ok'), 'error' => flash_get('error'), 'filterType' => null]);
  }

  public static function indexSms(): void {
    self::auth();
    self::ensureTable();
    $stmt = db()->prepare('SELECT id, name, type, subject, body, created_at FROM message_templates WHERE user_id = ? AND type = "sms" ORDER BY id DESC');
    $stmt->execute([current_user_id()]);
    $library = [];
    $manifestPath = BASE_PATH . '/content/sms_library/manifest.json';
    if (is_file($manifestPath)) {
      $json = file_get_contents($manifestPath);
      $arr = json_decode($json, true);
      if (is_array($arr)) $library = $arr;
    }
    view('templates/index', ['items' => $stmt->fetchAll(), 'library' => $library, 'ok' => flash_get('ok'), 'error' => flash_get('error'), 'filterType' => 'sms']);
  }

  public static function indexEmail(): void {
    self::auth();
    self::ensureTable();
    $stmt = db()->prepare('SELECT id, name, type, subject, body, created_at FROM message_templates WHERE user_id = ? AND type = "email" ORDER BY id DESC');
    $stmt->execute([current_user_id()]);
    view('templates/index', ['items' => $stmt->fetchAll(), 'library' => [], 'ok' => flash_get('ok'), 'error' => flash_get('error'), 'filterType' => 'email']);
  }

  public static function create(): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTable();
    $name = trim((string)($_POST['name'] ?? ''));
    $type = in_array(($_POST['type'] ?? 'sms'), ['sms','email'], true) ? (string)$_POST['type'] : 'sms';
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    if ($name === '' || $body === '') { flash_set('error', 'Name and body required.'); redirect('/templates'); }
    if ($type === 'email' && $subject === '') { flash_set('error', 'Subject required for email templates.'); redirect('/templates'); }
    $stmt = db()->prepare('INSERT INTO message_templates (user_id, name, type, subject, body) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([current_user_id(), $name, $type, $subject !== '' ? $subject : null, $body]);
    flash_set('ok', 'Template created.');
    redirect('/templates');
  }

  public static function importFromLibrary(): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTable();
    $slug = trim((string)($_POST['library_slug'] ?? ''));
    if ($slug === '') { flash_set('error', 'Select a library template.'); redirect('/templates'); }
    $manifestPath = BASE_PATH . '/content/sms_library/manifest.json';
    if (!is_file($manifestPath)) { flash_set('error', 'Library not found.'); redirect('/templates'); }
    $json = file_get_contents($manifestPath);
    $arr = json_decode($json, true);
    if (!is_array($arr)) { flash_set('error', 'Library invalid.'); redirect('/templates'); }
    $entry = null;
    foreach ($arr as $e) { if (isset($e['slug']) && $e['slug'] === $slug) { $entry = $e; break; } }
    if (!$entry) { flash_set('error', 'Template not found.'); redirect('/templates'); }
    $file = BASE_PATH . '/content/sms_library/' . basename((string)$entry['filename']);
    if (!is_file($file)) { flash_set('error', 'Template file missing.'); redirect('/templates'); }
    $body = (string)file_get_contents($file);
    $name = (string)($entry['name'] ?? $slug);
    $stmt = db()->prepare('INSERT INTO message_templates (user_id, name, type, subject, body) VALUES (?, ?, "sms", NULL, ?)');
    $stmt->execute([current_user_id(), $name, $body]);
    flash_set('ok', 'Template imported from library.');
    redirect('/templates');
  }

  public static function editForm(int $id): void {
    self::auth();
    self::ensureTable();
    $stmt = db()->prepare('SELECT id, name, type, subject, body FROM message_templates WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, current_user_id()]);
    $tpl = $stmt->fetch();
    if (!$tpl) { http_response_code(404); echo 'Not Found'; return; }
    view('templates/edit', ['tpl' => $tpl, 'error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function update(int $id): void {
    self::auth();
    require_csrf_or_400();
    self::ensureTable();
    $name = trim((string)($_POST['name'] ?? ''));
    $type = in_array(($_POST['type'] ?? 'sms'), ['sms','email'], true) ? (string)$_POST['type'] : 'sms';
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    if ($name === '' || $body === '') { flash_set('error', 'Name and body required.'); redirect('/templates/' . $id . '/edit'); }
    if ($type === 'email' && $subject === '') { flash_set('error', 'Subject required for email templates.'); redirect('/templates/' . $id . '/edit'); }
    $stmt = db()->prepare('UPDATE message_templates SET name = ?, type = ?, subject = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$name, $type, $subject !== '' ? $subject : null, $body, $id, current_user_id()]);
    flash_set('ok', 'Template updated.');
    redirect('/templates');
  }

  public static function showJson(int $id): void {
    self::auth();
    self::ensureTable();
    header('Content-Type: application/json');
    try {
      $stmt = db()->prepare('SELECT id, name, type, subject, body FROM message_templates WHERE id = ? AND user_id = ? LIMIT 1');
      $stmt->execute([$id, current_user_id()]);
      $tpl = $stmt->fetch();
      if (!$tpl) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        return;
      }
      echo json_encode(['ok' => true, 'template' => $tpl]);
    } catch (Throwable $e) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'server_error']);
    }
  }

  private static function ensureTable(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS message_templates (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      name VARCHAR(255) NOT NULL,
      type ENUM("sms","email") NOT NULL DEFAULT "sms",
      subject VARCHAR(255) NULL,
      body TEXT NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_msgtpl_user_name_type (user_id, name, type),
      KEY ix_msgtpl_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    // best-effort migration from older sms_templates table
    try {
      db()->exec('INSERT INTO message_templates (user_id, name, type, subject, body)
                  SELECT user_id, name, "sms" as type, NULL as subject, body FROM sms_templates');
    } catch (Throwable $e) { /* ignore */ }
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }

  public static function delete(int $id): void {
    self::auth();
    self::ensureTable();
    require_csrf_or_400();
    try { db()->prepare('UPDATE contact_lists SET default_sms_template_id = NULL WHERE default_sms_template_id = ? AND user_id = ?')->execute([$id, current_user_id()]); } catch (Throwable $e) {}
    try { db()->prepare('UPDATE contact_lists SET default_email_template_id = NULL WHERE default_email_template_id = ? AND user_id = ?')->execute([$id, current_user_id()]); } catch (Throwable $e) {}
    $del = db()->prepare('DELETE FROM message_templates WHERE id = ? AND user_id = ?');
    $del->execute([$id, current_user_id()]);
    flash_set('ok', 'Template deleted.');
    redirect('/templates');
  }
}


