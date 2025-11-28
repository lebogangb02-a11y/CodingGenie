<?php
// System Health JSON Endpoint
// Returns db_online, table_count, response_ms (latency)
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$out = ['success' => false, 'data' => ['db_online' => false, 'table_count' => 0, 'response_ms' => 0], 'error' => null];

$start = microtime(true);
try {
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    throw new Exception('Unauthorized');
  }

  $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // Lightweight ping: SHOW TABLES count
  $tables = (int)$pdo->query('SHOW TABLES')->rowCount();
  $out['data']['db_online'] = true;
  $out['data']['table_count'] = $tables;
  $out['success'] = true;
} catch (Throwable $e) {
  $out['success'] = false;
  $out['error'] = $e->getMessage();
}

$out['data']['response_ms'] = (int)round((microtime(true) - $start) * 1000);
echo json_encode($out);