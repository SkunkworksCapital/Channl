<?php
declare(strict_types=1);

function route_request(): void {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
  $path = rtrim($uri, '/') ?: '/';
  if ($path === '/index.php') {
    $path = '/';
  }
  // Normalize multiple slashes and trailing slashes
  $path = preg_replace('#/+#', '/', $path);
  if (function_exists('error_log')) { error_log('[ROUTE] ' . $method . ' ' . $path); }

  if ($method === 'GET' && $path === '/') {
    if (function_exists('current_user_id') && !current_user_id()) {
      view('landing', [ 'env' => $GLOBALS['CONFIG']['app_env'] ?? 'prod' ]);
      return;
    }
    $stats = null;
    if (function_exists('current_user_id') && current_user_id()) {
      try {
        $uid = current_user_id();
        $q1 = db()->prepare('SELECT COUNT(*) FROM contacts WHERE user_id = ?');
        $q1->execute([$uid]);
        $contactsCount = (int)$q1->fetchColumn();

        $q2 = db()->prepare('SELECT COUNT(*) FROM contact_lists WHERE user_id = ?');
        $q2->execute([$uid]);
        $listsCount = (int)$q2->fetchColumn();

        $q3 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ?');
        $q3->execute([$uid]);
        $msgTotal = (int)$q3->fetchColumn();

        $q4 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND status = "sent"');
        $q4->execute([$uid]);
        $msgSent = (int)$q4->fetchColumn();

        $q5 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND status = "error"');
        $q5->execute([$uid]);
        $msgFailed = (int)$q5->fetchColumn();

        // channel breakdown
        $qs1 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND channel = "sms" AND status = "sent"');
        $qs1->execute([$uid]);
        $smsSent = (int)$qs1->fetchColumn();
        $qs2 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND channel = "sms" AND status = "error"');
        $qs2->execute([$uid]);
        $smsFailed = (int)$qs2->fetchColumn();

        $qe1 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND channel = "email" AND status = "sent"');
        $qe1->execute([$uid]);
        $emailSent = (int)$qe1->fetchColumn();
        $qe2 = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND channel = "email" AND status = "error"');
        $qe2->execute([$uid]);
        $emailFailed = (int)$qe2->fetchColumn();

        $stats = [
          'contacts' => $contactsCount,
          'lists' => $listsCount,
          'messages' => [ 'total' => $msgTotal, 'sent' => $msgSent, 'failed' => $msgFailed ],
          'sms' => [ 'sent' => $smsSent, 'failed' => $smsFailed ],
          'email' => [ 'sent' => $emailSent, 'failed' => $emailFailed ],
        ];
      } catch (Throwable $e) {
        $stats = null;
      }
    }
    view('home', [
      'env' => $GLOBALS['CONFIG']['app_env'] ?? 'prod',
      'stats' => $stats,
    ]);
    return;
  }

  // Explicit landing page route (available even when logged in)
  if ($method === 'GET' && $path === '/landing') {
    view('landing', [ 'env' => $GLOBALS['CONFIG']['app_env'] ?? 'prod' ]);
    return;
  }

  if ($method === 'GET' && $path === '/health/db') {
    $verbose = isset($_GET['verbose']) && ($_GET['verbose'] === '1' || $_GET['verbose'] === 'true');
    try {
      $ok = db()->query('SELECT 1')->fetchColumn();
      if ($ok == 1 && !$verbose) {
        header('Content-Type: text/plain');
        echo 'ok';
        return;
      }
      header('Content-Type: application/json');
      $cfg = $GLOBALS['CONFIG']['db'] ?? [];
      echo json_encode([
        'ok' => $ok == 1,
        'driver' => extension_loaded('pdo_mysql') ? 'pdo_mysql' : 'missing',
        'host' => $cfg['host'] ?? null,
        'port' => $cfg['port'] ?? null,
        'name' => $cfg['name'] ?? null,
        'user' => $cfg['user'] ?? null,
      ]);
      return;
    } catch (Throwable $e) {
      http_response_code(500);
      if ($verbose) {
        header('Content-Type: application/json');
        $cfg = $GLOBALS['CONFIG']['db'] ?? [];
        echo json_encode([
          'ok' => false,
          'driver' => extension_loaded('pdo_mysql') ? 'pdo_mysql' : 'missing',
          'host' => $cfg['host'] ?? null,
          'port' => $cfg['port'] ?? null,
          'name' => $cfg['name'] ?? null,
          'user' => $cfg['user'] ?? null,
          'error' => $e->getMessage(),
        ]);
      } else {
        header('Content-Type: text/plain');
        echo 'db error';
      }
      return;
    }
  }

  // Auth routes
  require_once BASE_PATH . '/controllers/AuthController.php';
  if ($method === 'GET' && $path === '/login') { AuthController::showLogin(); return; }
  if ($method === 'POST' && $path === '/login') { AuthController::login(); return; }
  if ($method === 'GET' && $path === '/register') { AuthController::showRegister(); return; }
  if ($method === 'POST' && $path === '/register') { AuthController::register(); return; }
  if ($method === 'POST' && $path === '/logout') { require_csrf_or_400(); AuthController::logout(); return; }
  // SMS routes
  require_once BASE_PATH . '/controllers/SmsController.php';
  if ($method === 'GET' && $path === '/sms/send') { SmsController::sendForm(); return; }
  if ($method === 'POST' && $path === '/sms/send') { SmsController::send(); return; }
  if ($method === 'GET' && $path === '/sms/inbox') { SmsController::inbox(); return; }
  if ($method === 'POST' && $path === '/sms/sync') { require_csrf_or_400(); SmsController::syncFromTwilio(); return; }
  if ($method === 'POST' && $path === '/webhooks/twilio/sms') { SmsController::twilioInbound(); return; }

  // WhatsApp routes
  require_once BASE_PATH . '/controllers/WhatsappController.php';
  if ($method === 'GET' && $path === '/whatsapp/send') { WhatsappController::sendForm(); return; }
  if ($method === 'POST' && $path === '/whatsapp/send') { WhatsappController::send(); return; }
  if ($method === 'GET' && $path === '/whatsapp/inbox') { WhatsappController::inbox(); return; }
  if ($path === '/webhooks/whatsapp') { WhatsappController::webhook(); return; }

  // Campaigns (deprecated) → redirect to lists
  if ($path === '/campaigns' || $path === '/campaigns/new' || preg_match('#^/campaigns/\d+$#', $path)) {
    header('Location: /lists', true, 302);
    return;
  }

  // Contacts routes
  require_once BASE_PATH . '/controllers/ContactsController.php';
  if ($method === 'GET' && $path === '/contacts') { ContactsController::index(); return; }
  if ($method === 'GET' && $path === '/contacts/upload') { ContactsController::uploadForm(); return; }
  if ($method === 'POST' && $path === '/contacts/upload') { ContactsController::upload(); return; }
  if ($method === 'GET' && $path === '/contacts/new') { ContactsController::newForm(); return; }
  if ($method === 'POST' && $path === '/contacts') { ContactsController::create(); return; }
  if ($method === 'GET' && preg_match('#^/contacts/(\\d+)$#', $path, $m)) { ContactsController::view((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/contacts/(\\d+)/delete$#', $path, $m)) { ContactsController::delete((int)$m[1]); return; }

  // Contact Lists
  require_once BASE_PATH . '/controllers/ListsController.php';
  if ($method === 'GET' && $path === '/lists') { ListsController::index(); return; }
  if ($method === 'POST' && $path === '/lists') { ListsController::create(); return; }
  if ($method === 'GET' && preg_match('#^/lists/(\\d+)$#', $path, $m)) { ListsController::view((int)$m[1]); return; }
  if ($method === 'GET' && preg_match('#^/lists/(\\d+)/members\\.json$#', $path, $m)) { ListsController::membersJson((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/lists/(\\d+)/members$#', $path, $m)) { ListsController::addMember((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/lists/(\\d+)/members/remove$#', $path, $m)) { ListsController::removeMember((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/lists/(\\d+)/delete$#', $path, $m)) { ListsController::delete((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/lists/(\\d+)/templates$#', $path, $m)) { ListsController::setTemplates((int)$m[1]); return; }

  // Templates
  require_once BASE_PATH . '/controllers/TemplatesController.php';
  if ($method === 'GET' && $path === '/templates') { TemplatesController::index(); return; }
  if ($method === 'GET' && $path === '/templates/sms') { TemplatesController::indexSms(); return; }
  if ($method === 'GET' && $path === '/templates/email') { TemplatesController::indexEmail(); return; }
  if ($method === 'POST' && $path === '/templates') { TemplatesController::create(); return; }
  if ($method === 'POST' && $path === '/templates/import') { TemplatesController::importFromLibrary(); return; }
  if ($method === 'GET' && preg_match('#^/templates/(\\d+)/edit$#', $path, $m)) { TemplatesController::editForm((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/templates/(\\d+)$#', $path, $m)) { TemplatesController::update((int)$m[1]); return; }
  if ($method === 'GET' && preg_match('#^/templates/(\\d+)/json$#', $path, $m)) { TemplatesController::showJson((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/templates/(\\d+)/delete$#', $path, $m)) { TemplatesController::delete((int)$m[1]); return; }

  // Email
  require_once BASE_PATH . '/controllers/EmailController.php';
  if ($method === 'GET' && $path === '/email/send') { EmailController::form(); return; }
  if ($method === 'POST' && $path === '/email/send') { EmailController::send(); return; }
  if ($method === 'GET' && $path === '/email/inbox') { EmailController::inbox(); return; }

  // Billing
  require_once BASE_PATH . '/controllers/BillingController.php';
  if ($method === 'GET' && $path === '/billing') { BillingController::index(); return; }
  if ($method === 'GET' && $path === '/billing/buy') { BillingController::gift(); return; }
  if ($method === 'POST' && $path === '/billing/buy') { BillingController::buy(); return; }

  // Settings
  require_once BASE_PATH . '/controllers/SettingsController.php';
  if ($method === 'GET' && $path === '/settings') { SettingsController::profile(); return; }
  if ($method === 'POST' && $path === '/settings') { SettingsController::update(); return; }

  // API
  require_once BASE_PATH . '/controllers/ApiController.php';
  if ($method === 'GET' && $path === '/api/balance') { ApiController::balance(); return; }

  // Approvals (admin)
  require_once BASE_PATH . '/controllers/ApprovalsController.php';
  if ($method === 'GET' && $path === '/approvals') { ApprovalsController::index(); return; }
  if ($method === 'POST' && preg_match('#^/approvals/(\d+)/approve$#', $path, $m)) { ApprovalsController::approve((int)$m[1]); return; }
  if ($method === 'POST' && preg_match('#^/approvals/(\d+)/reject$#', $path, $m)) { ApprovalsController::reject((int)$m[1]); return; }

  // Exports (admin)
  require_once BASE_PATH . '/controllers/ExportsController.php';
  if ($method === 'GET' && $path === '/exports/audit.csv') { ExportsController::auditCsv(); return; }
  if ($method === 'GET' && $path === '/exports/messages.csv') { ExportsController::messagesCsv(); return; }

  // Scheduled jobs
  require_once BASE_PATH . '/controllers/ScheduledController.php';
  if ($method === 'GET' && $path === '/scheduled') { ScheduledController::index(); return; }
  if ($method === 'POST' && preg_match('#^/scheduled/(\d+)/cancel$#', $path, $m)) { require_csrf_or_400(); ScheduledController::cancel((int)$m[1]); return; }

  http_response_code(404);
  echo 'Not Found';
  if (function_exists('error_log')) { error_log('[ROUTE_404] ' . $method . ' ' . $path); }
}


