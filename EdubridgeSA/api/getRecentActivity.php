<?php
// Recent Admin Activity JSON Endpoint
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$out = ['success' => false, 'data' => [], 'error' => null];
try {
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    throw new Exception('Unauthorized');
  }
  $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
  )");

  $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;
  $stmt = $pdo->prepare('SELECT admin_username, action, details, created_at FROM admin_activity_logs ORDER BY created_at DESC LIMIT ?');
  $stmt->bindValue(1, $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();

  $out['success'] = true;
  $out['data'] = $rows;
} catch (Throwable $e) {
  $out['success'] = false;
  $out['error'] = $e->getMessage();
}

echo json_encode($out);