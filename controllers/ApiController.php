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
}


