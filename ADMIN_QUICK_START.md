# Hospital OTP System - Admin Quick Start

## 🏥 For Hospital Reception & Admin Staff

### 3 Pages You Need to Know

| Page | What It Does | Link |
|------|-------------|------|
| **Approve Appointments** | Admin approves & OTP auto-sends | admin-appointments.php |
| **Verify Patient OTP** | Receptionist verifies arriving patients | verify-appointment-otp.php |
| **View OTP Status** | Admin manages OTP system | otp-reports.php |

---

## 📋 TASK 1: Approving Appointment (Admin)

### When?
Whenever you see "Pending" appointment that should be confirmed.

### Steps:
1. Log in as Admin
2. Go to **Appointments** menu
3. Find the appointment to approve
4. Select **"Approved"** from dropdown
5. Click **"Save"**
6. **✓ Done!** OTP automatically sent to patient via email

### What Happens?
- Patient receives email with OTP
- Email contains: Doctor, Date, Time, OTP Code
- OTP valid for 24 hours
- OTP displayed on screen (for your records)

```
Example: 
Status: Pending
Select: Approved
Click: Save
↓
System: "Appointment approved! OTP generated and sent."
OTP: 342891 (shown on screen)
```

---

## 🔐 TASK 2: Verify Patient Arrival (Reception)

### When?
When patient arrives at hospital for appointment.

### Steps:

**Step 1: Open Verification Page**
- Go to: **verify-appointment-otp.php**
- Or click from Admin Menu

**Step 2: Get Information from Patient**
- Ask: "What's your Appointment ID?"
- Ask: "What's your OTP code?"
- Patient has this in their email confirmation

**Step 3: Enter Information**
```
Appointment ID: [____]    ← 3-digit number
OTP Code:      [____]    ← 6-digit from email
```

**Step 4: Click "Verify OTP"**

**Step 5: Check Result**

✅ **SUCCESS**: 
- Green message appears
- Shows patient name ✓
- Shows doctor name ✓
- Shows appointment details ✓
- **Patient ready for consultation**

❌ **FAILURE - Possible Reasons**:
| Error | Reason | Solution |
|-------|--------|----------|
| "Invalid OTP" | Wrong code or ID | Ask patient to check email |
| "OTP Expired" | Older than 24 hours | Admin must resend |
| "Already Used" | Already verified | Contact admin |
| "Max Attempts" | Too many wrong tries | Admin must resend |

---

## 📊 TASK 3: Check OTP Status (Admin)

### When?
- Daily to monitor system
- If patient says "didn't get email"
- To resend OTP if expired
- To check verification success

### Access:
Go to: **otp-reports.php**

### What You See:

**Top Section: Statistics**
```
Total OTPs: 156
Pending: 23      (waiting for verification)
Verified: 131    (successfully verified)
Expired: 2       (older than 24 hours)
```

**Bottom Section: All OTPs Table**
- Appointment ID
- Patient name
- Doctor name
- OTP code
- Status (Pending/Verified/Expired)
- When generated
- When expires
- Verification attempts

### Common Actions:

**Patient Says: "I didn't receive OTP email"**
1. Find appointment in table
2. Click **"Resend"** button
3. Confirm action
4. ✓ New OTP generated
5. ✓ Email sent again

**Check If Patient Verified**
1. Find appointment
2. Look at "Status" column
3. ✓ Verified = All good
4. ⏳ Pending = Not yet verified
5. ✗ Expired = Too old, need new one

**Monitor System Health**
- Check statistics at top
- High "Verified" = Good ✓
- High "Pending" = Maybe remind patients
- High "Expired" = Might need to resend

---

## 👤 TASK 4: Show Patients (Patient Portal)

### Where?
**my-appointments-otp.php**

### What Patients See:
- All their appointments
- OTP code (once approved)
- When OTP expires
- Status (Pending/Verified/Approved)
- Doctor name
- Appointment date & time

### Tell Patients:
"Log in to see your appointments and OTP code here"

---

## 📞 COMMON SCENARIOS

### Scenario: Patient Doesn't Have OTP

**Patient:** "I don't have my OTP code"

**You:**
1. Check if appointment approved (status should be "Approved")
   - If "Pending" → Tell patient "Hospital is reviewing your appointment"
   - If "Approved" → Continue...

2. Ask: "Did you receive appointment confirmation email?"
   - If "No" → Go to OTP Reports, click "Resend"
   - If "Yes" → Ask them to check email again (might be in spam)

3. If still no email → Go to OTP Reports, click "Resend"
4. New OTP sent, patient should receive within minutes

### Scenario: Patient Provides Wrong OTP

**Patient:** (provides wrong code)

**System:** "Invalid OTP"

**You:**
1. Ask patient to check email again
2. Verify they read code correctly
3. If still wrong after 3 tries → Check OTP Reports
4. If too many attempts (5+) → Request admin to resend

### Scenario: Patient Arrives Late

**Patient:** "Is my OTP still valid?"

**You:**
1. Go to verify-appointment-otp.php
2. Try the OTP
3. If works → Patient is verified ✓
4. If expired → Contact admin to resend

### Scenario: Same Patient, Multiple Appointments

**Patient:** "I have 2 appointments"

**You:**
1. Each appointment has different OTP
2. Patient must provide correct appointment ID
3. Verify with correct OTP
4. Repeat for second appointment

---

## ⚠️ IMPORTANT RULES

### OTP Rules:
✅ **Valid:** OTP works for 24 hours
✅ **Unique:** Each appointment gets own OTP
✅ **One-Time:** After verification, cannot be reused
✅ **Secure:** 6-digit random code, hard to guess

❌ **Invalid:** 
- OTP older than 24 hours
- Already used once
- Wrong appointment ID
- Too many wrong attempts

### What NOT to Do:
- ❌ Don't give OTP to someone who didn't receive email
- ❌ Don't manually change OTP status
- ❌ Don't share patient's OTP with others
- ❌ Don't verify without patient presenting

### What TO Do:
- ✅ Always ask patient for appointment ID
- ✅ Always ask patient for OTP
- ✅ Verify before allowing consultation
- ✅ Document if patient had verification issues
- ✅ Contact admin if OTP problems

---

## 🆘 QUICK TROUBLESHOOTING

| Problem | Cause | Fix |
|---------|-------|-----|
| "Can't access verify page" | Not logged in as admin | Log in first |
| "No approved appointments shown" | None approved yet | Admin needs to approve |
| "Email not received" | Spam/wrong address | Ask patient to check spam |
| "Verification fails" | Wrong code/ID or expired | Check if within 24 hours |
| "System says already verified" | Used already | Normal if patient verified already |

**Stuck?** Contact IT/Admin for help

---

## 📱 QUICK ACCESS

**Staff Verification Page**
```
Direct Link: verify-appointment-otp.php
Bookmark this for quick access!
```

**Admin OTP Dashboard**
```
Direct Link: otp-reports.php
Use for monitoring and resends
```

**Patient OTP Portal**
```
Direct Link: my-appointments-otp.php
Tell patients to log in here
```

---

## ✅ DAILY CHECKLIST

Every morning, admin should:

- [ ] Check OTP Reports page
- [ ] Note pending OTP count
- [ ] Review any resend requests
- [ ] Verify email sending works
- [ ] Document any issues

---

## 📊 SHIFT HANDOVER

When changing shift:

**Outgoing Staff:**
- "X patients verified today"
- "X appointments still pending"
- "Any emails not received? → List"

**Incoming Staff:**
- Check OTP Reports dashboard
- Know current pending count
- Know which patients might need help

---

## 🎓 TRAINING TIPS

**For New Staff:**
1. Show where to find verify-appointment-otp.php
2. Do one practice verification together
3. Explain what "Pending", "Verified", "Expired" mean
4. Give them this quick reference card
5. Practice with test appointments

**For Ongoing:**
- Weekly review of OTP Reports
- Monthly security briefing
- Track any issues or patterns

---

## 💡 TIPS FOR SMOOTH OPERATION

### For Faster Service:
- Keep link to verify page bookmarked
- Pre-fill appointment ID if you know it
- Have patient ready with OTP before they reach desk

### For Better Experience:
- Explain to patient: "This OTP verifies your appointment"
- Be patient if they can't find email
- Offer to resend OTP if needed (through admin)

### For Security:
- Don't write down OTP
- Don't share patient OTP with others
- Always verify before allowing treatment
- Ask patient verbally, not written

---

## 📞 HELP CONTACTS

**System Issue?** → Contact IT
**Patient Issue?** → Check OTP Reports or Resend
**Admin Question?** → Ask Hospital Manager
**Verification Error?** → Restart browser and try again

---

**Remember:** OTP System keeps hospital safe by verifying real appointments!

**Questions? Ask your supervisor or IT team.**

---

Quick Reference Card
Hospital Appointment OTP System
Version 1.0 - 2024
