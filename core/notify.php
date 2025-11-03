<?php
declare(strict_types=1);

require_once BASE_PATH . '/services/SmtpClient.php';
require_once BASE_PATH . '/services/SendgridClient.php';

function send_email_via_config(string $to, string $subject, string $body): array {
  $cfg = $GLOBALS['CONFIG']['integrations']['email'] ?? [];
  $mode = (string)($cfg['mode'] ?? 'smtp');
  $provider = $mode === 'sendgrid' ? 'sendgrid' : 'smtp';
  $mailer = $mode === 'sendgrid' ? new SendgridClient($cfg) : new SmtpClient($cfg);
  $res = $mailer->send($to, $subject, $body);
  return [ 'ok' => (bool)($res['ok'] ?? false), 'provider' => $provider, 'error' => $res['error'] ?? null ];
}


