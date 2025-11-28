<?php
require_once '../config.php';

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Connected to database successfully\n";
    
    // Check if documents table exists and has data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_documents");
    $count = $stmt->fetch()['count'];
    echo "Total documents in database: $count\n";
    
    if ($count > 0) {
        echo "\nSample documents:\n";
        $stmt = $pdo->query("SELECT d.id, d.document_type, d.file_name, d.upload_status, a.reference_number, a.full_name 
                            FROM application_documents d 
                            JOIN applications a ON d.application_id = a.id 
                            LIMIT 5");
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($docs);
    } else {
        echo "No documents found. Let's check if there are any applications:\n";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM applications");
        $app_count = $stmt->fetch()['count'];
        echo "Total applications: $app_count\n";
        
        if ($app_count > 0) {
            echo "Sample applications:\n";
            $stmt = $pdo->query("SELECT id, reference_number, full_name, status FROM applications LIMIT 3");
            $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            print_r($apps);
        }
    }
    
    // Test the getDocuments function
    echo "\n--- Testing getDocuments function ---\n";
    
    // Include the function from dashboard.php
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
                'total_pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    $result = getDocuments($pdo);
    echo "getDocuments result:\n";
    print_r($result);
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>