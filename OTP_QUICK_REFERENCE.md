# Hospital Appointment OTP System - Quick Reference Guide

## 🏥 For Hospital Staff (Reception/Admin)

### Where to Access OTP Verification?
- **URL:** `verify-appointment-otp.php`
- **Required:** Admin/Staff login
- **Menu:** Navigate from Admin Dashboard

### How to Verify a Patient's Appointment

1. **Patient Arrives at Reception**
   - Patient provides their appointment confirmation or appointment ID

2. **Open OTP Verification Page**
   - Go to `verify-appointment-otp.php`

3. **Enter Information**
   - **Appointment ID:** Patient's appointment ID (numeric)
   - **OTP Code:** 6-digit code from patient's email

4. **Click "Verify OTP"**
   - System verifies the code
   - If valid: Shows patient name, doctor, date/time
   - If invalid: Shows error message

5. **Approve Patient**
   - After verification, patient can proceed
   - Document verification in patient file if needed

### OTP Verification Codes Meaning

| Status | Meaning |
|--------|---------|
| ✓ Verified | Patient confirmed, ready for consultation |
| ✗ Invalid OTP | Code doesn't match, ask patient to check email |
| ✗ Expired | OTP older than 24 hours, patient needs new one |
| ✗ Already Used | OTP already verified before |
| ✗ Max Attempts | Too many wrong tries, need new OTP |

### What to Do If Patient Doesn't Have OTP?

1. **Check Email** - Ask patient to check spam folder
2. **Resend OTP** - Go to OTP Management, click "Resend"
3. **Contact Admin** - Admin can verify manually

---

## 👨‍⚕️ For Hospital Admin

### Dashboard Access
- **URL:** `otp-reports.php`
- **Menu:** Admin Dashboard → OTP Management

### Key Tasks

#### 1. Approve Appointments with OTP
- Go to `admin-appointments.php`
- Select appointment
- Change status to "Approved"
- Click "Save"
- OTP generated automatically
- Email sent to patient

#### 2. Monitor OTP Status
- Open OTP Reports dashboard
- View statistics:
  - Total OTPs generated
  - Pending verifications
  - Successfully verified
  - Expired OTPs

#### 3. Manage OTP Issues
**OTP Not Received?**
- Go to OTP Reports
- Find appointment row
- Click "Resend" button
- New OTP generated and emailed

**Patient Couldn't Verify?**
- Check if OTP expired (24 hour limit)
- Resend if expired
- Check verification attempts (max 5)

#### 4. View Audit Trail
- Each verification logged with:
  - Timestamp
  - IP address
  - Success/failure
  - Attempts made

---

## 👤 For Patients

### Will I Automatically Get an OTP?
Yes! When your appointment is approved:
- You receive an email with your OTP
- Email includes doctor name, date, time
- OTP is valid for 24 hours

### What's in the Confirmation Email?
```
Subject: Appointment Approved - Verification OTP

Contains:
- Your 6-digit OTP code
- Doctor's name
- Appointment date and time
- Department
- Instructions for hospital visit
```

### How to Use OTP at Hospital?
1. **Keep Email Safe** - Save appointment email or note OTP
2. **Arrive at Hospital** - Go to reception 10 minutes early
3. **Give OTP** - Tell receptionist your OTP
4. **Get Verified** - Receptionist enters code
5. **Proceed** - Receptionist directs you to doctor

### Lost Your OTP Email?
1. Check spam folder
2. Check promotions folder
3. Contact hospital reception
4. Hospital can send new OTP

### Where's Your OTP Status Page?
- **URL:** `my-appointments-otp.php`
- **Login:** Use your patient login
- **See:** All appointments and OTP status
- **Shows:** OTP code, expiry date, if verified

---

## ❓ Common Questions & Answers

### Q: What's an OTP?
**A:** OTP = One-Time Password. A 6-digit code unique to your appointment. Proof it's a real approved appointment.

### Q: Why do I need OTP?
**A:** Confirms your appointment is genuine and approved by the hospital before treatment.

### Q: How long is OTP valid?
**A:** 24 hours from when your appointment was approved.

### Q: Can I reuse OTP?
**A:** No, after verification it expires immediately.

### Q: What if I forget my OTP?
**A:** Check your email again or contact hospital. They can resend.

### Q: What if OTP doesn't work?
**A:** It might be:
- Expired (older than 24 hours)
- Already used
- Entered incorrectly
- Wrong appointment ID

Contact hospital for help.

### Q: Can I verify appointment before hospital visit?
**A:** No, OTP is verified at hospital reception when you arrive.

### Q: What if I'm late?
**A:** OTP still works as long as not expired (24 hours). Just verify when you arrive.

### Q: Multiple appointments?
**A:** Each appointment gets its own OTP. Keep all emails safe.

---

## 📞 Troubleshooting

### Appointment Status Shows as "Pending"
- **Problem:** Admin hasn't approved yet
- **Solution:** Wait for hospital approval or contact hospital

### Email Not Received
- **Check:**
  1. Spam folder
  2. Promotions tab
  3. Junk folder
- **Solution:** Contact hospital for resend

### OTP Says "Expired"
- **Problem:** More than 24 hours since appointment approval
- **Solution:** Ask hospital to resend new OTP

### "Invalid OTP" Error
- **Problem:** Wrong code or appointment ID entered
- **Check:**
  1. Typed correctly?
  2. Using right appointment ID?
  3. Correct email/OTP?
- **Solution:** Try again or ask staff for help

### "Maximum Attempts Exceeded"
- **Problem:** Tried wrong OTP 5+ times
- **Solution:** Ask hospital admin to resend new OTP

---

## 🔐 Security Tips

1. **Keep OTP Private**
   - Don't share with anyone except hospital staff
   - Don't post on social media
   - Don't reply if asked via email (hospital won't ask)

2. **Use Correct Email**
   - Ensure registration email is correct
   - Update email in patient profile if changed

3. **Check Sender**
   - Email should be from hospital
   - Suspicious emails = contact hospital directly

4. **Verify at Hospital**
   - Only staff should enter OTP
   - You provide it verbally to receptionist

---

## 📱 Quick Access Links

### For Patients
- Patient Dashboard: `dashboard.php`
- My Appointments & OTP: `my-appointments-otp.php`
- Book Appointment: `book-appointment.php`

### For Staff
- OTP Verification: `verify-appointment-otp.php`
- Admin Dashboard: `admin-dashboard.php`

### For Admins
- Manage Appointments: `admin-appointments.php`
- OTP Reports: `otp-reports.php`
- Hospital Dashboard: `admin-dashboard.php`

---

## 📝 Important Contacts

For help with OTP System:
- **Hospital Reception:** [Hospital Phone]
- **IT Support:** [IT Contact]
- **Appointment Issues:** Contact Hospital Admin

---

Last Updated: 2024
Hospital Management System - OTP Verification System
