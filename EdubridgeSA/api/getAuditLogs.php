<?php
// Audit Logs JSON Endpoint with secure pagination
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$out = ['success' => false, 'data' => [], 'page' => 1, 'total_pages' => 1, 'total' => 0, 'error' => null];

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

  $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
  if ($page < 1) $page = 1;
  if ($limit < 1) $limit = 10;
  if ($limit > 50) $limit = 50; // cap for shared hosting
  $offset = ($page - 1) * $limit;

  $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_activity_logs')->fetchColumn();
  $out['total'] = $count;
  $out['total_pages'] = max(1, (int)ceil($count / $limit));
  $out['page'] = min($page, $out['total_pages']);
  if ($offset >= $count) $offset = max(0, ($out['total_pages'] - 1) * $limit);

  $stmt = $pdo->prepare('SELECT admin_username, action, details, ip_address, created_at FROM admin_activity_logs ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
  $stmt->bindValue(1, $limit, PDO::PARAM_INT);
  $stmt->bindValue(2, $offset, PDO::PARAM_INT);
  $stmt->execute();
  $out['data'] = $stmt->fetchAll();
  $out['success'] = true;
} catch (Throwable $e) {
  $out['success'] = false;
  $out['error'] = $e->getMessage();
}

echo json_encode($out);