<?php
// contact_enquiries.php - Manage Student Enquiries
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';

admin_require_login();

$username = $_SESSION['admin_username'] ?? 'admin';
$enquiries = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side CSRF enforcement (no-op if helper not available)
    if (function_exists('require_csrf')) {
        require_csrf();
    }
    if (isset($_POST['update_enquiry_status']) && isset($_POST['enquiry_id'])) {
        $enquiryId = (int)$_POST['enquiry_id'];
        $status = $_POST['status'] ?? 'new';
        $assignedTo = $_POST['assigned_to'] ?? null;

        try {
            $stmt = $pdo->prepare('UPDATE contact_enquiries SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $assignedTo, $enquiryId]);

            // Log activity
            $logStmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)');
            $logStmt->execute([$username, 'Update Enquiry', "Updated enquiry #$enquiryId to $status", $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Throwable $e) {
            // Silently fail
        }
    }

    if (isset($_POST['delete_enquiry']) && isset($_POST['enquiry_id'])) {
        $enquiryId = (int)$_POST['enquiry_id'];
        try {
            $stmt = $pdo->prepare('DELETE FROM contact_enquiries WHERE id = ?');
            $stmt->execute([$enquiryId]);
        } catch (Throwable $e) {
            // Silently fail
        }
    }
}

// Load enquiries
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $filter = $_GET['status'] ?? 'all';
        $type = $_GET['type'] ?? 'all';

        $sql = "SELECT id, name, email, subject, message, enquiry_type, status, assigned_to, created_at FROM contact_enquiries WHERE 1=1";
        $params = [];

        if ($filter !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $filter;
        }

        if ($type !== 'all') {
            $sql .= " AND enquiry_type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $enquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // Silently fail
    }
}

admin_header('Student Enquiries');
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">
                    <i class="bi bi-envelope me-2"></i>Student Enquiries
                </h4>
                <div class="btn-group">
                    <a href="?status=all" class="btn btn-outline-primary <?= ($_GET['status'] ?? 'all') === 'all' ? 'active' : '' ?>">All</a>
                    <a href="?status=new" class="btn btn-outline-danger <?= ($_GET['status'] ?? '') === 'new' ? 'active' : '' ?>">New</a>
                    <a href="?status=in_progress" class="btn btn-outline-warning <?= ($_GET['status'] ?? '') === 'in_progress' ? 'active' : '' ?>">In Progress</a>
                    <a href="?status=resolved" class="btn btn-outline-success <?= ($_GET['status'] ?? '') === 'resolved' ? 'active' : '' ?>">Resolved</a>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($enquiries)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-envelope-open display-1 text-muted"></i>
                <h5 class="mt-3 text-muted">No enquiries found</h5>
                <p class="text-muted">Student enquiries will appear here when they contact the institution.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($enquiries as $enquiry): ?>
                <div class="col-lg-6 mb-4">
                    <div class="card shadow-sm h-100 enquiry-<?= $enquiry['status'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title"><?= htmlspecialchars($enquiry['subject']) ?></h5>
                                <span class="badge bg-<?= $enquiry['status'] === 'new' ? 'danger' : ($enquiry['status'] === 'in_progress' ? 'warning' : 'success') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $enquiry['status'])) ?>
                                </span>
                            </div>

                            <p class="card-text"><?= nl2br(htmlspecialchars($enquiry['message'])) ?></p>

                            <div class="enquiry-meta mb-3">
                                <div class="row small text-muted">
                                    <div class="col-6">
                                        <strong>From:</strong><br>
                                        <?= htmlspecialchars($enquiry['student_name']) ?><br>
                                        <?= htmlspecialchars($enquiry['student_email']) ?><br>
                                        <?= htmlspecialchars($enquiry['student_phone']) ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Details:</strong><br>
                                        Type: <?= ucfirst($enquiry['enquiry_type']) ?><br>
                                        Priority: <?= ucfirst($enquiry['priority']) ?><br>
                                        <?= time_ago($enquiry['created_at']) ?>
                                    </div>
                                </div>
                            </div>

                            <form method="post" class="enquiry-actions">
                                <input type="hidden" name="enquiry_id" value="<?= $enquiry['id'] ?>">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="new" <?= $enquiry['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                            <option value="in_progress" <?= $enquiry['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="resolved" <?= $enquiry['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                            <option value="closed" <?= $enquiry['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" name="delete_enquiry" class="btn btn-sm btn-outline-danger w-100"
                                            onclick="return confirm('Delete this enquiry?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php admin_footer(); ?>