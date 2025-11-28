<?php
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Simulate the get_documents AJAX call
    $_GET['action'] = 'get_documents';
    $_GET['page'] = $_GET['page'] ?? 1;
    $_GET['search'] = $_GET['search'] ?? '';
    $_GET['status'] = $_GET['status'] ?? '';
    
    // Include the getDocuments function from dashboard.php
    function getDocuments($pdo) {
        try {
            $page = $_GET['page'] ?? 1;
            $search = $_GET['search'] ?? '';
            $status_filter = $_GET['status'] ?? '';
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $where_conditions = [];
            $params = [];
            
            if (!empty($search)) {
                $where_conditions[] = "(a.reference_number LIKE ? OR a.full_name LIKE ? OR a.surname LIKE ? OR d.document_type LIKE ?)";
                $search_param = "%$search%";
                $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            }
            
            if (!empty($status_filter)) {
                $where_conditions[] = "d.upload_status = ?";
                $params[] = $status_filter;
            }
            
            $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
            
            // Check if table exists first
            $stmt = $pdo->query("SHOW TABLES LIKE 'application_documents'");
            if ($stmt->rowCount() == 0) {
                return ['success' => false, 'error' => 'application_documents table does not exist'];
            }
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM application_documents d 
                          JOIN applications a ON d.application_id = a.id 
                          $where_clause";
            $stmt = $pdo->prepare($count_sql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get documents with application details
            $sql = "SELECT d.*, a.reference_number, a.full_name, a.surname 
                    FROM application_documents d 
                    JOIN applications a ON d.application_id = a.id 
                    $where_clause 
                    ORDER BY d.created_at DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true, 
                'data' => $documents, 
                'total' => $total, 
                'page' => $page, 
                'total_pages' => ceil($total / $limit),
                'debug' => [
                    'sql' => $sql,
                    'params' => $params,
                    'where_clause' => $where_clause
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    $result = getDocuments($pdo);
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
?>