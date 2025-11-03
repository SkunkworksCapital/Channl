<?php
declare(strict_types=1);

final class BillingController {
  public static function index(): void {
    self::auth();
    ensure_wallet_tables();
    $balance = wallet_get_balance((int)current_user_id());
    $tx = db()->prepare('SELECT id, amount, type, reason, created_at FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 50');
    $tx->execute([current_user_id()]);
    view('billing/index', [ 'balance' => $balance, 'tx' => $tx->fetchAll(), 'ok' => flash_get('ok'), 'error' => flash_get('error') ]);
  }

  public static function buy(): void {
    self::auth();
    require_csrf_or_400();
    $pkg = (string)($_POST['package'] ?? '');
    // Simple preset packages (credits only; integrate payments later)
    // Accept old keys for backwards-compat
    $packages = [
      'deliver' => [ 'credits' => 500.0, 'price' => 29.95 ],
      'grow'    => [ 'credits' => 2000.0, 'price' => 100.50 ],
      'expand'  => [ 'credits' => 6000.0, 'price' => 296.00 ],
      // backwards compatibility
      'starter' => [ 'credits' => 500.0, 'price' => 29.95 ],
      'growth'  => [ 'credits' => 2000.0, 'price' => 100.50 ],
      'scale'   => [ 'credits' => 6000.0, 'price' => 296.00 ],
    ];
    if (!isset($packages[$pkg])) { flash_set('error', 'Invalid package.'); redirect('/billing'); }
    $p = $packages[$pkg];
    wallet_credit((int)current_user_id(), (float)$p['credits'], 'purchase', [ 'package' => $pkg, 'price' => $p['price'] ]);
    flash_set('ok', 'Credits added: ' . (float)$p['credits']);
    redirect('/billing');
  }

  // Temporary free-credit endpoint via GET for quick trials
  public static function gift(): void {
    self::auth();
    $pkg = (string)($_GET['package'] ?? '');
    $packages = [
      'deliver' => [ 'credits' => 500.0, 'price' => 29.95 ],
      'grow'    => [ 'credits' => 2000.0, 'price' => 100.50 ],
      'expand'  => [ 'credits' => 6000.0, 'price' => 296.00 ],
    ];
    if (!isset($packages[$pkg])) { flash_set('error', 'Select a package.'); redirect('/billing'); }
    $p = $packages[$pkg];
    wallet_credit((int)current_user_id(), (float)$p['credits'], 'trial_gift', [ 'package' => $pkg, 'price' => 0 ]);
    flash_set('ok', 'Trial credits added: ' . (float)$p['credits']);
    redirect('/billing');
  }

  private static function auth(): void { if (!current_user_id()) { flash_set('error', 'Please login.'); redirect('/login'); } }
}



