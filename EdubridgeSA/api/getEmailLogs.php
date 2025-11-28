<?php
// Email Logs JSON Endpoint (auto-refresh for admin panel)
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

  // Prefer email_notifications; fallback to admin_messages
  $hasEmailNotifications = $pdo->query("SHOW TABLES LIKE 'email_notifications'")->rowCount() > 0;
  $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;

  if ($hasEmailNotifications) {
    $stmt = $pdo->prepare('SELECT id, application_id, subject, status, created_at FROM email_notifications ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $out['data'] = $stmt->fetchAll();
  } else {
    $hasAdminMessages = $pdo->query("SHOW TABLES LIKE 'admin_messages'")->rowCount() > 0;
    if ($hasAdminMessages) {
      $stmt = $pdo->prepare('SELECT id, sender, subject, is_read, created_at FROM admin_messages ORDER BY created_at DESC LIMIT ?');
      $stmt->bindValue(1, $limit, PDO::PARAM_INT);
      $stmt->execute();
      $out['data'] = $stmt->fetchAll();
    } else {
      $out['data'] = [];
    }
  }

  $out['success'] = true;
} catch (Throwable $e) {
  $out['success'] = false;
  $out['error'] = $e->getMessage();
}

echo json_encode($out);