<?php
declare(strict_types=1);

function view(string $template, array $data = []): void {
  $path = BASE_PATH . '/views/' . ltrim($template, '/');
  if (!str_ends_with($path, '.php')) {
    $path .= '.php';
  }
  if (!is_file($path)) {
    http_response_code(500);
    echo 'View not found: ' . h($template);
    return;
  }
  extract($data, EXTR_SKIP);
  require $path;
}


