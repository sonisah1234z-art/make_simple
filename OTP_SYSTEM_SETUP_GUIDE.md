# Appointment Verification OTP System - Setup & Implementation Guide

## 📋 Overview

This document provides complete setup instructions for the Appointment Verification OTP System implemented in your Hospital Management System. The system automatically generates and sends OTPs to patients when their appointments are approved, and allows hospital staff to verify patients using these OTPs.

---

## 🔧 Installation Steps

### Step 1: Create Database Tables

Execute the SQL script to create necessary tables:

1. Open phpMyAdmin or your MySQL client
2. Select your `hospital_system` database
3. Import or execute the SQL from `otp-database-setup.sql`

The script creates two tables:
- **appointment_otps** - Stores OTP records with verification status
- **otp_verification_logs** - Audit log for OTP verification attempts

```sql
-- Tables created:
-- 1. appointment_otps (OTP storage with status tracking)
-- 2. otp_verification_logs (Security audit log)
```

### Step 2: Configure Email Settings

Edit `send_email.php` and configure your email settings:

```php
// Lines 4-7 in send_email.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');      // Your Gmail
define('SMTP_PASSWORD', 'your-app-password');          // Gmail App Password
define('SENDER_EMAIL', 'your-email@gmail.com');
define('SENDER_NAME', 'Hospital Management System');
```

#### For Gmail Users:
1. Enable 2-Step Verification on your Gmail account
2. Generate an App Password: https://support.google.com/accounts/answer/185833
3. Use the 16-character app password in `SMTP_PASSWORD`

#### For Other Providers:
- Gmail/Google Workspace: SMTP Host: `smtp.gmail.com`, Port: `587`
- Outlook: SMTP Host: `smtp-mail.outlook.com`, Port: `587`
- Custom Server: Ask your hosting provider for SMTP details

### Step 3: Files Included

The following new files have been created:

| File | Purpose |
|------|---------|
| `otp-database-setup.sql` | Database schema |
| `send_email.php` | Email utility functions |
| `otp-management.php` | OTP generation & verification logic |
| `verify-appointment-otp.php` | **Staff OTP verification interface** |
| `my-appointments-otp.php` | **Patient OTP status page** |
| `otp-reports.php` | **Admin OTP management dashboard** |

Modified Files:
- `admin-appointments.php` - Now generates and sends OTP on approval

---

## 🎯 How It Works

### 1. Appointment Approval Flow

```
Patient Books Appointment → Admin Approves → OTP Generated → Email Sent to Patient
```

**When Admin Approves Appointment:**
- Admin clicks "Save" on appointment status change to "Approved"
- System automatically generates a 6-digit random OTP
- OTP is stored in database with 24-hour expiry
- Email with OTP and appointment details sent to patient

### 2. Patient Verification at Hospital

```
Patient Arrives → Staff enters OTP → Verification Success → Appointment Details Displayed
```

**Patient Process:**
- Patient receives email with OTP code
- Keeps OTP for hospital visit
- At reception: Staff enters appointment ID + OTP
- System verifies OTP and displays appointment details
- Patient proceeds for consultation

### 3. Staff Verification

```
Reception Staff → Access Verify Page → Enter Appointment ID + OTP → Confirm Patient
```

---

## 📱 User Interfaces

### For Admin/Managers

#### 1. Appointment Management (`admin-appointments.php`)
- **Location:** `/admin-appointments.php`
- **Access:** Admin login required
- **Features:**
  - View all appointments
  - Change status to Approved (triggers OTP)
  - See generated OTP code
  - Confirmation that email was sent

**Steps:**
1. Go to Appointments page
2. Select status "Approved"
3. Click "Save"
4. System shows success message with OTP code

#### 2. OTP Verification (`verify-appointment-otp.php`)
- **Location:** `/verify-appointment-otp.php`
- **Access:** Admin/Reception login required
- **Features:**
  - Verify patient OTP
  - View approved appointments list
  - See OTP status (Pending/Verified/Expired)
  - Display patient details after verification

**Steps:**
1. Patient arrives at hospital
2. Staff opens OTP Verification page
3. Enter Appointment ID (from appointment confirmation)
4. Enter 6-digit OTP (from patient's email)
5. Click "Verify OTP"
6. System displays appointment details confirming verification

#### 3. OTP Management (`otp-reports.php`)
- **Location:** `/otp-reports.php`
- **Access:** Admin login required
- **Features:**
  - View all OTP records with statistics
  - Monitor OTP status (Pending/Verified/Expired)
  - Resend OTP if needed
  - View verification attempts
  - Audit trail of all OTPs

**Statistics Displayed:**
- Total OTPs Generated
- Pending Verification
- Successfully Verified
- Expired OTPs

### For Patients

#### Patient OTP Status Page (`my-appointments-otp.php`)
- **Location:** `/my-appointments-otp.php`
- **Access:** Patient login required
- **Features:**
  - View all appointments
  - Display OTP code
  - Show OTP status and expiry time
  - Confirmation of verification
  - Doctor and appointment details

**Patient Steps:**
1. Log in to patient dashboard
2. Go to "My Appointments & OTP Status"
3. View approved appointments
4. See OTP code displayed
5. Use OTP at hospital reception

---

## 🔐 Security Features Implemented

### 1. OTP Generation
- ✅ Random 6-digit numeric OTP
- ✅ Unique per appointment
- ✅ Cryptographically random generation

### 2. OTP Validation
- ✅ Valid for 24 hours (configurable)
- ✅ Automatically expires after set time
- ✅ One-time use only (can't be reused)
- ✅ Marked as verified after first successful use

### 3. Verification Protection
- ✅ Maximum 5 verification attempts allowed
- ✅ OTP locked after max attempts
- ✅ IP address logging for each attempt
- ✅ User agent (browser) tracking

### 4. Data Security
- ✅ OTP stored in database (not displayed in URL)
- ✅ Email validation
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS prevention (HTML escaping)
- ✅ CSRF protection via POST method

### 5. Audit Trail
- ✅ All verification attempts logged
- ✅ Success/failure tracking
- ✅ Timestamp of each attempt
- ✅ IP address and browser info recorded

---

## ⚙️ Configuration Options

Edit `otp-management.php` (Lines 7-9) to customize:

```php
define('OTP_LENGTH', 6);                    // OTP digits (default: 6)
define('OTP_EXPIRY_HOURS', 24);            // Validity period (default: 24 hours)
define('MAX_VERIFICATION_ATTEMPTS', 5);     // Max wrong attempts allowed
```

---

## 🧪 Testing the System

### Test Scenario 1: Basic OTP Flow

1. **Create Test Appointment**
   - Patient logs in
   - Books appointment with available doctor

2. **Admin Approves**
   - Admin logs in → Appointments page
   - Select "Approved" status
   - Click Save
   - Note the displayed OTP code

3. **Patient Receives Email**
   - Check patient's email inbox
   - Verify email contains:
     - OTP code
     - Doctor name
     - Department
     - Appointment date/time

4. **Staff Verifies at Reception**
   - Staff opens `verify-appointment-otp.php`
   - Enter appointment ID
   - Enter OTP from patient
   - Click "Verify OTP"
   - Confirm appointment details display

### Test Scenario 2: OTP Expiry

1. Approve an appointment
2. Wait 24+ hours
3. Try to verify OTP
4. System should show "OTP has expired"

### Test Scenario 3: Failed Attempts

1. Approve an appointment
2. Try wrong OTP 5 times
3. On 6th attempt, system should lock OTP

---

## 📧 Email Configuration Troubleshooting

### Issue: Emails not sending

**Check 1: Gmail Account**
```
- 2-Step Verification enabled? Yes ✓
- App Password generated? Yes ✓
- App Password correct (16 chars)? Yes ✓
```

**Check 2: PHP Configuration**
```php
// In send_email.php, verify:
require_once __DIR__ . '/vendor/autoload.php';
// If error, PHPMailer is not installed
```

**Check 3: Server Settings**
- Contact hosting provider
- Ask if SMTP sending is allowed
- Verify firewall allows port 587

**Fallback Option:**
System uses PHP `mail()` function if PHPMailer unavailable:
```php
// In send_email.php, function sendEmailWithPhpMail()
// This is less reliable but may work on shared hosting
```

---

## 📊 Database Schema

### appointment_otps Table

| Column | Type | Purpose |
|--------|------|---------|
| id | INT | Primary key |
| appointment_id | INT | Reference to appointment |
| patient_id | INT | Reference to patient |
| otp_code | VARCHAR(6) | 6-digit OTP |
| generated_at | DATETIME | When OTP was created |
| expires_at | DATETIME | When OTP expires |
| verified_at | DATETIME | When OTP was verified |
| verification_status | ENUM | Pending/Verified/Expired |
| verification_attempts | INT | Failed attempts count |
| created_by | INT | Admin ID who approved |

### otp_verification_logs Table

| Column | Type | Purpose |
|--------|------|---------|
| id | INT | Primary key |
| otp_id | INT | Reference to OTP record |
| attempted_otp | VARCHAR(6) | What was entered |
| attempt_time | DATETIME | When attempt was made |
| attempt_status | ENUM | Success/Failed/Expired |
| ip_address | VARCHAR(45) | Client IP |
| user_agent | TEXT | Browser info |

---

## 🚀 Advanced Features

### Resend OTP
If patient didn't receive email:
1. Go to OTP Reports page
2. Find the appointment
3. Click "Resend" button
4. Previous OTP invalidated
5. New OTP generated and sent

### View OTP History
Admin can view:
- When each OTP was generated
- When patient verified
- How many attempts were made
- IP addresses of verifications

### Export OTP Records
Data can be exported for:
- Compliance reporting
- Security audits
- Patient records
- Hospital analytics

---

## 🛠️ Maintenance Tasks

### Weekly
- Check OTP Reports dashboard
- Review any failed verifications
- Ensure email sending is working

### Monthly
- Review verification logs for suspicious patterns
- Archive old OTP records (older than 90 days)
- Check email configuration

### Quarterly
- Review security settings
- Update OTP expiry policies if needed
- Audit verification attempts

---

## 📝 API Reference

### For Developers

#### Generate OTP
```php
require_once 'otp-management.php';

$result = createAppointmentOTP($appointmentId, $patientId, $conn, $adminId);

// Returns:
// ['success' => bool, 'otp' => string, 'message' => string, 'email_sent' => bool]
```

#### Verify OTP
```php
$result = verifyAppointmentOTP($otp, $appointmentId, $conn);

// Returns:
// ['success' => bool, 'message' => string, 'data' => array]
```

#### Get OTP Details
```php
$otp = getAppointmentOTP($appointmentId, $conn);

// Returns OTP record or null
```

---

## ❓ FAQ

**Q: What if patient loses their OTP email?**
A: Admin can resend OTP from OTP Reports page. Previous OTP becomes invalid.

**Q: Can OTP be used for multiple appointments?**
A: No, each OTP is unique per appointment. Different appointments need different OTPs.

**Q: What happens if OTP expires?**
A: Patient must request new appointment or admin must resend OTP.

**Q: Is OTP case-sensitive?**
A: No, OTP is numeric only (0-9).

**Q: How long is OTP valid?**
A: Default is 24 hours. Configurable in `otp-management.php`.

**Q: Can same OTP be verified twice?**
A: No, OTP is marked "Verified" after first use and cannot be reused.

---

## 📞 Support & Issues

If you encounter issues:

1. **Check Database Tables Exist**
   ```sql
   SHOW TABLES LIKE 'appointment_otps';
   ```

2. **Verify Email Configuration**
   - Test SMTP credentials
   - Check PHP error logs

3. **Review Logs**
   - Check `otp_verification_logs` table
   - Look for error patterns

4. **Enable Debug Mode**
   ```php
   // In send_email.php
   $mail->SMTPDebug = 2; // Shows SMTP conversation
   ```

---

## 📄 License & Credits

Appointment Verification OTP System
- Secure 6-digit OTP generation
- Automated email delivery
- Real-time verification
- Complete audit trail

Developed for Hospital Management System
