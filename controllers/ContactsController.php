<?php
declare(strict_types=1);

final class ContactsController {
  public static function index(): void {
    self::requireAuth();
    $userId = current_user_id();
    $stmt = db()->prepare('SELECT id, name, email, phone, country, tags, created_at FROM contacts WHERE user_id = ? ORDER BY id DESC LIMIT 100');
    $stmt->execute([$userId]);
    $contacts = $stmt->fetchAll();
    view('contacts/index', ['contacts' => $contacts]);
  }

  public static function view(int $id): void {
    self::requireAuth();
    $userId = current_user_id();
    $stmt = db()->prepare('SELECT id, name, email, phone, country, tags, created_at FROM contacts WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, $userId]);
    $contact = $stmt->fetch();
    if (!$contact) {
      flash_set('error', 'Contact not found.');
      redirect('/contacts');
    }

    // Ensure messages table exists (safe if already created)
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

    $messages = [];
    $phone = $contact['phone'] ?? null;
    $email = $contact['email'] ?? null;
    try {
      // Fetch SMS/WhatsApp by phone and Email by email; include both inbound and outbound
      if ($phone || $email) {
        // Best-effort: ensure open-tracking columns exist for email messages
        try { db()->exec('ALTER TABLE messages ADD COLUMN opens INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
        try { db()->exec('ALTER TABLE messages ADD COLUMN last_opened_at DATETIME NULL'); } catch (Throwable $e) {}
        $clauses = [];
        $params = [$userId];
        if ($phone) { $clauses[] = '((channel IN ("sms","whatsapp")) AND (to_addr = ? OR from_addr = ?))'; $params[] = $phone; $params[] = $phone; }
        if ($email) { $clauses[] = '((channel = "email") AND (to_addr = ? OR from_addr = ?))'; $params[] = $email; $params[] = $email; }
        $where = implode(' OR ', $clauses);
        $sql = 'SELECT id, channel, provider, to_addr, from_addr, body, provider_message_id, status, price, currency, opens, last_opened_at, created_at FROM messages WHERE user_id = ? AND (' . $where . ') ORDER BY id DESC LIMIT 1000';
        $q = db()->prepare($sql);
        $q->execute($params);
        $messages = $q->fetchAll();
      }
    } catch (Throwable $e) {
      $messages = [];
    }

    view('contacts/view', [ 'contact' => $contact, 'messages' => $messages ]);
  }

  public static function uploadForm(): void {
    self::requireAuth();
    view('contacts/upload', ['error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function newForm(): void {
    self::requireAuth();
    view('contacts/new', ['error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function editForm(int $id): void {
    self::requireAuth();
    $userId = current_user_id();
    $stmt = db()->prepare('SELECT id, name, email, phone, country, tags FROM contacts WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, $userId]);
    $contact = $stmt->fetch();
    if (!$contact) { flash_set('error', 'Contact not found.'); redirect('/contacts'); }
    view('contacts/edit', ['contact' => $contact, 'error' => flash_get('error'), 'ok' => flash_get('ok')]);
  }

  public static function upload(): void {
    self::requireAuth();
    require_csrf_or_400();
    if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
      flash_set('error', 'No file uploaded.');
      redirect('/contacts/upload');
    }
    $tmp = $_FILES['csv']['tmp_name'];
    $tags = trim((string)($_POST['tags'] ?? ''));
    $tagList = array_values(array_filter(array_map('trim', explode(',', $tags)), fn($t) => $t !== ''));

    $userId = current_user_id();
    $handle = fopen($tmp, 'r');
    if ($handle === false) {
      flash_set('error', 'Unable to read file.');
      redirect('/contacts/upload');
    }
    $count = 0; $inserted = 0; $updated = 0;
    while (($row = fgetcsv($handle)) !== false) {
      $count++;
      if ($count === 1 && self::looksLikeHeader($row)) continue;
      [$name, $email, $phone, $country] = self::normalizeRow($row);
      if ($email === null && $phone === null) continue;

      self::upsertContact($userId, $name, $email, $phone, $country, $tagList, $inserted, $updated);
    }
    fclose($handle);
    flash_set('ok', "Processed {$count} rows. Inserted {$inserted}, Updated {$updated}.");
    redirect('/contacts');
  }

  public static function create(): void {
    self::requireAuth();
    require_csrf_or_400();
    $name = trim((string)($_POST['name'] ?? '')) ?: null;
    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $country = strtoupper(substr(trim((string)($_POST['country'] ?? '')), 0, 2)) ?: null;
    $tagsStr = trim((string)($_POST['tags'] ?? ''));
    $tagList = array_values(array_filter(array_map('trim', explode(',', $tagsStr)), fn($t) => $t !== ''));

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : null;
    $phone = self::normalizePhone($phoneRaw);

    if ($email === null && $phone === null) {
      flash_set('error', 'Provide a valid email or phone.');
      redirect('/contacts/new');
    }

    $inserted = 0; $updated = 0;
    self::upsertContact((int)current_user_id(), $name, $email, $phone, $country, $tagList, $inserted, $updated);

    if ($inserted > 0) {
      flash_set('ok', 'Contact added.');
    } else if ($updated > 0) {
      flash_set('ok', 'Contact updated.');
    } else {
      flash_set('ok', 'Contact saved.');
    }
    redirect('/contacts');
  }

  public static function update(int $id): void {
    self::requireAuth();
    require_csrf_or_400();
    $userId = (int)current_user_id();
    $name = trim((string)($_POST['name'] ?? '')) ?: null;
    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $country = strtoupper(substr(trim((string)($_POST['country'] ?? '')), 0, 2)) ?: null;
    $tagsStr = trim((string)($_POST['tags'] ?? ''));
    $tagList = array_values(array_filter(array_map('trim', explode(',', $tagsStr)), fn($t) => $t !== ''));

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : null;
    $phone = self::normalizePhone($phoneRaw);

    try {
      // ensure contact belongs to user
      $chk = db()->prepare('SELECT id FROM contacts WHERE id = ? AND user_id = ?');
      $chk->execute([$id, $userId]);
      if (!$chk->fetch()) { flash_set('error', 'Contact not found.'); redirect('/contacts'); }

      $tagsJson = !empty($tagList) ? json_encode(array_values(array_unique($tagList))) : null;
      $stmt = db()->prepare('UPDATE contacts SET name = ?, email = ?, phone = ?, country = ?, tags = ? WHERE id = ? AND user_id = ?');
      $stmt->execute([$name, $email, $phone, $country, $tagsJson, $id, $userId]);
      flash_set('ok', 'Contact updated.');
      redirect('/contacts/' . (int)$id);
    } catch (Throwable $e) {
      flash_set('error', 'Failed to update contact.');
      redirect('/contacts/' . (int)$id . '/edit');
    }
  }

  private static function looksLikeHeader(array $row): bool {
    $joined = strtolower(implode(',', $row));
    return strpos($joined, 'email') !== false || strpos($joined, 'phone') !== false;
  }

  private static function normalizeRow(array $row): array {
    $name = isset($row[0]) ? trim((string)$row[0]) : null;
    $emailRaw = isset($row[1]) ? trim((string)$row[1]) : '';
    $phoneRaw = isset($row[2]) ? trim((string)$row[2]) : '';
    $country = isset($row[3]) ? strtoupper(substr(trim((string)$row[3]), 0, 2)) : null;

    $email = filter_var($emailRaw, FILTER_VALIDATE_EMAIL) ? $emailRaw : null;
    $phone = self::normalizePhone($phoneRaw);
    return [$name ?: null, $email, $phone, $country ?: null];
  }

  private static function normalizePhone(string $p): ?string {
    $digits = preg_replace('/[^0-9+]/', '', $p);
    if ($digits === '' || strlen($digits) < 7) return null;
    if ($digits[0] !== '+') {
      $digits = '+' . $digits;
    }
    return $digits;
  }

  private static function upsertContact(int $userId, ?string $name, ?string $email, ?string $phone, ?string $country, array $tags, int &$inserted, int &$updated): void {
    // Try to find existing by email or phone
    $existing = null;
    if ($email) {
      $stmt = db()->prepare('SELECT id, tags FROM contacts WHERE user_id = ? AND email = ? LIMIT 1');
      $stmt->execute([$userId, $email]);
      $existing = $stmt->fetch();
    }
    if (!$existing && $phone) {
      $stmt = db()->prepare('SELECT id, tags FROM contacts WHERE user_id = ? AND phone = ? LIMIT 1');
      $stmt->execute([$userId, $phone]);
      $existing = $stmt->fetch();
    }

    $tagsJson = null;
    if (!empty($tags)) {
      $tagsJson = json_encode(array_values(array_unique($tags)));
    }

    if ($existing) {
      $id = (int)$existing['id'];
      // merge tags
      $mergedTags = $tags;
      if (!empty($existing['tags'])) {
        $prev = json_decode($existing['tags'], true);
        if (is_array($prev)) $mergedTags = array_values(array_unique(array_merge($prev, $tags)));
      }
      $mergedJson = !empty($mergedTags) ? json_encode($mergedTags) : null;
      $stmt = db()->prepare('UPDATE contacts SET name = COALESCE(?, name), email = COALESCE(?, email), phone = COALESCE(?, phone), country = COALESCE(?, country), tags = ? WHERE id = ? AND user_id = ?');
      $stmt->execute([$name, $email, $phone, $country, $mergedJson, $id, $userId]);
      $updated++;
    } else {
      $stmt = db()->prepare('INSERT INTO contacts (user_id, name, email, phone, country, tags) VALUES (?, ?, ?, ?, ?, ?)');
      $stmt->execute([$userId, $name, $email, $phone, $country, $tagsJson]);
      $inserted++;
    }
  }

  private static function requireAuth(): void {
    if (!current_user_id()) {
      flash_set('error', 'Please login.');
      redirect('/login');
    }
  }

  public static function delete(int $id): void {
    self::requireAuth();
    require_csrf_or_400();
    // Remove from any lists first
    db()->prepare('DELETE FROM contact_list_members WHERE contact_id = ?')->execute([$id]);
    // Then delete the contact for this user
    $stmt = db()->prepare('DELETE FROM contacts WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, current_user_id()]);
    flash_set('ok', 'Contact removed.');
    redirect('/contacts');
  }
}


