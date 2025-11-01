<?php
declare(strict_types=1);

final class SmsTwilio {
  private string $sid;
  private string $token;
  private string $from;

  public function __construct(array $cfg) {
    $this->sid = (string)($cfg['sid'] ?? '');
    $this->token = (string)($cfg['token'] ?? '');
    $from = trim((string)($cfg['from'] ?? ''));
    // If using a raw phone number (not a Messaging Service SID), strip any inline comments or extra tokens
    if (strpos($from, 'MG') !== 0) {
      $from = preg_replace('/\s*[#;].*$/', '', $from);
      $from = preg_replace('/\s+.*/', '', $from);
    }
    $this->from = $from;
  }

  public function send(string $to, string $body): array {
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($this->sid) . '/Messages.json';
    $params = [ 'To' => $to, 'Body' => $body ];
    if (strpos($this->from, 'MG') === 0) {
      $params['MessagingServiceSid'] = $this->from; // using Messaging Service
    } else {
      $params['From'] = $this->from; // using phone number
    }
    $payload = http_build_query($params);
    $auth = base64_encode($this->sid . ':' . $this->token);
    $resp = http_request('POST', $url, [
      'headers' => [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
      ],
      'body' => $payload,
      'timeout' => 15,
      'retry' => 2,
    ]);

    $status = $resp['status'];
    $json = json_decode($resp['body'] ?? '', true);
    if (!is_array($json)) $json = [];
    $sid = $json['sid'] ?? null;

    log_message_event($sid, 'sms', 'twilio', 'send', $status, [
      'to' => $to,
      'from' => $this->from,
      'body' => $body,
    ], $json);

    return [
      'ok' => $status >= 200 && $status < 300,
      'provider_message_id' => $sid,
      'status' => $status,
      'response' => $json,
    ];
  }

  public function listMessages(array $query = []): array {
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($this->sid) . '/Messages.json';
    if (!empty($query)) {
      // Support Twilio comparison operators in keys like 'DateSent>='
      $parts = [];
      foreach ($query as $k => $v) {
        $parts[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
      }
      $url .= '?' . implode('&', $parts);
    }
    $auth = base64_encode($this->sid . ':' . $this->token);
    $resp = http_request('GET', $url, [
      'headers' => [
        'Authorization: Basic ' . $auth,
      ],
      'timeout' => 15,
      'retry' => 2,
    ]);
    $status = $resp['status'];
    $json = json_decode($resp['body'] ?? '', true);
    if (!is_array($json)) $json = [];
    log_message_event(null, 'sms', 'twilio', 'list', $status, [ 'query' => $query ], $json);
    return [ 'ok' => $status >= 200 && $status < 300, 'status' => $status, 'response' => $json ];
  }
}


