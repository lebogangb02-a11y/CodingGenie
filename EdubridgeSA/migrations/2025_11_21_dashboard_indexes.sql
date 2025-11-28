-- Additional indexes for real-time dashboard (v2)
-- Safe for Hostinger MySQL; adjust IF NOT EXISTS if unsupported

-- Admin activity logs (used by api/getAuditLogs.php)
ALTER TABLE admin_activity_logs
  ADD INDEX idx_admin_username_created (admin_username, created_at),
  ADD INDEX idx_action_created (action, created_at);

-- Email logs table (used by background scripts)
ALTER TABLE email_logs
  ADD INDEX idx_email_logs_status_created (status, created_at),
  ADD INDEX idx_email_logs_type_created (type, created_at);

-- Ensure email_notifications also has optimal indexes (dup safe)
ALTER TABLE email_notifications
  ADD INDEX idx_email_notif_status_created (sent_status, sent_at),
  ADD INDEX idx_email_notif_app_created (application_id, sent_at);

-- Notes:
-- - Composite indexes match common ORDER BY/WHERE patterns
-- - Apply via phpMyAdmin or CLI during low-traffic windows
-- - For MySQL <8, remove IF NOT EXISTS clauses and check manually