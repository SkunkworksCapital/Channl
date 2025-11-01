<?php
declare(strict_types=1);

final class SmtpClient {
  private string $host;
  private int $port;
  private string $username;
  private string $password;
  private string $from;
  private bool $useTls;

  public function __construct(array $cfg) {
    $this->host = (string)($cfg['host'] ?? 'localhost');
    $this->port = (int)($cfg['port'] ?? 587);
    $this->username = (string)($cfg['user'] ?? '');
    $this->password = (string)($cfg['pass'] ?? '');
    $this->from = (string)($cfg['from'] ?? 'no-reply@example.com');
    $this->useTls = $this->port === 587 || ($cfg['tls'] ?? true);
  }

  public function send(string $to, string $subject, string $bodyText): array {
    $transcript = [];
    $errno = 0; $errstr = '';
    $context = stream_context_create([]);
    $fp = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
      return ['ok' => false, 'status' => 0, 'error' => 'socket_open_failed', 'response' => ['message' => $errstr]];
    }
    stream_set_timeout($fp, 15);

    $read = function() use ($fp, &$transcript) {
      $line = fgets($fp, 2048) ?: '';
      $transcript[] = trim($line);
      return $line;
    };
    $write = function(string $cmd) use ($fp, &$transcript) {
      $transcript[] = '> ' . trim($cmd);
      fwrite($fp, $cmd . "\r\n");
    };

    $greet = $read();
    if (strpos($greet, '220') !== 0) return $this->fail($fp, $transcript, 0, 'no_greeting');

    $write('EHLO channl.local');
    $ehlo = $read(); if (strpos($ehlo, '250') !== 0) return $this->fail($fp, $transcript, 0, 'ehlo_failed');
    // Drain EHLO lines
    stream_set_blocking($fp, false);
    while (($l = fgets($fp, 2048)) !== false) { $transcript[] = trim($l); }
    stream_set_blocking($fp, true);

    if ($this->useTls) {
      $write('STARTTLS');
      $tls = $read(); if (strpos($tls, '220') !== 0) return $this->fail($fp, $transcript, 0, 'starttls_failed');
      if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $this->fail($fp, $transcript, 0, 'tls_enable_failed');
      $write('EHLO channl.local');
      $ehlo2 = $read(); if (strpos($ehlo2, '250') !== 0) return $this->fail($fp, $transcript, 0, 'ehlo_after_tls_failed');
      stream_set_blocking($fp, false);
      while (($l = fgets($fp, 2048)) !== false) { $transcript[] = trim($l); }
      stream_set_blocking($fp, true);
    }

    if ($this->username !== '') {
      $write('AUTH LOGIN');
      if (strpos($read(), '334') !== 0) return $this->fail($fp, $transcript, 0, 'auth_prompt_failed');
      $write(base64_encode($this->username));
      if (strpos($read(), '334') !== 0) return $this->fail($fp, $transcript, 0, 'auth_user_rejected');
      $write(base64_encode($this->password));
      if (strpos($read(), '235') !== 0) return $this->fail($fp, $transcript, 0, 'auth_failed');
    }

    $from = $this->from;
    $write('MAIL FROM: <' . $from . '>');
    if (strpos($read(), '250') !== 0) return $this->fail($fp, $transcript, 0, 'mail_from_failed');
    $write('RCPT TO: <' . $to . '>');
    if (strpos($read(), '250') !== 0 && strpos($transcript[count($transcript)-1] ?? '', '251') !== 0) return $this->fail($fp, $transcript, 0, 'rcpt_to_failed');
    $write('DATA');
    if (strpos($read(), '354') !== 0) return $this->fail($fp, $transcript, 0, 'data_failed');

    $message = $this->buildMessage($from, $to, $subject, $bodyText);
    fwrite($fp, $message . "\r\n.\r\n");
    $resp = $read();
    if (strpos($resp, '250') !== 0) return $this->fail($fp, $transcript, 0, 'message_rejected');
    $write('QUIT');
    fclose($fp);
    return ['ok' => true, 'status' => 250, 'response' => ['transcript' => $transcript]];
  }

  private function buildMessage(string $from, string $to, string $subject, string $bodyText): string {
    $headers = [];
    $headers[] = 'From: <' . $from . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $this->encodeHeader($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@channl>';
    return implode("\r\n", $headers) . "\r\n\r\n" . $bodyText;
  }

  private function fail($fp, array $transcript, int $status, string $message): array {
    if (is_resource($fp)) { @fclose($fp); }
    return ['ok' => false, 'status' => $status, 'error' => $message, 'response' => ['transcript' => $transcript]];
  }

  private function encodeHeader(string $value): string {
    if (preg_match('/[^\x20-\x7E]/', $value)) {
      return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
  }
}


