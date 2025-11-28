<?php
// Lightweight endpoint returning only pending count for quick badge updates
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

 $out = ['success' => false, 'data' => ['pending' => 0], 'error' => null];
try {
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    throw new Exception('Unauthorized');
  }
  $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  $cols = $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
  $statusCol = in_array('status', $cols, true) ? 'status' : (in_array('application_status', $cols, true) ? 'application_status' : 'status');

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE LOWER(`$statusCol`) IN ('pending','under_review','review','awaiting_review')");
  $stmt->execute();
  $out['success'] = true;
  $out['data']['pending'] = (int)$stmt->fetchColumn();
} catch (Throwable $e) {
  $out['success'] = false;
  $out['error'] = $e->getMessage();
}

 echo json_encode($out);