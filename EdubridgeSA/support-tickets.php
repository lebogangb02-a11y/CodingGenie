<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

$student_id = $_SESSION['student_id'] ?? null;
$email = $_SESSION['student_email'] ?? $_SESSION['email'] ?? '';
$reference_number = $_SESSION['reference_number'] ?? null;

// Create new ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message'] ?? '');
    if ($subject !== '') {
        $stmt = $pdo->prepare('INSERT INTO support_tickets (student_id, reference_number, email, subject, status, priority) VALUES (?, ?, ?, ?, "open", ?)');
        $stmt->execute([$student_id, $reference_number, $email, $subject, $_POST['priority'] ?? 'normal']);
        $ticket_id = (int)$pdo->lastInsertId();
        if ($message !== '') {
            $stmt2 = $pdo->prepare('INSERT INTO support_ticket_messages (ticket_id, sender, message_text) VALUES (?, "student", ?)');
            $stmt2->execute([$ticket_id, $message]);
        }
        header('Location: support-tickets.php?created=1');
        exit();
    }
}

// Fetch student tickets
$tickets = [];
if ($email) {
    $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE email = ? OR student_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$email, $student_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Support Tickets - EduBridge SA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/support.css" rel="stylesheet">
</head>
<body>
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="page-title">Support Tickets</h1>
      <a class="btn btn-outline-primary" href="support.php"><i class="fa fa-life-ring"></i> Support Center</a>
    </div>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="card support-card">
          <div class="card-header bg-primary text-white"><strong>Create a Ticket</strong></div>
          <div class="p-3">
            <form method="POST">
              <div class="mb-2">
                <label class="form-label">Subject</label>
                <input class="form-control" name="subject" placeholder="Brief summary of your issue" required />
              </div>
              <div class="mb-2">
                <label class="form-label">Details</label>
                <textarea class="form-control" name="message" rows="4" placeholder="Describe what you need help with..."></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Priority</label>
                <select class="form-select" name="priority">
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <button class="btn btn-primary w-100"><i class="fa fa-ticket"></i> Submit Ticket</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card support-card">
          <div class="card-header bg-primary text-white"><strong>Your Tickets</strong></div>
          <div class="table-responsive">
            <table class="table mb-0 align-middle">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Subject</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tickets as $t): ?>
                  <tr>
                    <td>#<?php echo (int)$t['id']; ?></td>
                    <td><?php echo htmlspecialchars($t['subject']); ?></td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['status']); ?></span></td>
                    <td><?php echo htmlspecialchars($t['updated_at']); ?></td>
                    <td>
                      <a class="btn btn-sm btn-outline-primary" href="view-ticket.php?id=<?php echo (int)$t['id']; ?>">
                        <i class="fa fa-comments"></i> View
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($tickets)): ?>
                  <tr><td colspan="5" class="text-center text-muted">No tickets yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>