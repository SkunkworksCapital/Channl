<?php
declare(strict_types=1);

function http_request(string $method, string $url, array $opts = []): array {
  if (!function_exists('curl_init')) {
    return [ 'status' => 0, 'errno' => -1, 'error' => 'curl_extension_missing', 'body' => '' ];
  }
  $timeout = $opts['timeout'] ?? 10;
  $headers = $opts['headers'] ?? [];
  $body = $opts['body'] ?? null;
  $retry = (int)($opts['retry'] ?? 2);
  $retryDelayMs = (int)($opts['retry_delay_ms'] ?? 250);

  $attempt = 0; $errno = 0; $error = '';
  $httpCode = 0; $response = '';
  while ($attempt <= $retry) {
    $attempt++;
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_CUSTOMREQUEST => strtoupper($method),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_CONNECTTIMEOUT => $timeout,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = (string)curl_exec($ch);
    $errno = curl_errno($ch);
    $error = (string)curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $shouldRetry = $errno !== 0 || $httpCode >= 500;
    if (!$shouldRetry) break;
    usleep($retryDelayMs * 1000);
  }

  return [
    'status' => $httpCode,
    'errno' => $errno,
    'error' => $error,
    'body' => $response,
  ];
}

function log_message_event(?string $messageId, string $channel, string $provider, string $eventType, ?int $statusCode, $requestPayload, $responsePayload): void {
  try {
    $stmt = db()->prepare('INSERT INTO message_events (message_id, channel, provider, event_type, status_code, request_json, response_json) VALUES (?, ?, ?, ?, ?, CAST(? AS JSON), CAST(? AS JSON))');
    $req = json_encode($requestPayload, JSON_UNESCAPED_SLASHES);
    $res = json_encode($responsePayload, JSON_UNESCAPED_SLASHES);
    $stmt->execute([$messageId, $channel, $provider, $eventType, $statusCode, $req, $res]);
  } catch (Throwable $e) {
    // swallow logging errors
  }
}


