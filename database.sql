-- EMAIL AUTOMATION SYSTEM - DATABASE SETUP
-- Run this file to create all necessary tables

CREATE DATABASE IF NOT EXISTS email_system;
USE email_system;

-- ==================== COLLEGES TABLE ====================
CREATE TABLE IF NOT EXISTS colleges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    college_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    reference_number VARCHAR(50) NOT NULL,
    invitation_date DATE NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

-- ==================== EMAIL LOGS TABLE ====================
CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    college_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50),
    error_message LONGTEXT,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    INDEX idx_college (college_id),
    INDEX idx_status (status),
    INDEX idx_sent (sent_at)
);

-- ==================== TEMPLATE SETTINGS TABLE ====================
-- Stores the uploaded letter design (background image) and where the
-- dynamic fields (college name / ref number / date) get overlaid on it.
-- Single-row table (id always 1). If empty, the system falls back to the
-- built-in default letter design.
CREATE TABLE IF NOT EXISTS template_settings (
    id INT PRIMARY KEY DEFAULT 1,
    background_image VARCHAR(255) NULL,
    image_width INT NULL,
    image_height INT NULL,
    college_top DECIMAL(5,2) DEFAULT 20.00,
    college_left DECIMAL(5,2) DEFAULT 6.00,
    college_font_size INT DEFAULT 16,
    ref_top DECIMAL(5,2) DEFAULT 15.60,
    ref_left DECIMAL(5,2) DEFAULT 20.00,
    ref_font_size INT DEFAULT 14,
    date_top DECIMAL(5,2) DEFAULT 15.60,
    date_left DECIMAL(5,2) DEFAULT 76.00,
    date_font_size INT DEFAULT 14,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== SAMPLE DATA ====================
-- Uncomment below to insert sample data for testing

/*
INSERT INTO colleges (college_name, email, reference_number, invitation_date) VALUES
('ABC College', 'abc@college.com', 'REF001', '2024-01-15'),
('XYZ Institute', 'xyz@institute.com', 'REF002', '2024-01-16'),
('PQR University', 'pqr@university.com', 'REF003', '2024-01-17');
*/

-- ==================== VIEWS ====================

-- View for statistics
CREATE OR REPLACE VIEW college_stats AS
SELECT 
    COUNT(*) as total_colleges,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
FROM colleges;

-- ==================== STORED PROCEDURES ====================

-- Procedure to get college details with last email status
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS get_college_status(IN p_college_id INT)
BEGIN
    SELECT 
        c.id,
        c.college_name,
        c.email,
        c.reference_number,
        c.invitation_date,
        c.status,
        el.sent_at as last_email_sent,
        el.error_message
    FROM colleges c
    LEFT JOIN email_logs el ON c.id = el.college_id
    WHERE c.id = p_college_id
    ORDER BY el.sent_at DESC
    LIMIT 1;
END$$

-- Procedure to mark college as failed
CREATE PROCEDURE IF NOT EXISTS mark_as_failed(IN p_college_id INT, IN p_error_message VARCHAR(255))
BEGIN
    UPDATE colleges SET status = 'failed' WHERE id = p_college_id;
    INSERT INTO email_logs (college_id, status, error_message) 
    VALUES (p_college_id, 'failed', p_error_message);
END$$

DELIMITER ;

-- ==================== INDEXES ====================

-- Create indexes for better query performance
CREATE INDEX idx_colleges_status ON colleges(status);
CREATE INDEX idx_colleges_email ON colleges(email);
CREATE INDEX idx_colleges_created ON colleges(created_at);
CREATE INDEX idx_logs_college_id ON email_logs(college_id);
CREATE INDEX idx_logs_status ON email_logs(status);
