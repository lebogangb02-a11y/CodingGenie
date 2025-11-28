<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';
require_once __DIR__ . '/includes/admin_auth.php';

admin_require_login();

$admin_id = $_SESSION['admin_id'];

// Get filter parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$action_type = $_GET['action_type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT a.*, adm.name as admin_name 
          FROM admin_activity_logs a 
          JOIN admins adm ON a.admin_id = adm.id 
          WHERE 1=1";
$params = [];

if ($start_date) {
    $query .= " AND DATE(a.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND DATE(a.created_at) <= ?";
    $params[] = $end_date;
}

if ($action_type) {
    $query .= " AND a.action LIKE ?";
    $params[] = "%$action_type%";
}

if ($search) {
    $query .= " AND (a.action LIKE ? OR a.details LIKE ? OR adm.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY a.created_at DESC";

// Get activities
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activities = [];
    $error = 'Error loading activity log: ' . $e->getMessage();
}

admin_header('Activity Log');
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Activity Log</h1>
        <a href="admin_profile.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Profile
        </a>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action Type</label>
                    <input type="text" name="action_type" class="form-control" value="<?= htmlspecialchars($action_type) ?>" 
                           placeholder="e.g., Login, Update">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search activities...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="admin_activity_log.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Activity Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php elseif (empty($activities)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-history fa-3x mb-3"></i>
                    <p>No activities found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <img src="<?= getProfileImage($activity['profile_image'] ?? '') ?>" 
                                                 alt="Admin" 
                                                 class="rounded-circle"
                                                 width="32" height="32"
                                                 style="object-fit: cover;">
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <?= htmlspecialchars($activity['admin_name']) ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= htmlspecialchars($activity['action']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($activity['details'] ?? '') ?></td>
                                <td><code><?= htmlspecialchars($activity['ip_address']) ?></code></td>
                                <td><?= date('M j, Y g:i A', strtotime($activity['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php admin_footer(); ?>

<?php
function getProfileImage($image) {
    if ($image && file_exists(__DIR__ . '/uploads/admins/' . $image)) {
        return '/uploads/admins/' . $image;
    }
    return '/assets/default-admin.png';
}
?>