<?php
declare(strict_types=1);

require_once BASE_PATH . '/core/helpers.php';

function ensure_scheduled_jobs_table(): void {
  db()->exec('CREATE TABLE IF NOT EXISTS scheduled_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    channel ENUM("sms","email","whatsapp") NOT NULL,
    mode ENUM("single","list") NOT NULL,
    payload JSON NOT NULL,
    scheduled_at DATETIME NOT NULL,
    status ENUM("pending","running","done","failed","cancelled") NOT NULL DEFAULT "pending",
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_jobs_status_time (status, scheduled_at),
    KEY ix_jobs_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
}

function user_timezone(int $userId): string {
  try {
    $q = db()->prepare('SELECT timezone FROM users WHERE id = ?');
    $q->execute([$userId]);
    $row = $q->fetch();
    $tz = isset($row['timezone']) && is_string($row['timezone']) && $row['timezone'] !== '' ? (string)$row['timezone'] : 'UTC';
    return $tz;
  } catch (Throwable $e) { return 'UTC'; }
}

function user_quiet_hours(int $userId): array {
  try {
    $q = db()->prepare('SELECT quiet_start, quiet_end FROM users WHERE id = ?');
    $q->execute([$userId]);
    $row = $q->fetch();
    $start = isset($row['quiet_start']) ? (string)$row['quiet_start'] : null; // e.g. "21:00:00"
    $end = isset($row['quiet_end']) ? (string)$row['quiet_end'] : null; // e.g. "08:00:00"
    return [$start, $end];
  } catch (Throwable $e) { return [null, null]; }
}

function convert_local_to_utc(string $localDateTime, string $timezone): ?string {
  try {
    $tz = new DateTimeZone($timezone ?: 'UTC');
    $dt = new DateTimeImmutable($localDateTime, $tz);
    $utc = $dt->setTimezone(new DateTimeZone('UTC'));
    return $utc->format('Y-m-d H:i:s');
  } catch (Throwable $e) { return null; }
}

function is_within_quiet_hours(int $userId, DateTimeImmutable $timeUtc): bool {
  [$start, $end] = user_quiet_hours($userId);
  if (!$start && !$end) return false; // no quiet hours configured
  $tz = new DateTimeZone(user_timezone($userId));
  $local = $timeUtc->setTimezone($tz);
  $localTime = (int)$local->format('Hi'); // HHmm numeric for compare
  $startInt = $start ? (int)str_replace(':', '', substr($start, 0, 5)) : null;
  $endInt = $end ? (int)str_replace(':', '', substr($end, 0, 5)) : null;
  if ($startInt === null || $endInt === null) return false;
  if ($startInt <= $endInt) {
    // quiet window within same day, e.g. 21:00 -> 22:00
    return $localTime >= $startInt && $localTime < $endInt;
  } else {
    // quiet window spans midnight, e.g. 21:00 -> 08:00
    return ($localTime >= $startInt) || ($localTime < $endInt);
  }
}

function next_quiet_end_utc(int $userId, DateTimeImmutable $fromUtc): ?DateTimeImmutable {
  [$start, $end] = user_quiet_hours($userId);
  if (!$start && !$end) return null;
  $tz = new DateTimeZone(user_timezone($userId));
  $local = $fromUtc->setTimezone($tz);
  $endH = $end ? substr($end, 0, 5) : '08:00';
  // If we're already past end today, use today's end if still within quiet; else compute next day's end
  $endToday = DateTimeImmutable::createFromFormat('Y-m-d H:i', $local->format('Y-m-d') . ' ' . $endH, $tz);
  if ($endToday === false) return null;
  $startH = $start ? substr($start, 0, 5) : null;
  $within = is_within_quiet_hours($userId, $fromUtc);
  $targetLocal = $within && $endToday > $local ? $endToday : $endToday->modify('+1 day');
  return $targetLocal->setTimezone(new DateTimeZone('UTC'));
}

function user_daily_caps(int $userId): array {
  try {
    $q = db()->prepare('SELECT daily_cap_sms, daily_cap_email FROM users WHERE id = ?');
    $q->execute([$userId]);
    $row = $q->fetch();
    $sms = isset($row['daily_cap_sms']) ? (int)$row['daily_cap_sms'] : 0;
    $email = isset($row['daily_cap_email']) ? (int)$row['daily_cap_email'] : 0;
    return [$sms, $email];
  } catch (Throwable $e) { return [0, 0]; }
}

function user_sent_count_last_24h(int $userId, string $channel): int {
  try {
    $q = db()->prepare('SELECT COUNT(*) AS c FROM messages WHERE user_id = ? AND channel = ? AND status = "sent" AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
    $q->execute([$userId, $channel]);
    $row = $q->fetch();
    return $row ? (int)$row['c'] : 0;
  } catch (Throwable $e) { return 0; }
}


