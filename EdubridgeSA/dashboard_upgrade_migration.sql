-- Dashboard Upgrade Migration Script
-- Adds profile management and enhanced application features
-- Run this script to upgrade the existing database

-- 1. Add profile picture and additional fields to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS date_of_birth DATE DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS school_university VARCHAR(200) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_updated_at TIMESTAMP NULL DEFAULT NULL;

-- 2. Update applications table to include more status options
ALTER TABLE applications MODIFY COLUMN status ENUM('Pending', 'Accepted', 'Rejected', 'Submitted (with docs)', 'Submitted (without docs)') DEFAULT 'Pending';

-- 3. Add application_status column to applications table if it doesn't exist
ALTER TABLE applications ADD COLUMN IF NOT EXISTS application_status ENUM('Pending', 'Accepted', 'Rejected', 'Submitted (with docs)', 'Submitted (without docs)') DEFAULT 'Pending';

-- 4. Update application_status to match status column for existing records
UPDATE applications SET application_status = status WHERE application_status IS NULL;

-- 5. Create student_profiles table for extended profile information
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bio TEXT,
    interests TEXT,
    achievements TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    emergency_contact_relationship VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- 6. Create application_courses table for better course management
CREATE TABLE IF NOT EXISTS application_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    university_id INT,
    course_name VARCHAR(200) NOT NULL,
    course_code VARCHAR(50),
    priority_order INT DEFAULT 1,
    admission_requirements TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE SET NULL,
    INDEX idx_application (application_id),
    INDEX idx_university (university_id)
);

-- 7. Create application_timeline table for detailed tracking
CREATE TABLE IF NOT EXISTS application_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    event_type ENUM('created', 'submitted', 'documents_uploaded', 'status_changed', 'reviewed', 'decision_made') NOT NULL,
    event_title VARCHAR(200) NOT NULL,
    event_description TEXT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    created_by_user_id INT,
    created_by_admin_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application (application_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
);

-- 8. Update document types to include more options
ALTER TABLE application_documents MODIFY COLUMN document_type ENUM(
    'certified_id', 
    'proof_of_residence', 
    'parent_guardian_id', 
    'academic_results', 
    'matric_certificate',
    'transcript',
    'motivation_letter',
    'cv_resume',
    'portfolio',
    'recommendation_letter',
    'medical_certificate',
    'other'
) NOT NULL;

-- 9. Add document verification fields
ALTER TABLE application_documents ADD COLUMN IF NOT EXISTS verified_by_admin_id INT DEFAULT NULL;
ALTER TABLE application_documents ADD COLUMN IF NOT EXISTS verification_notes TEXT DEFAULT NULL;
ALTER TABLE application_documents ADD COLUMN IF NOT EXISTS verified_at TIMESTAMP NULL DEFAULT NULL;

-- 10. Create profile_picture_uploads table for tracking profile picture changes
CREATE TABLE IF NOT EXISTS profile_picture_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_current BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_current (is_current)
);

-- 11. Create application_filters table for saved search filters
CREATE TABLE IF NOT EXISTS application_filters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filter_name VARCHAR(100) NOT NULL,
    filter_criteria JSON NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- 12. Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_applications_status ON applications(application_status);
CREATE INDEX IF NOT EXISTS idx_applications_created_at ON applications(created_at);
CREATE INDEX IF NOT EXISTS idx_applications_updated_at ON applications(updated_at);
CREATE INDEX IF NOT EXISTS idx_users_profile_picture ON users(profile_picture);

-- 13. Create uploads directory structure (this will be handled by PHP)
-- The following directories will be created by the PHP application:
-- /uploads/profile_pictures/
-- /uploads/applications/documents/

-- 14. Insert sample timeline events for existing applications
INSERT IGNORE INTO application_timeline (application_id, event_type, event_title, event_description, new_status)
SELECT 
    id, 
    'created', 
    'Application Created', 
    'Initial application record created',
    status
FROM applications 
WHERE id NOT IN (SELECT DISTINCT application_id FROM application_timeline WHERE event_type = 'created');

-- 15. Update existing status history to new timeline format
INSERT IGNORE INTO application_timeline (application_id, event_type, event_title, event_description, old_status, new_status, created_at)
SELECT 
    application_id,
    'status_changed',
    CONCAT('Status changed from ', COALESCE(previous_status, 'unknown'), ' to ', new_status),
    notes,
    previous_status,
    new_status,
    created_at
FROM application_status_history
WHERE application_id IS NOT NULL;

-- 16. Add constraints for data integrity (triggers removed for compatibility)
-- Note: Triggers have been removed to ensure compatibility with line-by-line SQL execution
-- Profile timestamp updates and timeline tracking will be handled by the application code

-- Migration completed successfully
SELECT 'Dashboard upgrade migration completed successfully!' as message;