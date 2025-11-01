<?php
declare(strict_types=1);

function crypto_get_key(): string {
  $hex = $GLOBALS['CONFIG']['crypto']['file_key'] ?? '';
  $key = @hex2bin($hex);
  if ($key === false || strlen($key) !== 32) {
    throw new RuntimeException('Invalid FILE_KEY; expected 32-byte hex.');
  }
  return $key;
}

function crypto_random_iv(): string {
  return random_bytes(16);
}

function crypto_encrypt_string(string $plaintext): string {
  $key = crypto_get_key();
  $iv = crypto_random_iv();
  $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  if ($cipher === false) {
    throw new RuntimeException('Encryption failed');
  }
  $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
  return base64_encode("CH1" . $iv . $hmac . $cipher);
}

function crypto_decrypt_string(string $b64): string {
  $blob = base64_decode($b64, true);
  if ($blob === false || strlen($blob) < 3 + 16 + 32) {
    throw new RuntimeException('Invalid encrypted payload');
  }
  if (substr($blob, 0, 3) !== 'CH1') {
    throw new RuntimeException('Unknown payload version');
  }
  $offset = 3;
  $iv = substr($blob, $offset, 16); $offset += 16;
  $hmac = substr($blob, $offset, 32); $offset += 32;
  $cipher = substr($blob, $offset);
  $key = crypto_get_key();
  $calc = hash_hmac('sha256', $iv . $cipher, $key, true);
  if (!hash_equals($hmac, $calc)) {
    throw new RuntimeException('Invalid HMAC');
  }
  $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
  if ($plain === false) {
    throw new RuntimeException('Decryption failed');
  }
  return $plain;
}

function crypto_encrypt_to_path(string $sourcePath, string $destPath): void {
  $data = file_get_contents($sourcePath);
  if ($data === false) throw new RuntimeException('Read failed');
  $enc = crypto_encrypt_string($data);
  if (file_put_contents($destPath, $enc) === false) {
    throw new RuntimeException('Write failed');
  }
}

function crypto_decrypt_to_path(string $sourcePath, string $destPath): void {
  $b64 = file_get_contents($sourcePath);
  if ($b64 === false) throw new RuntimeException('Read failed');
  $plain = crypto_decrypt_string($b64);
  if (file_put_contents($destPath, $plain) === false) {
    throw new RuntimeException('Write failed');
  }
}


