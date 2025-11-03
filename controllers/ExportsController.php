<?php
declare(strict_types=1);

final class ExportsController {
  private static function authAdmin(): void {
    if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); }
    if (!is_admin()) { http_response_code(403); echo 'Forbidden'; exit; }
  }

  public static function auditCsv(): void {
    self::authAdmin();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','actor_user_id','action','resource_type','resource_id','ip','user_agent','meta','created_at']);
    $stmt = db()->query('SELECT id, actor_user_id, action, resource_type, resource_id, INET6_NTOA(ip) AS ip, user_agent, meta, created_at FROM audit_logs ORDER BY id DESC LIMIT 10000');
    while ($row = $stmt->fetch()) {
      fputcsv($out, [ $row['id'], $row['actor_user_id'], $row['action'], $row['resource_type'], $row['resource_id'], $row['ip'], $row['user_agent'], $row['meta'], $row['created_at'] ]);
    }
    fclose($out);
    audit_log('export.audit_csv', 'export', null);
    exit;
  }

  public static function messagesCsv(): void {
    self::authAdmin();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="messages.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id','user_id','channel','provider','to_addr','from_addr','status','price','currency','created_at']);
    $stmt = db()->query('SELECT id, user_id, channel, provider, to_addr, from_addr, status, price, currency, created_at FROM messages ORDER BY id DESC LIMIT 50000');
    while ($row = $stmt->fetch()) {
      fputcsv($out, [ $row['id'], $row['user_id'], $row['channel'], $row['provider'], $row['to_addr'], $row['from_addr'], $row['status'], $row['price'], $row['currency'], $row['created_at'] ]);
    }
    fclose($out);
    audit_log('export.messages_csv', 'export', null);
    exit;
  }
}

?>

