<?php
/**
 * OTP Generation and Management Utility
 * Handles creation, validation, and verification of appointment OTPs
 */

require_once 'db.php';

// Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_HOURS', 24);
define('MAX_VERIFICATION_ATTEMPTS', 5);

/**
 * Generate a unique 6-digit OTP
 * 
 * @return string 6-digit OTP code
 */
function generateOTP() {
    $otp = '';
    for ($i = 0; $i < OTP_LENGTH; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

/**
 * Check if OTP already exists in database
 * 
 * @param string $otp The OTP code to check
 * @param mysqli $conn Database connection
 * @return bool True if OTP exists, false otherwise
 */
function otpExists($otp, $conn) {
    $stmt = $conn->prepare('SELECT id FROM appointment_otps WHERE otp_code = ?');
    $stmt->bind_param('s', $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

/**
 * Create and store OTP for appointment
 * 
 * @param int $appointmentId Appointment ID
 * @param int $patientId Patient ID
 * @param mysqli $conn Database connection
 * @param int $adminId Optional admin ID who approved
 * @return array ['success' => bool, 'otp' => string, 'message' => string]
 */
function createAppointmentOTP($appointmentId, $patientId, $conn, $adminId = null) {
    // Verify appointment exists and is approved
    $stmt = $conn->prepare('
        SELECT a.id, a.status, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time,
               p.email, p.name as patient_name,
               d.name as doctor_name, d.specialty
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ? AND a.patient_id = ?
    ');
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $stmt->bind_param('ii', $appointmentId, $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Appointment not found'];
    }
    
    $appointment = $result->fetch_assoc();
    $stmt->close();
    
    // Check if appointment already has a pending OTP
    $checkStmt = $conn->prepare('
        SELECT id FROM appointment_otps 
        WHERE appointment_id = ? AND verification_status IN ("Pending", "Verified")
    ');
    $checkStmt->bind_param('i', $appointmentId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $checkStmt->close();
        return ['success' => false, 'message' => 'OTP already generated for this appointment'];
    }
    $checkStmt->close();
    
    // Generate unique OTP
    $otp = '';
    $maxAttempts = 10;
    $attempts = 0;
    
    do {
        $otp = generateOTP();
        $attempts++;
    } while (otpExists($otp, $conn) && $attempts < $maxAttempts);
    
    if ($attempts >= $maxAttempts) {
        return ['success' => false, 'message' => 'Unable to generate unique OTP'];
    }
    
    // Calculate expiry time
    $expiryTime = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_HOURS . ' hours'));
    
    // Store OTP in database
    $insertStmt = $conn->prepare('
        INSERT INTO appointment_otps 
        (appointment_id, patient_id, otp_code, expires_at, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ');
    
    if (!$insertStmt) {
        return ['success' => false, 'message' => 'Database error: ' . $conn->error];
    }
    
    $insertStmt->bind_param('iissi', $appointmentId, $patientId, $otp, $expiryTime, $adminId);
    
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        return ['success' => false, 'message' => 'Failed to generate OTP: ' . $conn->error];
    }
    $insertStmt->close();
    
    // Prepare email and send
    require_once 'send_email.php';
    
    $emailSubject = "Appointment Approved - Verification OTP";
    $emailBody = getOTPEmailTemplate(
        $appointment['patient_name'],
        $appointment['doctor_name'],
        $appointment['specialty'],
        $appointment['appointment_date'],
        $appointment['appointment_time'],
        $otp
    );
    
    $emailSent = sendEmail($appointment['email'], $emailSubject, $emailBody);
    
    return [
        'success' => true,
        'otp' => $otp,
        'message' => 'OTP generated successfully. Email sent to ' . $appointment['email'],
        'email_sent' => $emailSent
    ];
}

/**
 * Verify an OTP
 * 
 * @param string $otp OTP code to verify
 * @param int $appointmentId Appointment ID
 * @param mysqli $conn Database connection
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function verifyAppointmentOTP($otp, $appointmentId, $conn) {
    // Sanitize input
    $otp = preg_replace('/[^0-9]/', '', $otp);
    
    if (strlen($otp) !== OTP_LENGTH) {
        return ['success' => false, 'message' => 'Invalid OTP format'];
    }
    
    // Find OTP record
    $stmt = $conn->prepare('
        SELECT ao.id, ao.verification_status, ao.expires_at, ao.verification_attempts, ao.verified_at,
               a.patient_id, a.status as appointment_status,
               p.name as patient_name,
               d.name as doctor_name, d.specialty,
               a.appointment_date, a.appointment_time
        FROM appointment_otps ao
        JOIN appointments a ON ao.appointment_id = a.id
        JOIN patients p ON ao.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE ao.otp_code = ? AND ao.appointment_id = ?
    ');
    
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error'];
    }
    
    $stmt->bind_param('si', $otp, $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        // Log failed attempt
        logOTPAttempt(null, $otp, 'Failed');
        return ['success' => false, 'message' => 'Invalid OTP or appointment ID'];
    }
    
    $otpRecord = $result->fetch_assoc();
    $stmt->close();
    
    // Check if OTP is already verified
    if ($otpRecord['verification_status'] === 'Verified') {
        return ['success' => false, 'message' => 'This OTP has already been used'];
    }
    
    // Check if OTP is expired
    if (strtotime($otpRecord['expires_at']) < time()) {
        updateOTPStatus($otpRecord['id'], 'Expired', $conn);
        return ['success' => false, 'message' => 'OTP has expired. Please request a new one'];
    }
    
    // Check attempt limit
    if ($otpRecord['verification_attempts'] >= MAX_VERIFICATION_ATTEMPTS) {
        return ['success' => false, 'message' => 'Maximum verification attempts exceeded. Please request a new OTP'];
    }
    
    // Update verification status
    $verifiedTime = date('Y-m-d H:i:s');
    $updateStmt = $conn->prepare('
        UPDATE appointment_otps 
        SET verification_status = "Verified", verified_at = ?, verification_attempts = verification_attempts + 1
        WHERE id = ?
    ');
    
    if (!$updateStmt) {
        return ['success' => false, 'message' => 'Database error during verification'];
    }
    
    $updateStmt->bind_param('si', $verifiedTime, $otpRecord['id']);
    
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        return ['success' => false, 'message' => 'Failed to verify OTP'];
    }
    $updateStmt->close();
    
    // Log successful attempt
    logOTPAttempt($otpRecord['id'], $otp, 'Success');
    
    // Send verification confirmation email
    require_once 'send_email.php';
    $emailSubject = "Appointment Verified - Welcome!";
    $emailBody = getOTPVerificationEmailTemplate(
        $otpRecord['patient_name'],
        date('F j, Y at g:i A', strtotime($verifiedTime))
    );
    sendEmail($otpRecord['patient_name'], $emailSubject, $emailBody);
    
    return [
        'success' => true,
        'message' => 'OTP verified successfully!',
        'data' => [
            'patient_name' => $otpRecord['patient_name'],
            'doctor_name' => $otpRecord['doctor_name'],
            'specialty' => $otpRecord['specialty'],
            'appointment_date' => $otpRecord['appointment_date'],
            'appointment_time' => $otpRecord['appointment_time'],
            'verified_at' => $verifiedTime
        ]
    ];
}

/**
 * Update OTP verification status
 * 
 * @param int $otpId OTP record ID
 * @param string $status New status (Pending, Verified, Expired)
 * @param mysqli $conn Database connection
 * @return bool True if updated successfully
 */
function updateOTPStatus($otpId, $status, $conn) {
    $stmt = $conn->prepare('UPDATE appointment_otps SET verification_status = ? WHERE id = ?');
    if (!$stmt) return false;
    
    $stmt->bind_param('si', $status, $otpId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Log OTP verification attempt
 * 
 function logOTPAttempt($otpId, $attemptedOtp, $status, $conn = null) {

    global $conn;

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if ($otpId !== null) {
        $stmt = $conn->prepare('
            INSERT INTO otp_verification_logs (otp_id, attempted_otp, attempt_status, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('issss', $otpId, $attemptedOtp, $status, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
}
/**
 * Get OTP details for an appointment
 * 
 * @param int $appointmentId Appointment ID
 * @param mysqli $conn Database connection
 * @return array OTP record or null
 */
function getAppointmentOTP($appointmentId, $conn) {
    $stmt = $conn->prepare('
        SELECT id, patient_id, otp_code, generated_at, expires_at, verification_status, verified_at
        FROM appointment_otps
        WHERE appointment_id = ?
        ORDER BY generated_at DESC
        LIMIT 1
    ');
    
    if (!$stmt) return null;
    
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $otp = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $otp;
}

/**
 * Check if OTP is still valid (not expired)
 * 
 * @param string $expiryTime Expiry time string
 * @return bool True if OTP is still valid
 */
function isOTPValid($expiryTime) {
    return strtotime($expiryTime) > time();
}

/**
 * Get all pending OTPs for a patient
 * 
 * @param int $patientId Patient ID
 * @param mysqli $conn Database connection
 * @return array Array of OTP records
 */
function getPatientPendingOTPs($patientId, $conn) {
    $stmt = $conn->prepare('
        SELECT ao.id, ao.appointment_id, ao.otp_code, ao.generated_at, ao.expires_at,
               ao.verification_status, d.name as doctor_name,
               a.appointment_date, a.appointment_time
        FROM appointment_otps ao
        JOIN appointments a ON ao.appointment_id = a.id
        JOIN doctors d ON a.doctor_id = d.id
        WHERE ao.patient_id = ? AND ao.verification_status = "Pending"
        ORDER BY ao.generated_at DESC
    ');
    
    if (!$stmt) return [];
    
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $otps = [];
    while ($row = $result->fetch_assoc()) {
        $otps[] = $row;
    }
    $stmt->close();
    return $otps;
}

?>
