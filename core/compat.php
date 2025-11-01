<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

if (!function_exists('str_ends_with')) {
  function str_ends_with(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    $needleLen = strlen($needle);
    if ($needleLen === 0) return true;
    return substr($haystack, -$needleLen) === $needle;
  }
}


