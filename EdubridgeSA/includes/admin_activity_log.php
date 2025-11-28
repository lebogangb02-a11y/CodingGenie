<?php
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/includes/admin_layout.php';

$admin = require_admin_session(['super','admin']); // staff can be restricted if desired

admin_header('Admin Activity Log');
admin_sidebar('activity');

// Filters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$type = $_GET['type'] ?? '';
$q = $_GET['q'] ?? '';

// Build query
global $pdo;
$sql = 'SELECT l.*, a.username FROM admin_activity_logs l JOIN admins a ON a.id = l.admin_id WHERE 1=1';
$vals = [];
if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $sql .= ' AND DATE(l.created_at) >= ?'; $vals[] = $from; }
if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $sql .= ' AND DATE(l.created_at) <= ?'; $vals[] = $to; }
if ($type) { $sql .= ' AND l.action = ?'; $vals[] = $type; }
if ($q) { $sql .= ' AND (l.details LIKE ? OR a.username LIKE ?)'; $vals[] = "%$q%"; $vals[] = "%$q%"; }
$sql .= ' ORDER BY l.created_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($vals);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center mb-3">
    <i class="fa-solid fa-clipboard-list me-2"></i>
    <h4 class="mb-0">Admin Activity Log</h4>
  </div>

  <form class="row g-2 mb-3" method="get" action="admin_activity_log.php">
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Activity Type</label>
      <select name="type" class="form-select">
        <option value="">All</option>
        <?php
          $types = ['Edit Profile','Change Password','Upload Profile Image','Login','Logout','Mark Message Read'];
          foreach ($types as $t) {
            $sel = $type === $t ? 'selected' : '';
            echo "<option value='".htmlspecialchars($t)."' $sel>".htmlspecialchars($t)."</option>";
          }
        ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Search</label>
      <input type="text" class="form-control" name="q" placeholder="username, details" value="<?= htmlspecialchars($q) ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-primary w-100"><i class="fa-solid fa-magnifying-glass me-1"></i> Filter</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Admin</th>
            <th>Action</th>
            <th>IP</th>
            <th>Agent</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><span class="badge text-bg-secondary"><?= htmlspecialchars($row['action']) ?></span></td>
              <td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>
              <td class="text-truncate" style="max-width:320px;"><?= htmlspecialchars($row['user_agent'] ?? '') ?></td>
              <td><?= htmlspecialchars($row['details'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$logs): ?>
        <p class="text-muted mb-0">No activity found for selected filters.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php admin_footer(); ?>