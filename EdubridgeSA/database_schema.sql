-- Student Application System Database Schema
-- Created for multi-step online application form

-- Main applications table
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(20) UNIQUE NOT NULL,
    
    -- Personal Information
    gender ENUM('Male', 'Female') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    id_number VARCHAR(20) NOT NULL,
    cellphone_number VARCHAR(20) NOT NULL,
    date_of_birth DATE NOT NULL,
    email_address VARCHAR(150) NOT NULL,
    physical_address TEXT,
    postal_code VARCHAR(10) NOT NULL,
    country_of_residence VARCHAR(100) NOT NULL,
    
    -- Application status and tracking
    status ENUM('draft', 'submitted', 'documents_pending', 'complete', 'under_review') DEFAULT 'draft',
    step_completed INT DEFAULT 0, -- Track which step was last completed (1-4)
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    
    INDEX idx_reference (reference_number),
    INDEX idx_email (email_address),
    INDEX idx_id_number (id_number),
    INDEX idx_status (status)
);

-- Parent/Guardian details table
CREATE TABLE parent_guardian_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    full_name VARCHAR(100),
    surname VARCHAR(100),
    marital_status ENUM('Single', 'Married', 'Divorced', 'Widowed', 'Other'),
    id_number VARCHAR(20),
    contact_number VARCHAR(20),
    email_address VARCHAR(150),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application (application_id)
);

-- University choices table (supports up to 3 choices)
CREATE TABLE university_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    university_1 VARCHAR(100),
    university_2 VARCHAR(100),
    university_3 VARCHAR(100),
    course_first_choice VARCHAR(200),
    course_second_choice VARCHAR(200),
    additional_comments TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application (application_id)
);

-- Document uploads table
CREATE TABLE application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    document_type ENUM('certified_id', 'proof_of_residence', 'parent_guardian_id', 'academic_results') NOT NULL,
    original_filename VARCHAR(255),
    stored_filename VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    mime_type VARCHAR(100),
    
    upload_status ENUM('pending', 'uploaded', 'verified', 'rejected') DEFAULT 'pending',
    upload_date TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application (application_id),
    INDEX idx_document_type (document_type),
    INDEX idx_status (upload_status)
);

-- Application status history for tracking progress
CREATE TABLE application_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    step_completed INT,
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application (application_id),
    INDEX idx_created (created_at)
);

-- Email notifications log
CREATE TABLE email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    
    email_type ENUM('confirmation', 'reminder', 'status_update') NOT NULL,
    recipient_email VARCHAR(150) NOT NULL,
    subject VARCHAR(255),
    message_body TEXT,
    
    sent_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    INDEX idx_application (application_id),
    INDEX idx_email_type (email_type),
    INDEX idx_sent_status (sent_status)
);

-- Insert default universities list for reference
CREATE TABLE universities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default university options
INSERT INTO universities (name, code) VALUES
('University of Cape Town', 'UCT'),
('University of the Witwatersrand', 'Wits'),
('Stellenbosch University', 'SU'),
('University of Pretoria', 'UP'),
('University of Johannesburg', 'UJ'),
('University of KwaZulu-Natal', 'UKZN'),
('North-West University', 'NWU'),
('University of South Africa', 'UNISA'),
('Tshwane University of Technology', 'TUT'),
('University of the Free State', 'UFS'),
('Other', 'OTHER');