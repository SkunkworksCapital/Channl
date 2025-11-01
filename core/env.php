<?php
declare(strict_types=1);

function load_env(string $path): void {
  if (!is_file($path) || !is_readable($path)) {
    return;
  }
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) {
    return;
  }
  foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || $trimmed[0] === '#' || strpos($trimmed, ';//') === 0) {
      continue;
    }
    if (substr($trimmed, 0, 7) === 'export ') {
      $trimmed = substr($trimmed, 7);
    }
    $pos = strpos($trimmed, '=');
    if ($pos === false) {
      continue;
    }
    $name = rtrim(substr($trimmed, 0, $pos));
    $value = ltrim(substr($trimmed, $pos + 1));
    $wasQuoted = false;
    if ($value !== '' && ($value[0] === '"' || $value[0] === '\'')) {
      $wasQuoted = true;
      $q = $value[0];
      $len = strlen($value);
      if ($len >= 2 && substr($value, -1) === $q) {
        $value = substr($value, 1, -1);
      } else {
        $value = ltrim($value, $q);
      }
      $value = str_replace(['\\n', '\\r'], ["\n", "\r"], $value);
    }
    // If not quoted, strip inline comments (# or ;) and trailing whitespace
    if (!$wasQuoted) {
      $value = preg_replace('/\s*[#;].*$/', '', $value);
      $value = rtrim($value);
    }
    if ($name === '') {
      continue;
    }
    // Always override with .env value so app-level config wins over host-level vars
    $_ENV[$name] = $value;
    putenv($name . '=' . $value);
  }
}


