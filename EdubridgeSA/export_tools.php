<?php
// export_tools.php - Enhanced Data Export Tools
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Use your existing config
require_once __DIR__ . '/config.php';

// Verify DB connection
$pdo_ok = isset($pdo) && $pdo instanceof PDO;
if (!$pdo_ok) {
    die("Database connection failed. Please check config.php credentials.");
}

// Handle export requests
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    if ($type === 'students_csv') {
        exportStudentsCSV();
    } elseif ($type === 'statistics_excel') {
        exportStatisticsExcel();
    } elseif ($type === 'database_backup') {
        backupDatabase();
    } elseif ($type === 'documents_archive') {
        downloadDocumentsArchive();
    }
}

function exportStudentsCSV() {
    global $pdo;
    
    try {
        // Discover available columns
        $cols = $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
        
        // Build column mapping for CSV
        $columnMap = [
            'id' => 'ID',
            'application_ref' => 'Application Reference',
            'reference_number' => 'Reference Number',
            'full_name' => 'Full Name',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'surname' => 'Surname',
            'email_address' => 'Email',
            'email' => 'Email',
            'cellphone_number' => 'Phone',
            'phone' => 'Phone',
            'id_number' => 'ID Number',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'nationality' => 'Nationality',
            'address' => 'Address',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => 'Postal Code',
            'country' => 'Country',
            'program_choice_1' => 'Program Choice 1',
            'program_choice_2' => 'Program Choice 2',
            'program_choice_3' => 'Program Choice 3',
            'high_school_name' => 'High School',
            'matric_year' => 'Matric Year',
            'aps' => 'APS Score',
            'status' => 'Status',
            'application_status' => 'Application Status',
            'created_at' => 'Created At',
            'submitted_at' => 'Submitted At'
        ];
        
        // Filter only existing columns
        $selectedColumns = [];
        $headers = [];
        foreach ($columnMap as $dbCol => $displayName) {
            if (in_array($dbCol, $cols)) {
                $selectedColumns[] = $dbCol;
                $headers[] = $displayName;
            }
        }
        
        // Add any missing important columns
        if (!in_array('status', $selectedColumns) && in_array('application_status', $cols)) {
            $selectedColumns[] = 'application_status';
            $headers[] = 'Application Status';
        }
        
        if (empty($selectedColumns)) {
            $selectedColumns = ['id', 'full_name', 'email_address', 'status', 'created_at'];
            $headers = ['ID', 'Full Name', 'Email', 'Status', 'Created At'];
        }
        
        $columnsSql = implode(', ', $selectedColumns);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=students_export_' . date('Y-m-d_His') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 compatibility with Excel
        fputs($output, "\xEF\xBB\xBF");
        
        // Write headers
        fputcsv($output, $headers);
        
        // Fetch and write data
        $stmt = $pdo->query("SELECT $columnsSql FROM applications ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log("CSV Export Error: " . $e->getMessage());
        die("Error generating CSV export: " . $e->getMessage());
    }
}

function exportStatisticsExcel() {
    global $pdo;
    
    try {
        // Get statistics data
        $stats = [
            'total_applications' => $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
            'pending_applications' => $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Pending' OR application_status = 'pending'")->fetchColumn(),
            'approved_applications' => $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Accepted' OR status = 'Approved' OR application_status = 'approved'")->fetchColumn(),
            'rejected_applications' => $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Rejected' OR application_status = 'rejected'")->fetchColumn(),
            'today_applications' => $pdo->query("SELECT COUNT(*) FROM applications WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
            'month_applications' => $pdo->query("SELECT COUNT(*) FROM applications WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn(),
        ];
        
        // Program statistics
        $programStats = $pdo->query("
            SELECT program_choice_1, COUNT(*) as count 
            FROM applications 
            WHERE program_choice_1 IS NOT NULL AND program_choice_1 != '' 
            GROUP BY program_choice_1 
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Status breakdown
        $statusStats = $pdo->query("
            SELECT COALESCE(status, application_status) as status, COUNT(*) as count 
            FROM applications 
            GROUP BY COALESCE(status, application_status)
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Generate simple CSV as Excel (for now)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=statistics_report_' . date('Y-m-d_His') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        
        fputcsv($output, ['EduBridgeSA - Application Statistics Report']);
        fputcsv($output, ['Generated on:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        fputcsv($output, ['Overall Statistics']);
        fputcsv($output, ['Total Applications', $stats['total_applications']]);
        fputcsv($output, ['Pending Applications', $stats['pending_applications']]);
        fputcsv($output, ['Approved Applications', $stats['approved_applications']]);
        fputcsv($output, ['Rejected Applications', $stats['rejected_applications']]);
        fputcsv($output, ["Today's Applications", $stats['today_applications']]);
        fputcsv($output, ['This Month Applications', $stats['month_applications']]);
        fputcsv($output, []);
        
        fputcsv($output, ['Program Popularity']);
        fputcsv($output, ['Program', 'Count']);
        foreach ($programStats as $program) {
            fputcsv($output, [$program['program_choice_1'], $program['count']]);
        }
        fputcsv($output, []);
        
        fputcsv($output, ['Status Breakdown']);
        fputcsv($output, ['Status', 'Count']);
        foreach ($statusStats as $status) {
            fputcsv($output, [$status['status'], $status['count']]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        error_log("Statistics Export Error: " . $e->getMessage());
        die("Error generating statistics report: " . $e->getMessage());
    }
}

function backupDatabase() {
    global $pdo;
    
    try {
        // Get database name from config
        $dbName = DB_NAME;
        $backupFile = $dbName . '_backup_' . date('Y-m-d_His') . '.sql';
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $backupFile);
        
        // Get all tables
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        
        $output = "";
        
        foreach ($tables as $table) {
            // Drop table
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Create table
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
            $output .= $createTable['Create Table'] . ";\n\n";
            
            // Insert data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                $output .= "INSERT INTO `$table` VALUES ";
                $insertValues = [];
                
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($pdo) {
                        if ($value === null) return 'NULL';
                        return $pdo->quote($value);
                    }, $row);
                    
                    $insertValues[] = "(" . implode(', ', $values) . ")";
                }
                
                $output .= implode(",\n", $insertValues) . ";\n\n";
            }
        }
        
        echo $output;
        exit;
        
    } catch (Exception $e) {
        error_log("Database Backup Error: " . $e->getMessage());
        die("Error creating database backup: " . $e->getMessage());
    }
}

function downloadDocumentsArchive() {
    $uploadDir = __DIR__ . '/uploads/';
    
    if (!is_dir($uploadDir)) {
        die("Uploads directory not found.");
    }
    
    // Create temporary zip file
    $zipFile = tempnam(sys_get_temp_dir(), 'documents_archive_') . '.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        die("Cannot create zip file");
    }
    
    // Add files to zip
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    $fileCount = 0;
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($uploadDir));
            
            if ($zip->addFile($filePath, $relativePath)) {
                $fileCount++;
            }
        }
    }
    
    $zip->close();
    
    if ($fileCount === 0) {
        unlink($zipFile);
        die("No documents found to archive.");
    }
    
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="documents_archive_' . date('Y-m-d_His') . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    
    readfile($zipFile);
    
    // Clean up
    unlink($zipFile);
    exit;
}

// Get statistics for display
try {
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    $pendingStudents = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Pending' OR application_status = 'pending' OR application_status = 'under_review'")->fetchColumn();
    $approvedStudents = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'Accepted' OR status = 'Approved' OR application_status = 'approved'")->fetchColumn();
    $todayStudents = $pdo->query("SELECT COUNT(*) FROM applications WHERE DATE(created_at) = CURDATE()")->fetchColumn();
} catch (Exception $e) {
    $totalStudents = $pendingStudents = $approvedStudents = $todayStudents = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Tools - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-shadow { 
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); 
            border: 1px solid rgba(0,0,0,0.125);
        }
        .navbar { background-color: #2c3e50; }
        .export-card { 
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .export-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .btn-export {
            min-width: 140px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-download me-2"></i>Export Tools
        </span>
        <div>
            <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
            <a href="admin_logout.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row g-4">
        <!-- Student Data Export -->
        <div class="col-md-6 col-lg-3">
            <div class="card card-shadow export-card">
                <div class="card-body text-center">
                    <i class="bi bi-people display-4 text-primary mb-3"></i>
                    <h5 class="card-title">Student Data Export</h5>
                    <p class="text-muted small">Export all student applications to CSV format</p>
                    <a href="?export=students_csv" class="btn btn-primary btn-export">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export to CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Export -->
        <div class="col-md-6 col-lg-3">
            <div class="card card-shadow export-card">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up display-4 text-success mb-3"></i>
                    <h5 class="card-title">Statistics Report</h5>
                    <p class="text-muted small">Generate application statistics and analytics</p>
                    <a href="?export=statistics_excel" class="btn btn-success btn-export">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
                    </a>
                </div>
            </div>
        </div>

        <!-- Database Backup -->
        <div class="col-md-6 col-lg-3">
            <div class="card card-shadow export-card">
                <div class="card-body text-center">
                    <i class="bi bi-database display-4 text-warning mb-3"></i>
                    <h5 class="card-title">Database Backup</h5>
                    <p class="text-muted small">Create a complete database backup</p>
                    <a href="?export=database_backup" class="btn btn-warning btn-export">
                        <i class="bi bi-archive me-1"></i>Backup Database
                    </a>
                </div>
            </div>
        </div>

        <!-- Document Archive -->
        <div class="col-md-6 col-lg-3">
            <div class="card card-shadow export-card">
                <div class="card-body text-center">
                    <i class="bi bi-folder display-4 text-info mb-3"></i>
                    <h5 class="card-title">Document Archive</h5>
                    <p class="text-muted small">Download all uploaded documents as ZIP</p>
                    <a href="?export=documents_archive" class="btn btn-info btn-export">
                        <i class="bi bi-file-zip me-1"></i>Download Archive
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-shadow">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-speedometer me-2"></i>Export Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="stat-number text-primary"><?= $totalStudents ?></div>
                            <small class="text-muted">Total Students</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-number text-warning"><?= $pendingStudents ?></div>
                            <small class="text-muted">Pending Applications</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-number text-success"><?= $approvedStudents ?></div>
                            <small class="text-muted">Approved Applications</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="stat-number text-info"><?= $todayStudents ?></div>
                            <small class="text-muted">Today's Applications</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Instructions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-shadow">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Export Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>CSV Export</h6>
                            <ul class="small">
                                <li>Includes all student application data</li>
                                <li>Compatible with Excel, Google Sheets</li>
                                <li>UTF-8 encoded for special characters</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Database Backup</h6>
                            <ul class="small">
                                <li>Complete SQL dump of all tables</li>
                                <li>Includes structure and data</li>
                                <li>Can be restored using phpMyAdmin or MySQL CLI</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Add loading states to export buttons
    document.addEventListener('DOMContentLoaded', function() {
        const exportLinks = document.querySelectorAll('a[href*="export="]');
        exportLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Preparing...';
                this.classList.add('disabled');
                
                // Revert after 10 seconds if still on page
                setTimeout(() => {
                    if (this.classList.contains('disabled')) {
                        this.innerHTML = originalText;
                        this.classList.remove('disabled');
                    }
                }, 10000);
            });
        });
    });
</script>
</body>
</html>