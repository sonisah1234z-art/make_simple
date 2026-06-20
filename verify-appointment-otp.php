<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'otp-management.php';

$admin_name = $_SESSION['admin_name'];
$message = '';
$error = '';
$verification_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int)$_POST['appointment_id'] ?? 0;
    $otp = $conn->real_escape_string($_POST['otp'] ?? '');
    
    if ($appointment_id && $otp) {
        $verification_result = verifyAppointmentOTP($otp, $appointment_id, $conn);
        
        if ($verification_result['success']) {
            $message = $verification_result['message'];
        } else {
            $error = $verification_result['message'];
        }
    } else {
        $error = 'Please provide both appointment ID and OTP.';
    }
}

// Get list of approved appointments for verification
$approved_appointments = [];
$result = $conn->query("
    SELECT a.id, p.name AS patient_name, d.name AS doctor_name, d.specialty,
           a.appointment_date, a.appointment_time, a.status,
           ao.verification_status, ao.expires_at
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN appointment_otps ao ON a.id = ao.appointment_id
    WHERE a.status = 'Approved'
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $approved_appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Appointment OTP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
            color: #e2e8f0;
            padding: 24px;
        }
        .page { max-width: 1200px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px; margin-bottom: 28px; }
        h1 { font-size: 34px; }
        .subtitle { color: #94a3b8; margin-top: 6px; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: 16px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 700; cursor: pointer; transition: transform .18s ease; text-decoration: none; }
        .button:hover { transform: translateY(-1px); }
        .card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 28px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22); margin-bottom: 24px; }
        .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
        .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
        .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; color: #cbd5e1; font-weight: 600; }
        input, select { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(148,163,184,0.18); background: rgba(255,255,255,0.08); color: #f8fafc; font-size: 15px; }
        input::placeholder { color: rgba(226,232,240,0.5); }
        input:focus, select:focus { outline: none; border-color: #60a5fa; background: rgba(255,255,255,0.12); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .button-group { display: flex; gap: 12px; margin-top: 20px; }
        .button-group button { flex: 1; padding: 14px 16px; border: none; border-radius: 12px; background: #8b5cf6; color: white; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .button-group button:hover { background: #7c3aed; }
        .table-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
        th { color: #cbd5e1; font-weight: 700; }
        td { color: #e2e8f0; font-size: 14px; }
        .status { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status.Pending { background: rgba(245,158,11,0.16); color: #f59e0b; }
        .status.Verified { background: rgba(34,197,94,0.16); color: #22c55e; }
        .status.Expired { background: rgba(239,68,68,0.16); color: #ef4444; }
        .status.Approved { background: rgba(34,197,94,0.16); color: #22c55e; }
        .verification-details { background: rgba(34,197,94,0.10); border-left: 4px solid #22c55e; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; }
        .detail-label { color: #94a3b8; }
        .detail-value { color: #f8fafc; font-weight: 600; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .button-group { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Appointment OTP Verification</h1>
            <p class="subtitle">Welcome, <?php echo htmlspecialchars($admin_name); ?>. Verify patient appointments using OTP.</p>
        </div>
        <div>
            <a class="button" href="admin-dashboard.php">Back to Dashboard</a>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 12px; font-weight: 600;">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <!-- Verification Form -->
    <div class="card">
        <h2 style="margin-bottom: 20px; color: #f8fafc;">Verify Patient OTP</h2>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($message): ?>
            <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="verify-appointment-otp.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="appointment_id">Appointment ID</label>
                    <input type="number" id="appointment_id" name="appointment_id" placeholder="Enter appointment ID" required>
                </div>
                <div class="form-group">
                    <label for="otp">OTP Code</label>
                    <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}" required>
                </div>
            </div>
            <div class="button-group">
                <button type="submit">Verify OTP</button>
                <button type="reset">Clear</button>
            </div>
        </form>

        <!-- Verification Success Details -->
        <?php if ($verification_result && $verification_result['success']): ?>
            <div class="verification-details">
                <h3 style="margin-bottom: 15px; color: #22c55e;">✓ Appointment Verified Successfully</h3>
                <div class="detail-row">
                    <span class="detail-label">Patient Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($verification_result['data']['patient_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Doctor:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($verification_result['data']['doctor_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Department:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($verification_result['data']['specialty']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Appointment Date:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($verification_result['data']['appointment_date']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Appointment Time:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($verification_result['data']['appointment_time']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Verified At:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($verification_result['data']['verified_at']); ?></span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approved Appointments Table -->
    <div class="table-card">
        <h2 style="margin-bottom: 20px; color: #f8fafc;">Approved Appointments for Verification</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>OTP Status</th>
                        <th>OTP Expires</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($approved_appointments) === 0): ?>
                        <tr><td colspan="8" style="color:#94a3b8; padding:18px; text-align: center;">No approved appointments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($approved_appointments as $apt): ?>
                            <tr>
                                <td><strong><?php echo (int)$apt['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($apt['specialty']); ?></td>
                                <td><?php echo htmlspecialchars($apt['appointment_date']); ?></td>
                                <td><?php echo htmlspecialchars(substr($apt['appointment_time'], 0, 5)); ?></td>
                                <td>
                                    <?php if ($apt['verification_status']): ?>
                                        <span class="status <?php echo htmlspecialchars($apt['verification_status']); ?>">
                                            <?php echo htmlspecialchars($apt['verification_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status Pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($apt['expires_at']): ?>
                                        <?php 
                                            $isExpired = strtotime($apt['expires_at']) < time();
                                            $expireTime = date('M d, Y H:i', strtotime($apt['expires_at']));
                                            echo $expireTime;
                                            if ($isExpired) {
                                                echo ' <span style="color: #ef4444;">(Expired)</span>';
                                            }
                                        ?>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
