# Appointment Verification OTP System - Technical Documentation

## 🔧 System Architecture

### Component Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    HOSPITAL MANAGEMENT SYSTEM               │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────┐      ┌──────────────┐   ┌──────────────┐  │
│  │   Database   │      │  Email       │   │   OTP Logic  │  │
│  │              │      │  Service     │   │              │  │
│  │ appointment_ │←────→│              │   │ Verify       │  │
│  │ otps         │      │ SMTP Config  │   │ Generate     │  │
│  │              │      │              │   │ Log          │  │
│  └──────────────┘      └──────────────┘   └──────────────┘  │
│         ↑                      ↑                   ↑           │
│         │                      │                   │           │
│  ┌──────┴──────────────────────┴───────────────────┘           │
│  │                                                             │
│  ├─→ verify-appointment-otp.php      (Staff Verification)    │
│  ├─→ my-appointments-otp.php         (Patient Portal)        │
│  ├─→ otp-reports.php                 (Admin Dashboard)       │
│  ├─→ admin-appointments.php          (Modified)              │
│  │                                                             │
│  └─────────────────────────────────────────────────────────── │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 File Structure

### Core Files

#### 1. **otp-management.php** - OTP Business Logic
```
Location: /otp-management.php
Size: ~450 lines
Requires: db.php, send_email.php

Functions:
├── generateOTP()              → Create random OTP
├── otpExists($otp, $conn)    → Check uniqueness
├── createAppointmentOTP()    → Generate & store OTP
├── verifyAppointmentOTP()    → Validate OTP
├── updateOTPStatus()         → Change status
├── logOTPAttempt()          → Audit logging
├── getAppointmentOTP()       → Retrieve OTP data
├── isOTPValid()             → Check expiry
└── getPatientPendingOTPs()  → List patient OTPs
```

#### 2. **send_email.php** - Email Delivery
```
Location: /send_email.php
Size: ~300 lines
Requires: PHPMailer or PHP mail()

Functions:
├── sendEmail()                        → Main email function
├── sendEmailWithPHPMailer()          → PHPMailer method
├── sendEmailWithPhpMail()            → Fallback method
├── getOTPEmailTemplate()             → Format OTP email
└── getOTPVerificationEmailTemplate() → Verification email
```

#### 3. **verify-appointment-otp.php** - Staff Interface
```
Location: /verify-appointment-otp.php
Size: ~400 lines
Requires: db.php, auth.php, otp-management.php
Access: Admin/Staff login required

Features:
├── OTP verification form
├── Appointment list display
├── Success confirmation
└── Error handling
```

#### 4. **my-appointments-otp.php** - Patient Portal
```
Location: /my-appointments-otp.php
Size: ~350 lines
Requires: db.php
Access: Patient login required

Features:
├── Appointment list
├── OTP code display
├── Status tracking
└── Expiry information
```

#### 5. **otp-reports.php** - Admin Dashboard
```
Location: /otp-reports.php
Size: ~380 lines
Requires: db.php, auth.php, otp-management.php
Access: Admin login required

Features:
├── OTP statistics
├── Records table
├── Resend functionality
└── Audit trail
```

---

## 🗄️ Database Schema

### Table: appointment_otps

```sql
CREATE TABLE appointment_otps (
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
    created_by INT NULL,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES admins(id),
    
    INDEX idx_appointment (appointment_id),
    INDEX idx_patient (patient_id),
    INDEX idx_status (verification_status),
    INDEX idx_expires (expires_at)
);
```

### Table: otp_verification_logs

```sql
CREATE TABLE otp_verification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    otp_id INT NOT NULL,
    attempted_otp VARCHAR(6) NOT NULL,
    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    attempt_status ENUM('Success', 'Failed', 'Expired') DEFAULT 'Failed',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    FOREIGN KEY (otp_id) REFERENCES appointment_otps(id),
    INDEX idx_otp (otp_id),
    INDEX idx_time (attempt_time)
);
```

### Query Examples

**Get OTP for appointment:**
```sql
SELECT * FROM appointment_otps 
WHERE appointment_id = 123
ORDER BY generated_at DESC LIMIT 1;
```

**Get pending OTPs:**
```sql
SELECT * FROM appointment_otps 
WHERE verification_status = 'Pending'
AND expires_at > NOW();
```

**Get verification attempts:**
```sql
SELECT * FROM otp_verification_logs 
WHERE otp_id = 456
ORDER BY attempt_time DESC;
```

---

## 🔐 Security Implementation

### OTP Generation Algorithm

```php
// Location: otp-management.php, generateOTP()

function generateOTP() {
    $otp = '';
    for ($i = 0; $i < OTP_LENGTH; $i++) {
        $otp .= random_int(0, 9);  // Cryptographically random
    }
    return $otp;
}

// Configuration
define('OTP_LENGTH', 6);           // 1 million combinations
define('OTP_EXPIRY_HOURS', 24);    // 24-hour validity
define('MAX_VERIFICATION_ATTEMPTS', 5);
```

### Input Validation

```php
// All user inputs sanitized
$appointment_id = (int)$_POST['appointment_id'];      // Integer cast
$otp = preg_replace('/[^0-9]/', '', $otp);            // Numeric only
$status = $conn->real_escape_string($status);         // String escape

// Prepared statements prevent SQL injection
$stmt = $conn->prepare('SELECT * FROM appointment_otps WHERE otp_code = ?');
$stmt->bind_param('s', $otp);
$stmt->execute();
```

### XSS Prevention

```php
// All output HTML-escaped
echo htmlspecialchars($appointment['patient_name']);
echo htmlspecialchars($otp_code);

// In email templates
$body = "Patient Name: " . htmlspecialchars($patientName);
```

### Rate Limiting

```php
// Maximum 5 verification attempts
if ($otpRecord['verification_attempts'] >= MAX_VERIFICATION_ATTEMPTS) {
    return ['success' => false, 'message' => 'Maximum attempts exceeded'];
}
```

### Audit Logging

```php
// Every verification attempt logged
logOTPAttempt($otpId, $attemptedOtp, $status, $conn);

// Captured data:
// - IP address: $_SERVER['REMOTE_ADDR']
// - Browser: $_SERVER['HTTP_USER_AGENT']
// - Timestamp: CURRENT_TIMESTAMP
// - Result: Success/Failed/Expired
```

---

## 📧 Email Configuration

### SMTP Settings (send_email.php)

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SENDER_EMAIL', 'your-email@gmail.com');
define('SENDER_NAME', 'Hospital Management System');
```

### Email Method Selection

```
Priority Order:
1. PHPMailer (if installed) → Most reliable
2. PHP mail() function → Fallback for shared hosting
3. Custom SMTP → For advanced configurations
```

### Template Variables

**OTP Email Template:**
```php
getOTPEmailTemplate(
    $patientName,      // string
    $doctorName,       // string
    $department,       // string
    $appointmentDate,  // string (YYYY-MM-DD)
    $appointmentTime,  // string (HH:MM:SS)
    $otp              // string (6 digits)
)
```

Returns: HTML-formatted email body

---

## 🔄 Data Flow Diagrams

### Appointment Approval → OTP Generation

```
1. Admin views admin-appointments.php
2. Selects appointment
3. Changes status to "Approved"
4. Clicks "Save" (POST request)
   ↓
5. admin-appointments.php receives POST
6. Calls createAppointmentOTP()
   ↓
7. generateOTP() creates unique code
8. OTP stored in appointment_otps table
9. Expiry calculated (now + 24 hours)
   ↓
10. sendEmail() invoked
11. Email sent to patient
    ↓
12. Success message displayed
13. OTP code shown to admin
```

### Patient Verification at Reception

```
1. Patient provides appointment ID
2. Staff opens verify-appointment-otp.php
3. Enters appointment ID + OTP (POST)
   ↓
4. verifyAppointmentOTP() called
5. Locates OTP record
6. Validates status (not already verified)
7. Checks expiry time
8. Verifies attempts count
   ↓
9. Matches OTP code
10. Updates status to "Verified"
11. Records verified_at timestamp
    ↓
12. Returns appointment details
13. Display patient info to staff
```

---

## 🧪 Testing Guide

### Unit Tests

**Test OTP Generation:**
```php
// Should generate 6-digit numeric code
$otp = generateOTP();
assert(strlen($otp) === 6);
assert(is_numeric($otp));
assert(otpExists($otp) === false); // Should be unique
```

**Test OTP Verification:**
```php
// Should verify correct OTP
$result = verifyAppointmentOTP($otp, $appointmentId, $conn);
assert($result['success'] === true);
assert($result['data']['patient_name'] !== null);

// Should reject expired OTP
// Modify expires_at to past time
$result = verifyAppointmentOTP($otp, $appointmentId, $conn);
assert($result['success'] === false);
```

### Integration Tests

**End-to-End Flow:**
```
1. Create appointment in database
2. Call createAppointmentOTP()
3. Verify OTP generated and stored
4. Call verifyAppointmentOTP()
5. Verify status updated to "Verified"
6. Check audit log entry created
```

---

## 📊 Database Queries for Analysis

### OTP Success Rate
```sql
SELECT 
    COUNT(*) as total_otps,
    SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) as verified,
    ROUND(100.0 * SUM(CASE WHEN verification_status = 'Verified' THEN 1 ELSE 0 END) / COUNT(*), 2) as success_rate
FROM appointment_otps;
```

### Average Verification Attempts
```sql
SELECT 
    AVG(verification_attempts) as avg_attempts,
    MAX(verification_attempts) as max_attempts,
    MIN(verification_attempts) as min_attempts
FROM appointment_otps
WHERE verification_status = 'Verified';
```

### Failed Verification Attempts by IP
```sql
SELECT 
    ip_address,
    COUNT(*) as attempt_count,
    COUNT(CASE WHEN attempt_status = 'Failed' THEN 1 END) as failed_attempts
FROM otp_verification_logs
GROUP BY ip_address
ORDER BY failed_attempts DESC;
```

---

## 🚀 Performance Optimization

### Database Indexes
```sql
-- Indexes for fast lookup
CREATE INDEX idx_appointment ON appointment_otps(appointment_id);
CREATE INDEX idx_patient ON appointment_otps(patient_id);
CREATE INDEX idx_status ON appointment_otps(verification_status);
CREATE INDEX idx_expires ON appointment_otps(expires_at);
```

### Query Optimization
```php
// Use LIMIT 1 for unique lookups
SELECT * FROM appointment_otps WHERE appointment_id = ? LIMIT 1;

// Use prepared statements (prevents full table scans)
$stmt = $conn->prepare('SELECT * FROM appointment_otps WHERE id = ?');
$stmt->bind_param('i', $id);

// Avoid N+1 queries with JOINs
SELECT ao.*, a.*, p.*, d.*
FROM appointment_otps ao
JOIN appointments a ON ao.appointment_id = a.id
JOIN patients p ON ao.patient_id = p.id
JOIN doctors d ON a.doctor_id = d.id;
```

---

## 🔧 Extension Points

### Custom Email Templates

Create custom template:
```php
function getCustomOTPEmailTemplate($patientName, $otp) {
    return "
    <html>
        <body>
            <h1>Your OTP: {$otp}</h1>
        </body>
    </html>";
}
```

Use in createAppointmentOTP():
```php
$emailBody = getCustomOTPEmailTemplate(
    $appointment['patient_name'],
    $otp
);
```

### Webhook Notifications

Add to verifyAppointmentOTP():
```php
// Send webhook to external system
$ch = curl_init('https://external-system.com/webhook');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'appointment_id' => $appointmentId,
    'patient_id' => $patientId,
    'verified_at' => $verifiedTime
]));
curl_exec($ch);
```

### SMS Notifications

Add to createAppointmentOTP():
```php
// Send SMS via Twilio or similar
require_once 'vendor/autoload.php';
$twilio = new Twilio\Rest\Client($account, $token);
$twilio->messages->create(
    $patientPhone,
    ['from' => $twilioNumber, 'body' => "Your OTP: {$otp}"]
);
```

---

## 📋 Maintenance Scripts

### Archive Old OTPs
```php
// Run monthly
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
$conn->query("DELETE FROM appointment_otps WHERE generated_at < '$thirtyDaysAgo' AND verification_status != 'Verified'");
$conn->query("DELETE FROM otp_verification_logs WHERE attempt_time < '$thirtyDaysAgo'");
```

### Email Delivery Report
```sql
-- Check email delivery stats
SELECT DATE(generated_at) as date, COUNT(*) as total_otps
FROM appointment_otps
WHERE generated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(generated_at)
ORDER BY date DESC;
```

---

## 🐛 Common Issues & Solutions

### Issue: OTP Not Sending

**Cause:** Email configuration error
**Solution:** Check `send_email.php` SMTP settings

```php
// Test email function
$test = sendEmail('test@example.com', 'Test', 'Testing OTP System');
if (!$test) {
    error_log('Email sending failed');
}
```

### Issue: OTP Expired Too Quickly

**Cause:** Expiry time calculation wrong
**Solution:** Check `otp-management.php` line defining expiry

```php
$expiryTime = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_HOURS . ' hours'));
// Should add hours to current time
```

### Issue: Verification Always Fails

**Cause:** OTP mismatch or status issue
**Solution:** Debug verification

```php
// Add debug logging
error_log("Attempting OTP: " . $otp);
error_log("OTP Record Status: " . $otpRecord['verification_status']);
error_log("OTP Expires: " . $otpRecord['expires_at']);
error_log("Current Time: " . date('Y-m-d H:i:s'));
```

---

## 📚 References

- [PHP MySQLi Documentation](https://www.php.net/manual/en/book.mysqli.php)
- [PHPMailer GitHub](https://github.com/PHPMailer/PHPMailer)
- [OWASP Security Guidelines](https://owasp.org/)
- [Database Indexing Best Practices](https://dev.mysql.com/doc/)

---

## 👨‍💻 Code Standards

### Naming Conventions
- **Functions:** camelCase: `createAppointmentOTP()`
- **Variables:** camelCase: `$appointmentId`
- **Constants:** UPPER_SNAKE_CASE: `OTP_LENGTH`
- **Database:** snake_case: `appointment_otps`

### Comment Style
```php
/**
 * Brief description
 * 
 * @param type $param Description
 * @return type Description
 */
function functionName($param) {
    // Implementation
}
```

### Error Handling
```php
try {
    // Code that might fail
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    return ['success' => false, 'message' => 'An error occurred'];
}
```

---

Document Version: 1.0
Last Updated: 2024
