-- Appointment Verification OTP System Database Schema
-- This file contains the SQL to create the OTP table

-- Create the appointment_otps table
CREATE TABLE IF NOT EXISTS appointment_otps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL UNIQUE,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    verification_status ENUM('Pending', 'Verified', 'Expired') DEFAULT 'Pending',
    verification_attempts INT DEFAULT 0,
    last_attempt_at DATETIME NULL,
    created_by INT NULL COMMENT 'Admin ID who approved the appointment',
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_appointment (appointment_id),
    INDEX idx_patient (patient_id),
    INDEX idx_status (verification_status),
    INDEX idx_expires (expires_at)
);

-- Create a log table for OTP verification attempts (for security auditing)
CREATE TABLE IF NOT EXISTS otp_verification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    otp_id INT NOT NULL,
    attempted_otp VARCHAR(6) NOT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    attempt_status ENUM('Success', 'Failed', 'Expired') DEFAULT 'Failed',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    FOREIGN KEY (otp_id) REFERENCES appointment_otps(id) ON DELETE CASCADE,
    INDEX idx_otp (otp_id),
    INDEX idx_time (attempt_time)
);
