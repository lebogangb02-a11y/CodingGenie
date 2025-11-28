<?php
// Admin Stats JSON Endpoint
// Returns aggregated metrics for the admin dashboard with fast, index-friendly queries
// Hostinger PHP 8.2 compatible

declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

$response = [
  'success' => false,
  'error' => null,
  'data' => []
];

try {
  // Ensure only logged-in admins can query
  if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    throw new Exception('Unauthorized');
  }

  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_PERSISTENT => false,
    ]
  );

  // Detect key columns dynamically to support existing schema
  $applicationsTableExists = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount() > 0;
  if (!$applicationsTableExists) {
    throw new Exception('Applications table not found');
  }

  $cols = $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
  $createdCandidates = ['created_at','submitted_at','created_on','date_created','timestamp','updated_at'];
  $statusCandidates  = ['status','application_status'];
  $createdCol = null; $statusCol = null;
  foreach ($createdCandidates as $c) { if (in_array($c, $cols, true)) { $createdCol = $c; break; } }
  foreach ($statusCandidates as $s) { if (in_array($s, $cols, true)) { $statusCol = $s; break; } }

  // Defaults if not found
  if (!$createdCol) $createdCol = 'created_at';
  if (!$statusCol)  $statusCol = 'status';

  // Precompute date ranges (index-friendly)
  $startToday = date('Y-m-d 00:00:00');
  $startMonth = date('Y-m-01 00:00:00');
  $startYear  = date('Y-01-01 00:00:00');

  // Total count
  $total = (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();

  // Date window counts using range comparisons (better for indexes)
  $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE `$createdCol` >= ?");
  $stmtToday->execute([$startToday]);
  $today = (int)$stmtToday->fetchColumn();

  $stmtMonth = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE `$createdCol` >= ?");
  $stmtMonth->execute([$startMonth]);
  $month = (int)$stmtMonth->fetchColumn();

  $stmtYear = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE `$createdCol` >= ?");
  $stmtYear->execute([$startYear]);
  $year = (int)$stmtYear->fetchColumn();

  // Status buckets
  $stmtStatus = $pdo->query("SELECT `$statusCol` AS s, COUNT(*) AS c FROM applications GROUP BY `$statusCol`");
  $statusCounts = [];
  foreach ($stmtStatus->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $key = strtolower(trim((string)$row['s']));
    if ($key !== '') $statusCounts[$key] = (int)$row['c'];
  }
  $pending  = ($statusCounts['pending'] ?? 0) + ($statusCounts['under_review'] ?? 0) + ($statusCounts['review'] ?? 0) + ($statusCounts['awaiting_review'] ?? 0);
  $approved = $statusCounts['approved'] ?? ($statusCounts['accepted'] ?? 0);
  $rejected = ($statusCounts['rejected'] ?? 0) + ($statusCounts['declined'] ?? 0);

  // System health
  $health = [
    'dbOnline' => true,
    'applicationsTableExists' => true,
    'totalTables' => (int)$pdo->query('SHOW TABLES')->rowCount(),
  ];

  // Optional: unread admin messages
  $unreadMessages = 0;
  try {
    $messagesTableExists = $pdo->query("SHOW TABLES LIKE 'admin_messages'")->rowCount() > 0;
    if ($messagesTableExists) {
      $unreadMessages = (int)$pdo->query('SELECT COUNT(*) FROM admin_messages WHERE is_read = 0')->fetchColumn();
    }
  } catch (Throwable $e) { /* ignore */ }

  $response['success'] = true;
  $response['data'] = [
    'total' => $total,
    'pending' => $pending,
    'approved' => $approved,
    'rejected' => $rejected,
    'today' => $today,
    'month' => $month,
    'year' => $year,
    'unreadMessages' => $unreadMessages,
    'health' => $health
  ];
} catch (Throwable $e) {
  http_response_code(200); // return 200 with error payload for graceful UI fallback
  $response['success'] = false;
  $response['error'] = $e->getMessage();
}

echo json_encode($response);