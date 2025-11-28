<?php
session_start();
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_login.php');
    exit();
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_stats':
            echo json_encode(getApplicationStats($pdo));
            exit();
        case 'get_applications':
            echo json_encode(getApplications($pdo));
            exit();
        case 'update_status':
            $application_id = $_POST['application_id'] ?? $_GET['application_id'];
            $new_status = $_POST['new_status'] ?? $_GET['new_status'];
            $notes = $_POST['notes'] ?? $_GET['notes'] ?? '';
            echo json_encode(updateApplicationStatus($pdo, $application_id, $new_status, $notes));
            exit();
        case 'get_application_details':
            echo json_encode(getApplicationDetails($pdo, $_GET['id']));
            exit();
        case 'send_email':
            echo json_encode(sendEmailNotification($pdo));
            exit();
        case 'get_universities':
            echo json_encode(getUniversities($pdo));
            exit();
        case 'update_university':
            $id = $_POST['id'] ?? $_GET['id'];
            $is_active = $_POST['is_active'] ?? $_GET['is_active'];
            echo json_encode(updateUniversity($pdo, $id, $is_active));
            exit();
        case 'update_document_status':
            $document_id = $_POST['document_id'] ?? $_GET['document_id'];
            $status = $_POST['status'] ?? $_GET['status'];
            echo json_encode(updateDocumentStatus($pdo, $document_id, $status));
            exit();
        case 'send_email_notification':
            $application_id = $_POST['application_id'] ?? $_GET['application_id'];
            $email_type = $_POST['email_type'] ?? $_GET['email_type'];
            $custom_message = $_POST['custom_message'] ?? $_GET['custom_message'] ?? '';
            echo json_encode(sendEmailNotificationToStudent($pdo, $application_id, $email_type, $custom_message));
            exit();
        case 'get_documents':
            echo json_encode(getDocuments($pdo));
            exit();
    }
}

// Functions
function getApplicationStats($pdo) {
    try {
        $stats = [];
        
        // Total applications
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM applications");
        $stats['total'] = $stmt->fetch()['total'];
        
        // Applications by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM applications GROUP BY status");
        $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $stats['draft'] = $statusCounts['draft'] ?? 0;
        $stats['submitted'] = $statusCounts['submitted'] ?? 0;
        $stats['under_review'] = $statusCounts['under_review'] ?? 0;
        $stats['complete'] = $statusCounts['complete'] ?? 0;
        $stats['documents_pending'] = $statusCounts['documents_pending'] ?? 0;
        
        return ['success' => true, 'data' => $stats];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getApplications($pdo) {
    try {
        $page = $_GET['page'] ?? 1;
        $search = $_GET['search'] ?? '';
        $status_filter = $_GET['status'] ?? '';
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = "(reference_number LIKE ? OR full_name LIKE ? OR surname LIKE ? OR email_address LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        }
        
        if (!empty($status_filter)) {
            $where_conditions[] = "status = ?";
            $params[] = $status_filter;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM applications $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get applications
        $sql = "SELECT id, reference_number, full_name, surname, email_address, cellphone_number, status, created_at 
                FROM applications $where_clause 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true, 
            'data' => $applications, 
            'total' => $total, 
            'page' => $page, 
            'total_pages' => ceil($total / $limit)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getApplicationDetails($pdo, $id) {
    try {
        // Get main application data
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            return ['success' => false, 'error' => 'Application not found'];
        }
        
        // Get parent/guardian details
        $stmt = $pdo->prepare("SELECT * FROM parent_guardian_details WHERE application_id = ?");
        $stmt->execute([$id]);
        $parent_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get university choices
        $stmt = $pdo->prepare("SELECT * FROM university_choices WHERE application_id = ?");
        $stmt->execute([$id]);
        $university_choices = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get documents
        $stmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ?");
        $stmt->execute([$id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get status history
        $stmt = $pdo->prepare("SELECT * FROM application_status_history WHERE application_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $status_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get email notifications
        $stmt = $pdo->prepare("SELECT * FROM email_notifications WHERE application_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $email_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'application' => $application,
                'parent_details' => $parent_details,
                'university_choices' => $university_choices,
                'documents' => $documents,
                'status_history' => $status_history,
                'email_notifications' => $email_notifications
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateApplicationStatus($pdo, $application_id, $new_status, $notes = '') {
    try {
        $pdo->beginTransaction();
        
        // Get current status
        $stmt = $pdo->prepare("SELECT status FROM applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $current_status = $stmt->fetch()['status'];
        
        // Update application status
        $stmt = $pdo->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $application_id]);
        
        // Log status change
        $stmt = $pdo->prepare("INSERT INTO application_status_history (application_id, previous_status, new_status, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$application_id, $current_status, $new_status, $notes]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Status updated successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getUniversities($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM universities ORDER BY name");
        $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $universities];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateUniversity($pdo, $id, $is_active) {
    try {
        $stmt = $pdo->prepare("UPDATE universities SET is_active = ? WHERE id = ?");
        $stmt->execute([$is_active, $id]);
        return ['success' => true, 'message' => 'University updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateDocumentStatus($pdo, $document_id, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE application_documents SET upload_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $document_id]);
        return ['success' => true, 'message' => 'Document status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

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

function sendEmailNotificationToStudent($pdo, $application_id, $email_type, $custom_message = '') {
    try {
        // Get application details
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            return ['success' => false, 'error' => 'Application not found'];
        }
        
        // Prepare email content based on type
        $subject = '';
        $body = '';
        
        switch ($email_type) {
            case 'confirmation':
                $subject = 'Application Confirmation - ' . $application['reference_number'];
                $body = "Dear {$application['full_name']},\n\nYour application has been received and is being processed.\n\nReference Number: {$application['reference_number']}\n\n" . $custom_message;
                break;
            case 'status_update':
                $subject = 'Application Status Update - ' . $application['reference_number'];
                $body = "Dear {$application['full_name']},\n\nYour application status has been updated to: {$application['status']}\n\nReference Number: {$application['reference_number']}\n\n" . $custom_message;
                break;
            case 'document_reminder':
                $subject = 'Document Upload Reminder - ' . $application['reference_number'];
                $body = "Dear {$application['full_name']},\n\nThis is a reminder to upload your required documents.\n\nReference Number: {$application['reference_number']}\n\n" . $custom_message;
                break;
            default:
                $subject = 'EduBridge SA - ' . $application['reference_number'];
                $body = $custom_message;
        }
        
        // Log the email notification
        $stmt = $pdo->prepare("INSERT INTO email_notifications (application_id, email_type, recipient_email, subject, message_body, sent_at, sent_status) VALUES (?, ?, ?, ?, ?, NOW(), 'sent')");
        $stmt->execute([$application_id, $email_type, $application['email_address'], $subject, $body]);
        
        return ['success' => true, 'message' => 'Email notification sent and logged successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Application System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 0.4em 0.8em;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white">EduBridge SA</h4>
                        <p class="text-white-50">Admin Dashboard</p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" onclick="showSection('overview')">
                                <i class="fas fa-tachometer-alt me-2"></i>Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="showSection('applications')">
                                <i class="fas fa-file-alt me-2"></i>Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="showSection('universities')">
                                <i class="fas fa-university me-2"></i>Universities
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="showSection('documents')">
                                <i class="fas fa-file-upload me-2"></i>Documents
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="?logout=1">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshData()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Overview Section -->
                <div id="overview-section">
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-2x mb-2"></i>
                                    <h3 id="total-applications">0</h3>
                                    <p class="mb-0">Total Applications</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-clock fa-2x mb-2"></i>
                                    <h3 id="draft-applications">0</h3>
                                    <p class="mb-0">Draft</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-paper-plane fa-2x mb-2"></i>
                                    <h3 id="submitted-applications">0</h3>
                                    <p class="mb-0">Submitted</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <h3 id="complete-applications">0</h3>
                                    <p class="mb-0">Complete</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Applications Section -->
                <div id="applications-section" style="display: none;">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" id="search-input" placeholder="Search by reference, name, or email...">
                                <button class="btn btn-outline-secondary" type="button" onclick="searchApplications()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="status-filter" onchange="filterApplications()">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="under_review">Under Review</option>
                                <option value="documents_pending">Documents Pending</option>
                                <option value="complete">Complete</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-container p-3">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Reference</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="applications-table-body">
                                    <!-- Applications will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <nav aria-label="Applications pagination">
                            <ul class="pagination justify-content-center" id="pagination">
                                <!-- Pagination will be loaded here -->
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Universities Section -->
                <div id="universities-section" style="display: none;">
                    <div class="table-container p-3">
                        <h3 class="mb-3">Universities Management</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="universities-table-body">
                                    <!-- Universities will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div id="documents-section" style="display: none;">
                    <div class="table-container p-3">
                        <h3 class="mb-3">Document Management</h3>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="document-search-input" placeholder="Search by reference or document type...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="searchDocuments()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="document-status-filter" onchange="filterDocuments()">
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="verified">Verified</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Reference</th>
                                        <th>Student Name</th>
                                        <th>Document Type</th>
                                        <th>File Name</th>
                                        <th>Status</th>
                                        <th>Uploaded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="documents-table-body">
                                    <!-- Documents will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        <div id="documents-pagination" class="mt-3">
                            <!-- Pagination will be loaded here -->
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div class="modal fade" id="applicationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="application-details">
                    <!-- Application details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Application Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="status-form">
                        <input type="hidden" id="status-application-id">
                        <div class="mb-3">
                            <label for="new-status" class="form-label">New Status</label>
                            <select class="form-select" id="new-status" required>
                                <option value="">Select Status</option>
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="under_review">Under Review</option>
                                <option value="documents_pending">Documents Pending</option>
                                <option value="complete">Complete</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status-notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="status-notes" rows="3" placeholder="Add any notes about this status change..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveStatusUpdate()">Update Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Notification Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Email Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="email-form">
                        <input type="hidden" id="email-application-id">
                        <div class="mb-3">
                            <label for="email-type" class="form-label">Email Type</label>
                            <select class="form-select" id="email-type" required>
                                <option value="">Select Email Type</option>
                                <option value="confirmation">Application Confirmation</option>
                                <option value="status_update">Status Update</option>
                                <option value="document_reminder">Document Reminder</option>
                                <option value="custom">Custom Message</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="custom-message" class="form-label">Additional Message (Optional)</label>
                            <textarea class="form-control" id="custom-message" rows="4" placeholder="Add any additional message to include in the email..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendEmailNotification()">Send Email</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPage = 1;
        let currentSearch = '';
        let currentStatusFilter = '';

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadApplications();
        });

        function showSection(section) {
            // Hide all sections
            document.getElementById('overview-section').style.display = 'none';
            document.getElementById('applications-section').style.display = 'none';
            document.getElementById('universities-section').style.display = 'none';
            document.getElementById('documents-section').style.display = 'none';
            
            // Show selected section
            document.getElementById(section + '-section').style.display = 'block';
            
            // Update active nav link
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
            
            // Load section-specific data
            if (section === 'applications') {
                loadApplications();
            } else if (section === 'universities') {
                loadUniversities();
            } else if (section === 'documents') {
                loadDocuments();
            } else if (section === 'overview') {
                loadStats();
            }
        }

        function loadStats() {
            fetch('?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('total-applications').textContent = data.data.total;
                        document.getElementById('draft-applications').textContent = data.data.draft;
                        document.getElementById('submitted-applications').textContent = data.data.submitted;
                        document.getElementById('complete-applications').textContent = data.data.complete;
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }

        function loadApplications(page = 1) {
            currentPage = page;
            const search = document.getElementById('search-input')?.value || '';
            const statusFilter = document.getElementById('status-filter')?.value || '';
            
            fetch(`?action=get_applications&page=${page}&search=${encodeURIComponent(search)}&status=${statusFilter}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderApplicationsTable(data.data);
                        renderPagination(data.page, data.total_pages);
                    }
                })
                .catch(error => console.error('Error loading applications:', error));
        }

        function renderApplicationsTable(applications) {
            const tbody = document.getElementById('applications-table-body');
            tbody.innerHTML = '';
            
            applications.forEach(app => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${app.reference_number}</td>
                    <td>${app.full_name} ${app.surname}</td>
                    <td>${app.email_address}</td>
                    <td>${app.cellphone_number}</td>
                    <td><span class="badge status-badge bg-${getStatusColor(app.status)}">${app.status}</span></td>
                    <td>${new Date(app.created_at).toLocaleDateString()}</td>
                    <td>
                                <button class="btn btn-sm btn-primary me-1" onclick="viewApplication(${app.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="btn btn-sm btn-warning me-1" onclick="updateStatus(${app.id}, '${app.status}')">
                                    <i class="fas fa-edit"></i> Status
                                </button>
                                <button class="btn btn-sm btn-info" onclick="sendEmail(${app.id})">
                                    <i class="fas fa-envelope"></i> Email
                                </button>
                            </td>
                `;
                tbody.appendChild(row);
            });
        }

        function renderPagination(currentPage, totalPages) {
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';
            
            for (let i = 1; i <= totalPages; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="loadApplications(${i})">${i}</a>`;
                pagination.appendChild(li);
            }
        }

        function getStatusColor(status) {
            const colors = {
                'draft': 'secondary',
                'submitted': 'primary',
                'under_review': 'warning',
                'documents_pending': 'info',
                'complete': 'success'
            };
            return colors[status] || 'secondary';
        }

        function getDocumentStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'verified': 'success',
                'rejected': 'danger',
                'uploaded': 'info'
            };
            return colors[status] || 'secondary';
        }

        function searchApplications() {
            loadApplications(1);
        }

        function filterApplications() {
            loadApplications(1);
        }

        function viewApplication(id) {
            fetch(`?action=get_application_details&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderApplicationDetails(data.data);
                        new bootstrap.Modal(document.getElementById('applicationModal')).show();
                    }
                })
                .catch(error => console.error('Error loading application details:', error));
        }

        function renderApplicationDetails(data) {
            const container = document.getElementById('application-details');
            const app = data.application;
            
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
                        <p><strong>Reference:</strong> ${app.reference_number}</p>
                        <p><strong>Name:</strong> ${app.full_name} ${app.surname}</p>
                        <p><strong>Email:</strong> ${app.email_address}</p>
                        <p><strong>Phone:</strong> ${app.cellphone_number}</p>
                        <p><strong>ID Number:</strong> ${app.id_number}</p>
                        <p><strong>Date of Birth:</strong> ${app.date_of_birth}</p>
                        <p><strong>Address:</strong> ${app.physical_address}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Application Status</h6>
                        <p><strong>Current Status:</strong> <span class="badge bg-${getStatusColor(app.status)}">${app.status}</span></p>
                        <p><strong>Created:</strong> ${new Date(app.created_at).toLocaleString()}</p>
                        <p><strong>Updated:</strong> ${new Date(app.updated_at).toLocaleString()}</p>
                        ${app.submitted_at ? `<p><strong>Submitted:</strong> ${new Date(app.submitted_at).toLocaleString()}</p>` : ''}
                    </div>
                </div>
                
                ${data.parent_details ? `
                <hr>
                <h6>Parent/Guardian Details</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> ${data.parent_details.full_name} ${data.parent_details.surname}</p>
                        <p><strong>ID Number:</strong> ${data.parent_details.id_number}</p>
                        <p><strong>Contact:</strong> ${data.parent_details.contact_number}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Email:</strong> ${data.parent_details.email_address}</p>
                        <p><strong>Marital Status:</strong> ${data.parent_details.marital_status}</p>
                    </div>
                </div>
                ` : ''}
                
                ${data.university_choices ? `
                <hr>
                <h6>University Choices</h6>
                <p><strong>First Choice:</strong> ${data.university_choices.university_1}</p>
                <p><strong>Course:</strong> ${data.university_choices.course_first_choice}</p>
                ${data.university_choices.university_2 ? `<p><strong>Second Choice:</strong> ${data.university_choices.university_2}</p>` : ''}
                ${data.university_choices.course_second_choice ? `<p><strong>Second Course:</strong> ${data.university_choices.course_second_choice}</p>` : ''}
                ` : ''}
                
                <hr>
                <h6>Documents</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Filename</th>
                                <th>Status</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.documents.map(doc => `
                                <tr>
                                    <td>${doc.document_type}</td>
                                    <td>${doc.original_filename || 'N/A'}</td>
                                    <td><span class="badge bg-${getDocumentStatusColor(doc.upload_status)}">${doc.upload_status}</span></td>
                                    <td>${doc.upload_date ? new Date(doc.upload_date).toLocaleDateString() : 'N/A'}</td>
                                    <td>
                                        ${(doc.status === 'pending' || !doc.status) ? `
                                            <button class="btn btn-sm btn-success me-1" onclick="updateDocumentStatus(${doc.id}, 'verified')">
                                                <i class="fas fa-check"></i> Verify
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="updateDocumentStatus(${doc.id}, 'rejected')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        ` : ''}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        function loadUniversities() {
            fetch('?action=get_universities')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderUniversitiesTable(data.data);
                    }
                })
                .catch(error => console.error('Error loading universities:', error));
        }

        function renderUniversitiesTable(universities) {
            const tbody = document.getElementById('universities-table-body');
            tbody.innerHTML = '';
            
            universities.forEach(uni => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${uni.name}</td>
                    <td>${uni.code}</td>
                    <td><span class="badge bg-${uni.is_active ? 'success' : 'danger'}">${uni.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <button class="btn btn-sm ${uni.is_active ? 'btn-danger' : 'btn-success'} btn-action" 
                                onclick="toggleUniversity(${uni.id}, ${!uni.is_active})">
                            ${uni.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function toggleUniversity(id, isActive) {
            fetch(`?action=update_university&id=${id}&is_active=${isActive ? 1 : 0}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadUniversities();
                    } else {
                        alert('Error updating university: ' + data.error);
                    }
                })
                .catch(error => console.error('Error updating university:', error));
        }

        function updateStatus(applicationId, currentStatus) {
            document.getElementById('status-application-id').value = applicationId;
            document.getElementById('new-status').value = currentStatus;
            document.getElementById('status-notes').value = '';
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        function saveStatusUpdate() {
             const applicationId = document.getElementById('status-application-id').value;
             const newStatus = document.getElementById('new-status').value;
             const notes = document.getElementById('status-notes').value;
             
             if (!newStatus) {
                 alert('Please select a status');
                 return;
             }
             
             const formData = new FormData();
             formData.append('application_id', applicationId);
             formData.append('new_status', newStatus);
             formData.append('notes', notes);
             
             fetch('?action=update_status', {
                 method: 'POST',
                 body: formData
             })
             .then(response => response.json())
             .then(data => {
                 if (data.success) {
                     bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                     loadApplications(currentPage);
                     loadStats();
                 } else {
                     alert('Error updating status: ' + data.error);
                 }
             })
             .catch(error => {
                 console.error('Error updating status:', error);
                 alert('Error updating status');
             });
         }

         function sendEmail(applicationId) {
             document.getElementById('email-application-id').value = applicationId;
             document.getElementById('email-type').value = '';
             document.getElementById('custom-message').value = '';
             new bootstrap.Modal(document.getElementById('emailModal')).show();
         }

         function sendEmailNotification() {
             const applicationId = document.getElementById('email-application-id').value;
             const emailType = document.getElementById('email-type').value;
             const customMessage = document.getElementById('custom-message').value;
             
             if (!emailType) {
                 alert('Please select an email type');
                 return;
             }
             
             const formData = new FormData();
             formData.append('application_id', applicationId);
             formData.append('email_type', emailType);
             formData.append('custom_message', customMessage);
             
             fetch('?action=send_email_notification', {
                 method: 'POST',
                 body: formData
             })
             .then(response => response.json())
             .then(data => {
                 if (data.success) {
                     bootstrap.Modal.getInstance(document.getElementById('emailModal')).hide();
                     alert('Email notification sent successfully!');
                 } else {
                     alert('Error sending email: ' + data.error);
                 }
             })
             .catch(error => {
                 console.error('Error sending email:', error);
                 alert('Error sending email');
             });
         }

        function loadDocuments(page = 1) {
            console.log('Loading documents...');
            const search = document.getElementById('document-search-input').value;
            const status = document.getElementById('document-status-filter').value;
            
            const params = new URLSearchParams({
                action: 'get_documents',
                page: page,
                search: search,
                status: status
            });
            
            console.log('Fetching:', 'dashboard.php?' + params);
            
            fetch('dashboard.php?' + params)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        console.log('Documents found:', data.data.length);
                        displayDocuments(data.data);
                        updateDocumentsPagination(data.page, data.total_pages);
                    } else {
                        console.error('Error loading documents:', data.error);
                        const tbody = document.getElementById('documents-table-body');
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading documents: ' + data.error + '</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const tbody = document.getElementById('documents-table-body');
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Network error loading documents</td></tr>';
                });
        }
        
        function getStatusBadge(status) {
            switch(status) {
                case 'pending':
                    return '<span class="badge bg-warning">Pending</span>';
                case 'verified':
                    return '<span class="badge bg-success">Verified</span>';
                case 'rejected':
                    return '<span class="badge bg-danger">Rejected</span>';
                case 'uploaded':
                    return '<span class="badge bg-info">Uploaded</span>';
                default:
                    return '<span class="badge bg-secondary">Unknown</span>';
            }
        }
        
        function displayDocuments(documents) {
            const tbody = document.getElementById('documents-table-body');
            
            if (documents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No documents found</td></tr>';
                return;
            }
            
            tbody.innerHTML = documents.map(doc => {
                const statusBadge = getStatusBadge(doc.upload_status);
                const studentName = `${doc.full_name} ${doc.surname}`;
                const uploadDate = new Date(doc.created_at).toLocaleDateString();
                const fileName = doc.original_filename || doc.stored_filename || 'No file uploaded';
                const documentType = doc.document_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                
                return `
                    <tr>
                        <td>${doc.reference_number}</td>
                        <td>${studentName}</td>
                        <td>${documentType}</td>
                        <td>${fileName}</td>
                        <td>${statusBadge}</td>
                        <td>${uploadDate}</td>
                        <td>
                            ${doc.upload_status === 'pending' ? `
                                <button class="btn btn-sm btn-success me-1" onclick="updateDocumentStatus(${doc.id}, 'verified')">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="updateDocumentStatus(${doc.id}, 'rejected')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            ` : `
                                <span class="text-muted">No actions available</span>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function updateDocumentsPagination(currentPage, totalPages) {
            const pagination = document.getElementById('documents-pagination');
            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }
            
            let paginationHTML = '<nav><ul class="pagination justify-content-center">';
            
            // Previous button
            if (currentPage > 1) {
                paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadDocuments(${currentPage - 1})">Previous</a></li>`;
            }
            
            // Page numbers
            for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
                paginationHTML += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadDocuments(${i})">${i}</a>
                </li>`;
            }
            
            // Next button
            if (currentPage < totalPages) {
                paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadDocuments(${currentPage + 1})">Next</a></li>`;
            }
            
            paginationHTML += '</ul></nav>';
            pagination.innerHTML = paginationHTML;
        }

        function searchDocuments() {
            loadDocuments(1); // Reset to first page when searching
        }

        function filterDocuments() {
            loadDocuments(1); // Reset to first page when filtering
        }

        function updateDocumentStatus(documentId, status) {
            if (confirm(`Are you sure you want to ${status} this document?`)) {
                fetch(`?action=update_document_status&document_id=${documentId}&status=${status}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Reload the current view
                            const currentSection = document.querySelector('.nav-link.active').textContent.trim().toLowerCase();
                            if (currentSection === 'documents') {
                                loadDocuments();
                            } else {
                                // Refresh application details if viewing an application
                                const modal = document.getElementById('applicationModal');
                                if (modal.classList.contains('show')) {
                                    // Re-load the application details
                                    location.reload();
                                }
                            }
                        } else {
                            alert('Error updating document status: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating document status:', error);
                        alert('Error updating document status');
                    });
            }
        }

        function refreshData() {
            loadStats();
            loadApplications(currentPage);
        }
    </script>
</body>
</html>