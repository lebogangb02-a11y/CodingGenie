<?php
// One-off migration to ensure 'documents' table exists
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query("SHOW TABLES LIKE 'documents'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'documents' already exists.\n";
        exit;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id INT UNSIGNED NOT NULL,
  doc_type VARCHAR(100) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) DEFAULT NULL,
  file_size BIGINT UNSIGNED DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'pending',
  notes TEXT DEFAULT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_documents_application_id (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    $pdo->exec($sql);
    echo "Table 'documents' created successfully.\n";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}