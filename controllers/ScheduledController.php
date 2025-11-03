<?php
declare(strict_types=1);

require_once BASE_PATH . '/core/scheduler.php';

final class ScheduledController {
  public static function index(): void {
    self::auth();
    ensure_scheduled_jobs_table();
    $uid = (int)current_user_id();
    $items = [];
    $tz = user_timezone($uid);
    try {
      $q = db()->prepare('SELECT id, channel, mode, payload, scheduled_at, status, attempts, last_error, created_at FROM scheduled_jobs WHERE user_id = ? ORDER BY status = "pending" DESC, scheduled_at ASC, id DESC LIMIT 500');
      $q->execute([$uid]);
      $rows = $q->fetchAll();
      foreach ($rows as $r) {
        $utc = new DateTimeImmutable((string)$r['scheduled_at'], new DateTimeZone('UTC'));
        $local = $utc->setTimezone(new DateTimeZone($tz));
        $items[] = [
          'id' => (int)$r['id'],
          'channel' => (string)$r['channel'],
          'mode' => (string)$r['mode'],
          'payload' => (string)$r['payload'],
          'scheduled_at_utc' => $utc->format('Y-m-d H:i:s'),
          'scheduled_at_local' => $local->format('Y-m-d H:i'),
          'status' => (string)$r['status'],
          'attempts' => (int)$r['attempts'],
          'last_error' => (string)($r['last_error'] ?? ''),
          'created_at' => (string)$r['created_at'],
        ];
      }
    } catch (Throwable $e) {}
    view('scheduled/index', [ 'items' => $items, 'tz' => $tz, 'ok' => flash_get('ok'), 'error' => flash_get('error') ]);
  }

  public static function cancel(int $id): void {
    self::auth();
    ensure_scheduled_jobs_table();
    $uid = (int)current_user_id();
    try {
      $u = db()->prepare('UPDATE scheduled_jobs SET status = "cancelled", updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND status = "pending"');
      $u->execute([$id, $uid]);
      if ($u->rowCount() > 0) {
        audit_log('scheduled.cancelled', 'scheduled_job', $id);
        flash_set('ok', 'Scheduled job cancelled.');
      } else {
        flash_set('error', 'Unable to cancel. It may have already run.');
      }
    } catch (Throwable $e) {
      flash_set('error', 'Failed to cancel job.');
    }
    redirect('/scheduled');
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }
}


