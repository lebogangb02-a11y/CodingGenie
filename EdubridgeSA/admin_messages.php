<?php
// admin_messages.php - Enhanced Admin Messages & Notifications System
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';

admin_require_login();

$username = $_SESSION['admin_username'] ?? 'admin';
$unreadCount = 0;
$messages = [];
$notifications = [];

// Ensure messages and notifications tables exist
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Create admin_messages table
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            message_type ENUM('application','enquiry','system','alert') NOT NULL DEFAULT 'system',
            priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
            sender_type ENUM('student','system','admin') NOT NULL DEFAULT 'system',
            sender_id INT,
            sender_name VARCHAR(255),
            recipient_id INT,
            recipient_username VARCHAR(100),
            is_read BOOLEAN DEFAULT FALSE,
            is_archived BOOLEAN DEFAULT FALSE,
            related_entity_type ENUM('application','student','payment','system') NULL,
            related_entity_id INT,
            action_url VARCHAR(500),
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            INDEX idx_recipient (recipient_username),
            INDEX idx_type (message_type),
            INDEX idx_priority (priority),
            INDEX idx_read (is_read),
            INDEX idx_created (created_at),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create system_notifications table for broadcast messages
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            notification_type ENUM('info','warning','success','danger') NOT NULL DEFAULT 'info',
            target_audience ENUM('all','super','moderator','staff') NOT NULL DEFAULT 'all',
            is_active BOOLEAN DEFAULT TRUE,
            start_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            end_at TIMESTAMP NULL,
            created_by VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_audience (target_audience),
            INDEX idx_dates (start_at, end_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create contact_enquiries table for student enquiries
        $pdo->exec("CREATE TABLE IF NOT EXISTS contact_enquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_name VARCHAR(255) NOT NULL,
            student_email VARCHAR(255) NOT NULL,
            student_phone VARCHAR(50),
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            enquiry_type ENUM('general','admission','financial','technical','other') NOT NULL DEFAULT 'general',
            status ENUM('new','in_progress','resolved','closed') NOT NULL DEFAULT 'new',
            assigned_to VARCHAR(100),
            priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            INDEX idx_status (status),
            INDEX idx_type (enquiry_type),
            INDEX idx_assigned (assigned_to),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    } catch (Throwable $e) {
        // Silently continue - tables might already exist
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read']) && isset($_POST['message_id'])) {
        $messageId = (int)$_POST['message_id'];
        try {
            $stmt = $pdo->prepare('UPDATE admin_messages SET is_read = TRUE, read_at = NOW() WHERE id = ? AND recipient_username = ?');
            $stmt->execute([$messageId, $username]);
        } catch (Throwable $e) {
            // Silently fail
        }
    }
    
    if (isset($_POST['archive_message']) && isset($_POST['message_id'])) {
        $messageId = (int)$_POST['message_id'];
        try {
            $stmt = $pdo->prepare('UPDATE admin_messages SET is_archived = TRUE WHERE id = ? AND recipient_username = ?');
            $stmt->execute([$messageId, $username]);
        } catch (Throwable $e) {
            // Silently fail
        }
    }
    
    if (isset($_POST['update_enquiry_status']) && isset($_POST['enquiry_id'])) {
        $enquiryId = (int)$_POST['enquiry_id'];
        $status = $_POST['status'] ?? 'new';
        $assignedTo = $_POST['assigned_to'] ?? null;
        
        try {
            $stmt = $pdo->prepare('UPDATE contact_enquiries SET status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $assignedTo, $enquiryId]);
        } catch (Throwable $e) {
            // Silently fail
        }
    }
}

// Load messages and notifications
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Get unread count
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM admin_messages WHERE recipient_username = ? AND is_read = FALSE AND is_archived = FALSE');
        $countStmt->execute([$username]);
        $unreadCount = $countStmt->fetchColumn();

        // Get messages
        $msgStmt = $pdo->prepare('
            SELECT m.*, 
                   a.reference_number, 
                   a.full_name as student_name,
                   e.student_name as enquiry_student_name,
                   e.student_email as enquiry_email
            FROM admin_messages m
            LEFT JOIN applications a ON m.related_entity_id = a.id AND m.related_entity_type = "application"
            LEFT JOIN contact_enquiries e ON m.related_entity_id = e.id AND m.related_entity_type = "enquiry"
            WHERE m.recipient_username = ? AND m.is_archived = FALSE
            ORDER BY m.created_at DESC
            LIMIT 50
        ');
        $msgStmt->execute([$username]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get active notifications
        $notifStmt = $pdo->prepare('
            SELECT * FROM system_notifications 
            WHERE is_active = TRUE 
            AND (target_audience = "all" OR target_audience = ?)
            AND (start_at <= NOW() AND (end_at IS NULL OR end_at >= NOW()))
            ORDER BY created_at DESC
            LIMIT 10
        ');
        $notifStmt->execute([$_SESSION['admin_role'] ?? 'staff']);
        $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        // Silently fail - non-critical features
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';

admin_header('Messages & Notifications');
?>
<style>
.message-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}
.message-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.message-unread {
    border-left-color: #dc3545;
    background-color: #f8f9fa;
}
.message-application {
    border-left-color: #28a745;
}
.message-enquiry {
    border-left-color: #ffc107;
}
.message-system {
    border-left-color: #6c757d;
}
.message-alert {
    border-left-color: #dc3545;
}
.priority-high { border-left-width: 6px; }
.priority-urgent { 
    border-left-width: 8px; 
    background: linear-gradient(90deg, rgba(220,53,69,0.1) 0%, rgba(255,255,255,1) 100%);
}
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
}
.message-actions {
    opacity: 0;
    transition: opacity 0.3s ease;
}
.message-card:hover .message-actions {
    opacity: 1;
}
.enquiry-new {
    background-color: #e3f2fd;
}
.enquiry-in_progress {
    background-color: #fff3cd;
}
.enquiry-resolved {
    background-color: #d4edda;
}
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Left Column - Messages -->
        <div class="col-lg-8">
            <!-- Header with Filters -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-chat-dots me-2"></i>Messages
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger ms-2"><?= $unreadCount ?> unread</span>
                            <?php endif; ?>
                        </h4>
                        <div class="btn-group">
                            <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                            <a href="?filter=unread" class="btn btn-outline-danger <?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
                            <a href="?filter=applications" class="btn btn-outline-success <?= $filter === 'applications' ? 'active' : '' ?>">Applications</a>
                            <a href="?filter=enquiries" class="btn btn-outline-warning <?= $filter === 'enquiries' ? 'active' : '' ?>">Enquiries</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="messages-container">
                <?php if (empty($messages)): ?>
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h5 class="mt-3 text-muted">No messages</h5>
                            <p class="text-muted">You're all caught up! New applications and enquiries will appear here.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): 
                        $isUnread = !$message['is_read'];
                        $priorityClass = $message['priority'] === 'high' ? 'priority-high' : ($message['priority'] === 'urgent' ? 'priority-urgent' : '');
                        $typeClass = 'message-' . $message['message_type'];
                    ?>
                        <div class="card message-card shadow-sm mb-3 <?= $typeClass ?> <?= $priorityClass ?> <?= $isUnread ? 'message-unread' : '' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <h6 class="card-title mb-0 me-2">
                                                <?= htmlspecialchars($message['subject']) ?>
                                                <?php if ($isUnread): ?>
                                                    <span class="badge bg-danger notification-badge">New</span>
                                                <?php endif; ?>
                                            </h6>
                                            <span class="badge bg-secondary"><?= ucfirst($message['message_type']) ?></span>
                                            <?php if ($message['priority'] !== 'medium'): ?>
                                                <span class="badge bg-<?= $message['priority'] === 'high' ? 'warning' : 'danger' ?> ms-1">
                                                    <?= ucfirst($message['priority']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="card-text mb-2"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                        
                                        <div class="small text-muted">
                                            <?php if ($message['sender_name']): ?>
                                                From: <strong><?= htmlspecialchars($message['sender_name']) ?></strong> •
                                            <?php endif; ?>
                                            <?php if ($message['student_name']): ?>
                                                Student: <strong><?= htmlspecialchars($message['student_name']) ?></strong> •
                                            <?php endif; ?>
                                            <?php if ($message['reference_number']): ?>
                                                Ref: <code><?= htmlspecialchars($message['reference_number']) ?></code> •
                                            <?php endif; ?>
                                            <?= time_ago($message['created_at']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="message-actions ms-3">
                                        <?php if ($isUnread): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                                <button type="submit" name="mark_read" class="btn btn-sm btn-outline-success" title="Mark as read">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($message['action_url']): ?>
                                            <a href="<?= htmlspecialchars($message['action_url']) ?>" class="btn btn-sm btn-primary" title="Take action">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                            <button type="submit" name="archive_message" class="btn btn-sm btn-outline-secondary" title="Archive">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column - Notifications & Quick Actions -->
        <div class="col-lg-4">
            <!-- System Notifications -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bell me-2"></i>System Notifications
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-bell-slash display-4"></i>
                            <p class="mt-2">No active notifications</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="alert alert-<?= $notification['notification_type'] ?> alert-dismissible fade show mb-3">
                                <h6 class="alert-heading"><?= htmlspecialchars($notification['title']) ?></h6>
                                <?= nl2br(htmlspecialchars($notification['content'])) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="bi bi-speedometer2 me-2"></i>Quick Overview
                    </h6>
                    <?php
                    try {
                        $pendingApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'")->fetchColumn();
                        $newEnquiries = $pdo->query("SELECT COUNT(*) FROM contact_enquiries WHERE status = 'new'")->fetchColumn();
                        $todayApps = $pdo->query("SELECT COUNT(*) FROM applications WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                    } catch (Throwable $e) {
                        $pendingApps = $newEnquiries = $todayApps = 0;
                    }
                    ?>
                    <div class="row text-center">
                        <div class="col-4 mb-3">
                            <div class="h5 text-primary mb-0"><?= $pendingApps ?></div>
                            <small class="text-muted">Pending Apps</small>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="h5 text-warning mb-0"><?= $newEnquiries ?></div>
                            <small class="text-muted">New Enquiries</small>
                        </div>
                        <div class="col-4 mb-3">
                            <div class="h5 text-success mb-0"><?= $todayApps ?></div>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Enquiries -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-envelope me-2"></i>Recent Enquiries
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $enquiries = $pdo->query("
                            SELECT * FROM contact_enquiries 
                            ORDER BY created_at DESC 
                            LIMIT 5
                        ")->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Throwable $e) {
                        $enquiries = [];
                    }
                    ?>
                    
                    <?php if (empty($enquiries)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-envelope-open display-4"></i>
                            <p class="mt-2">No recent enquiries</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($enquiries as $enquiry): ?>
                            <div class="enquiry-item mb-3 p-2 rounded <?= 'enquiry-' . $enquiry['status'] ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($enquiry['subject']) ?></h6>
                                        <p class="small mb-1"><?= htmlspecialchars(substr($enquiry['message'], 0, 100)) ?>...</p>
                                        <div class="small text-muted">
                                            From: <?= htmlspecialchars($enquiry['student_name']) ?> •
                                            <?= time_ago($enquiry['created_at']) ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-<?= $enquiry['status'] === 'new' ? 'danger' : ($enquiry['status'] === 'in_progress' ? 'warning' : 'success') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $enquiry['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="contact_enquiries.php" class="btn btn-sm btn-outline-primary">View All Enquiries</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh messages every 30 seconds
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newCount = doc.querySelector('.badge.bg-danger')?.textContent || '0';
            const currentCount = document.querySelector('.badge.bg-danger')?.textContent || '0';
            
            if (newCount !== currentCount) {
                location.reload();
            }
        })
        .catch(error => console.log('Auto-refresh failed:', error));
}, 30000);

// Mark message as read on click
document.addEventListener('click', function(e) {
    if (e.target.closest('.message-card')) {
        const messageCard = e.target.closest('.message-card');
        const messageId = messageCard.querySelector('input[name="message_id"]')?.value;
        const isUnread = messageCard.classList.contains('message-unread');
        
        if (isUnread && messageId) {
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('mark_read', '1');
            
            fetch('admin_messages.php', {
                method: 'POST',
                body: formData
            }).then(() => {
                messageCard.classList.remove('message-unread');
                const badge = messageCard.querySelector('.notification-badge');
                if (badge) badge.remove();
                
                // Update unread count
                const unreadBadge = document.querySelector('.badge.bg-danger');
                if (unreadBadge) {
                    const newCount = parseInt(unreadBadge.textContent) - 1;
                    if (newCount > 0) {
                        unreadBadge.textContent = newCount;
                    } else {
                        unreadBadge.remove();
                    }
                }
            });
        }
    }
});
</script>
