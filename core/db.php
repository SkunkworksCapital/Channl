<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) {
    return $pdo;
  }

  // Load global config loaded by bootstrap
  if (!isset($GLOBALS['CONFIG'])) {
    $GLOBALS['CONFIG'] = require __DIR__ . '/config.php';
  }
  $cfg = $GLOBALS['CONFIG']['db'];

  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], (int)$cfg['port'], $cfg['name'], $cfg['charset']);
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_STRINGIFY_FETCHES => false,
  ];
  $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
  return $pdo;
}


