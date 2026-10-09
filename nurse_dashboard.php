<?php
require_once 'auth.php';
require_nurse();
require_once 'db.php';
require_once 'announcements.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nurse Dashboard</title>

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, sans-serif;
        }

        body{
            background:#f4f6f9;
        }

        .header{
            background:#2c3e50;
            color:white;
            padding:20px;
            text-align:center;
            font-size:28px;
            font-weight:bold;
        }

        .container{
            width:90%;
            margin:30px auto;
        }

        .welcome{
            background:white;
            padding:20px;
            border-radius:10px;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
            margin-bottom:20px;
        }

        .cards{
            display:flex;
            flex-wrap:wrap;
            gap:20px;
            justify-content:center;
        }

        .card{
            width:250px;
            background:white;
            padding:25px;
            border-radius:10px;
            text-align:center;
            box-shadow:0 2px 10px rgba(0,0,0,0.1);
            transition:0.3s;
        }

        .card:hover{
            transform:translateY(-5px);
        }

        .card h3{
            margin-bottom:15px;
            color:#2c3e50;
        }

        .card a{
            display:inline-block;
            padding:10px 20px;
            background:#3498db;
            color:white;
            text-decoration:none;
            border-radius:5px;
        }

        .card a:hover{
            background:#2980b9;
        }

        .footer{
            margin-top:40px;
            background:#2c3e50;
            color:white;
            text-align:center;
            padding:15px;
        }
    </style>
    <link rel="stylesheet" href="assets/css/compact-ui.css">

</head>
<body>

<div class="header" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
    <span>🏥 Nurse Dashboard</span>
    <a href="about.php" style="display:inline-block; padding:10px 16px; background:#38bdf8; color:#082f49; text-decoration:none; border-radius:8px; font-weight:bold;">About Us</a>
</div>

<div class="container">

    <div class="welcome">
        <h2>Welcome <?php echo htmlspecialchars($_SESSION['nurse_name']); ?></h2>
        <p>Manage patient records, attendance, and nursing activities.</p>
    </div>

    <?php $context = get_current_user_context(); echo render_announcements_panel($conn, $context['role'], $context['id'], 3); ?>

    <div class="cards">

        <div class="card">
            <h3>➕ Add Nurse Record</h3>
            <a href="add_nurse_record.php">Open</a>
        </div>

        <div class="card">
            <h3>📋 View Records</h3>
            <a href="view_nurses.php">Open</a>
        </div>

        <div class="card">
            <h3>🩺 Patient Records</h3>
            <a href="view_nurses.php">Open</a>
        </div>

        <div class="card">
            <h3>📊 Attendance</h3>
            <a href="view_nurses.php">Open</a>
        </div>

        <div class="card">
            <h3>🖨 Print Reports</h3>
            <a href="print_nurse.php?id=1">Open</a>
        </div>
        
        <div class="card">
            <h3> 📅 Shift Schedule</h3>
            <a href="view_shifts.php">Open</a>
        </div>

        <div class="card">
            <h3>�️ Leave</h3>
            <a href="leave_request.php">Open</a>
        </div>

        <div class="card">
            <h3>📜 Leave History</h3>
            <a href="leave_history.php">Open</a>
        </div>

        <div class="card">
            <h3>🩺 My Assigned Patients</h3>
            <a href="nurse_assigned_patients.php">Open</a>
        </div>

        <div class="card">
            <h3>👤 Patient Details</h3>
            <a href="nurse_patient_details.php">Open</a>
        </div>

        <div class="card">
            <h3>📈 Vital Signs</h3>
            <a href="nurse_vital_signs.php">Open</a>
        </div>

        <div class="card">
            <h3>💊 Medication Administration</h3>
            <a href="nurse_medication_admin.php">Open</a>
        </div>

        <div class="card">
            <h3>📝 Nursing Notes</h3>
            <a href="nurse_nursing_notes.php">Open</a>
        </div>

        <div class="card">
            <h3>📋 Doctor Instructions</h3>
            <a href="nurse_doctor_instructions.php">Open</a>
        </div>

        <div class="card">
            <h3>💉 IV Fluid Monitoring</h3>
            <a href="nurse_iv_monitoring.php">Open</a>
        </div>

        <div class="card">
            <h3>🩹 Injection/Treatment Records</h3>
            <a href="nurse_treatment_records.php">Open</a>
        </div>

        <div class="card">
            <h3>🧪 Lab Test Status</h3>
            <a href="nurse_lab_test_status.php">Open</a>
        </div>

        <div class="card">
            <h3>🚨 Emergency Alerts</h3>
            <a href="nurse_emergency_alerts.php">Open</a>
        </div>

        <div class="card">
            <h3>🛏 Ward/Room/Bed</h3>
            <a href="nurse_ward_management.php">Open</a>
        </div>

        <div class="card">
            <h3>🧾 Admission/Transfer</h3>
            <a href="nurse_admission_transfer.php">Open</a>
        </div>

        <div class="card">
            <h3>✅ Nursing Tasks</h3>
            <a href="nurse_tasks.php">Open</a>
        </div>

        <div class="card">
            <h3>⏰ Medication Reminders</h3>
            <a href="nurse_medication_reminders.php">Open</a>
        </div>

        <div class="card">
            <h3>📡 Patient Monitoring</h3>
            <a href="nurse_patient_monitoring.php">Open</a>
        </div>

        <div class="card">
            <h3>🔔 Notifications</h3>
            <a href="nurse_notifications.php">Open</a>
        </div>

        <div class="card">
            <h3>👩‍⚕ Nurse Profile</h3>
            <a href="nurse_profile.php">Open</a>
        </div>

        <div class="card">
            <h3>📄 Daily Nursing Reports</h3>
            <a href="nurse_daily_reports.php">Open</a>
        </div>

        <div class="card">
            <h3>�🚪 Logout</h3>
            <a href="logout.php">Logout</a>
        </div>

    </div>

</div>

<div class="footer">
    Hospital Management System © 2026
</div>

</body>
</html>
