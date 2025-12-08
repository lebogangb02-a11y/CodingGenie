-- Critical Performance Indexes for EdubridgeSA
-- Execute all commands in this file to optimize database queries
-- Execution Time: 2-5 minutes (depending on database size)
-- Expected Impact: 50-70% faster queries for indexed columns

-- ============================================
-- 1. APPLICATIONS TABLE INDEXES
-- ============================================

-- Index for email lookups (user registration, application lookup)
ALTER TABLE applications ADD INDEX idx_applications_email (email_address);

-- Index for reference number lookups (application tracking)
ALTER TABLE applications ADD INDEX idx_applications_ref (reference_number);

-- Index for status filtering (admin dashboard, progress tracking)
ALTER TABLE applications ADD INDEX idx_applications_status (application_status);

-- Index for date range queries (pagination, recent apps)
ALTER TABLE applications ADD INDEX idx_applications_created (created_at);

-- Composite index for common query patterns
ALTER TABLE applications ADD INDEX idx_app_email_status (email_address, application_status);

-- ============================================
-- 2. APPLICATION_DOCUMENTS TABLE INDEXES
-- ============================================

-- Index for document lookup by application
ALTER TABLE application_documents ADD INDEX idx_docs_app_id (application_id);

-- Index for document type filtering (finding PDFs, images, etc)
ALTER TABLE application_documents ADD INDEX idx_docs_type (document_type);

-- Composite index for document status queries
ALTER TABLE application_documents ADD INDEX idx_docs_app_type (application_id, document_type);

-- ============================================
-- 3. USERS TABLE INDEXES
-- ============================================

-- Index for login/authentication queries
ALTER TABLE users ADD INDEX idx_users_email (email);

-- Index for email verification filtering
ALTER TABLE users ADD INDEX idx_users_verified (email_verified);

-- Composite index for auth+verification
ALTER TABLE users ADD INDEX idx_users_auth (email, email_verified, password_hash);

-- ============================================
-- 4. CHAT_CONVERSATIONS TABLE INDEXES
-- ============================================

-- Index for student chat lookups (dashboard)
ALTER TABLE chat_conversations ADD INDEX idx_chat_student_id (student_id);

-- Index for status filtering (active vs closed chats)
ALTER TABLE chat_conversations ADD INDEX idx_chat_status (status);

-- Composite index for common patterns
ALTER TABLE chat_conversations ADD INDEX idx_chat_student_status (student_id, status);

-- ============================================
-- 5. CHAT_MESSAGES TABLE INDEXES
-- ============================================

-- Index for message retrieval by conversation
ALTER TABLE chat_messages ADD INDEX idx_msg_conversation (conversation_id);

-- Index for unread message filtering
ALTER TABLE chat_messages ADD INDEX idx_msg_unread (conversation_id, is_read);

-- ============================================
-- 6. AUDIT_LOGS TABLE INDEXES
-- ============================================

-- Index for user activity lookups
ALTER TABLE audit_logs ADD INDEX idx_audit_user_id (user_id);

-- Index for date range queries
ALTER TABLE audit_logs ADD INDEX idx_audit_timestamp (timestamp);

-- Composite index for common audit patterns
ALTER TABLE audit_logs ADD INDEX idx_audit_user_time (user_id, timestamp);

-- ============================================
-- 7. PARENT_GUARDIAN_DETAILS TABLE INDEXES
-- ============================================

-- Index for application lookup
ALTER TABLE parent_guardian_details ADD INDEX idx_pgd_app_id (application_id);

-- ============================================
-- 8. UNIVERSITY_CHOICES TABLE INDEXES
-- ============================================

-- Index for application university lookup
ALTER TABLE university_choices ADD INDEX idx_uc_app_id (application_id);

-- ============================================
-- Verify Indexes Were Created
-- ============================================

-- Show all indexes on key tables (for verification)
SHOW INDEXES FROM applications;
SHOW INDEXES FROM application_documents;
SHOW INDEXES FROM users;
SHOW INDEXES FROM chat_conversations;
SHOW INDEXES FROM audit_logs;

-- ============================================
-- Performance Testing Commands
-- ============================================

-- Test email lookup (should use idx_applications_email)
-- EXPLAIN SELECT * FROM applications WHERE email_address = 'test@example.com';

-- Test reference lookup (should use idx_applications_ref)
-- EXPLAIN SELECT * FROM applications WHERE reference_number = 'APP2025000001';

-- Test status query (should use idx_applications_status)
-- EXPLAIN SELECT * FROM applications WHERE application_status = 'submitted';

-- Expected Result: "Using index" in EXPLAIN output for all queries above
