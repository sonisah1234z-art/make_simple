<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';
require_once 'otp-management.php';

$admin_name = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'] ?? null;
$message = '';
$error = '';

// Handle OTP resend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'resend_otp') {
        $appointment_id = (int)$_POST['appointment_id'];
        $patient_id = (int)$_POST['patient_id'];
        
        // Delete old OTP
        $deleteStmt = $conn->prepare('DELETE FROM appointment_otps WHERE appointment_id = ?');
        $deleteStmt->bind_param('i', $appointment_id);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Generate new OTP
        $otpResult = createAppointmentOTP($appointment_id, $patient_id, $conn, $admin_id);
        
        if ($otpResult['success']) {
            $message = 'OTP has been resent successfully. Email sent: ' . ($otpResult['email_sent'] ? 'Yes' : 'Check configuration');
        } else {
            $error = 'Failed to resend OTP: ' . $otpResult['message'];
        }
    }
}

// Get OTP statistics
$stats = [];
$statsResult = $conn->query("
    SELECT 
        COUNT(DISTINCT CASE WHEN verification_status = 'Pending' THEN id END) as pending_otps,
        COUNT(DISTINCT CASE WHEN verification_status = 'Verified' THEN id END) as verified_otps,
        COUNT(DISTINCT CASE WHEN verification_status = 'Expired' THEN id END) as expired_otps,
        COUNT(*) as total_otps
    FROM appointment_otps
");

if ($statsResult) {
    $stats = $statsResult->fetch_assoc();
}

// Get OTP records with detailed information
$otps = [];
$result = $conn->query("
    SELECT ao.id, ao.appointment_id, ao.otp_code, ao.generated_at, ao.expires_at,
           ao.verification_status, ao.verified_at, ao.verification_attempts,
           p.name as patient_name, p.email, p.id as patient_id,
           d.name as doctor_name,
           a.appointment_date, a.appointment_time
    FROM appointment_otps ao
    JOIN appointments a ON ao.appointment_id = a.id
    JOIN patients p ON ao.patient_id = p.id
    JOIN doctors d ON a.doctor_id = d.id
    ORDER BY ao.generated_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $otps[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OTP Management & Analytics</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
            color: #e2e8f0;
            padding: 24px;
        }
        .page { max-width: 1400px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px; margin-bottom: 28px; }
        h1 { font-size: 34px; }
        .subtitle { color: #94a3b8; margin-top: 6px; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: 16px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 700; cursor: pointer; transition: transform .18s ease; text-decoration: none; }
        .button:hover { transform: translateY(-1px); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .stat-card {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        .stat-value { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .stat-value.pending { color: #f59e0b; }
        .stat-value.verified { color: #22c55e; }
        .stat-value.expired { color: #ef4444; }
        .stat-value.total { color: #60a5fa; }
        .stat-label { color: #94a3b8; font-size: 13px; }
        .card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22); margin-bottom: 24px; }
        .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
        .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
        .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
        th { color: #cbd5e1; font-weight: 700; font-size: 13px; }
        td { color: #e2e8f0; font-size: 13px; }
        .status { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .status.Pending { background: rgba(245,158,11,0.16); color: #f59e0b; }
        .status.Verified { background: rgba(34,197,94,0.16); color: #22c55e; }
        .status.Expired { background: rgba(239,68,68,0.16); color: #ef4444; }
        .otp-code { font-family: monospace; background: rgba(0,0,0,0.2); padding: 4px 8px; border-radius: 4px; }
        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            background: #8b5cf6;
            color: white;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .action-btn:hover { background: #7c3aed; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content {
            background: rgba(15, 23, 42, 0.98);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 20px;
            padding: 28px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header { font-size: 20px; font-weight: 700; margin-bottom: 16px; }
        .modal-buttons { display: flex; gap: 12px; margin-top: 20px; }
        .modal-buttons button { flex: 1; padding: 10px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .confirm-btn { background: #22c55e; color: white; }
        .confirm-btn:hover { background: #16a34a; }
        .cancel-btn { background: #6b7280; color: white; }
        .cancel-btn:hover { background: #4b5563; }
        @media (max-width: 1024px) { .topbar { flex-direction: column; align-items: flex-start; } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr 1fr; } table { font-size: 11px; } th, td { padding: 8px 10px; } }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>OTP Management & Analytics</h1>
            <p class="subtitle">Welcome, <?php echo htmlspecialchars($admin_name); ?>. Manage and monitor appointment OTPs.</p>
        </div>
        <div>
            <a class="button" href="admin-dashboard.php">Back to Dashboard</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value total"><?php echo $stats['total_otps'] ?? 0; ?></div>
            <div class="stat-label">Total OTPs Generated</div>
        </div>
        <div class="stat-card">
            <div class="stat-value pending"><?php echo $stats['pending_otps'] ?? 0; ?></div>
            <div class="stat-label">Pending Verification</div>
        </div>
        <div class="stat-card">
            <div class="stat-value verified"><?php echo $stats['verified_otps'] ?? 0; ?></div>
            <div class="stat-label">Verified</div>
        </div>
        <div class="stat-card">
            <div class="stat-value expired"><?php echo $stats['expired_otps'] ?? 0; ?></div>
            <div class="stat-label">Expired</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($message): ?>
        <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- OTP Records Table -->
    <div class="card">
        <h2 style="margin-bottom: 20px; color: #f8fafc;">OTP Records</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Date & Time</th>
                        <th>OTP Code</th>
                        <th>Status</th>
                        <th>Generated</th>
                        <th>Expires</th>
                        <th>Verified</th>
                        <th>Attempts</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($otps) === 0): ?>
                        <tr><td colspan="11" style="color:#94a3b8; padding:18px; text-align: center;">No OTP records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($otps as $otp): ?>
                            <tr>
                                <td><strong><?php echo (int)$otp['appointment_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($otp['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($otp['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($otp['appointment_date'] . ' ' . substr($otp['appointment_time'], 0, 5)); ?></td>
                                <td><span class="otp-code"><?php echo htmlspecialchars($otp['otp_code']); ?></span></td>
                                <td><span class="status <?php echo htmlspecialchars($otp['verification_status']); ?>"><?php echo htmlspecialchars($otp['verification_status']); ?></span></td>
                                <td><?php echo htmlspecialchars(date('M d, H:i', strtotime($otp['generated_at']))); ?></td>
                                <td><?php echo htmlspecialchars(date('M d, H:i', strtotime($otp['expires_at']))); ?></td>
                                <td><?php echo $otp['verified_at'] ? htmlspecialchars(date('M d, H:i', strtotime($otp['verified_at']))) : '—'; ?></td>
                                <td><?php echo $otp['verification_attempts']; ?></td>
                                <td>
                                    <?php if ($otp['verification_status'] !== 'Verified'): ?>
                                        <button class="action-btn" onclick="openResendModal(<?php echo (int)$otp['appointment_id']; ?>, '<?php echo htmlspecialchars($otp['patient_name']); ?>', <?php echo (int)$otp['patient_id']; ?>)">Resend</button>
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

<!-- Resend Modal -->
<div id="resendModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Resend OTP Confirmation</div>
        <p style="color: #cbd5e1; margin-bottom: 16px;">
            Are you sure you want to resend OTP for appointment <strong id="modalAppointmentId"></strong> to <strong id="modalPatientName"></strong>?
        </p>
        <p style="color: #94a3b8; font-size: 13px;">
            The previous OTP will be invalidated and a new one will be generated and sent to the patient's email.
        </p>
        <form id="resendForm" method="POST" action="otp-reports.php">
            <input type="hidden" name="action" value="resend_otp">
            <input type="hidden" name="appointment_id" id="modalAppointmentId_input">
            <input type="hidden" name="patient_id" id="modalPatientId_input">
            <div class="modal-buttons">
                <button type="submit" class="confirm-btn">Yes, Resend OTP</button>
                <button type="button" class="cancel-btn" onclick="closeResendModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResendModal(appointmentId, patientName, patientId) {
    document.getElementById('modalAppointmentId').textContent = appointmentId;
    document.getElementById('modalPatientName').textContent = patientName;
    document.getElementById('modalAppointmentId_input').value = appointmentId;
    document.getElementById('modalPatientId_input').value = patientId;
    document.getElementById('resendModal').classList.add('active');
}

function closeResendModal() {
    document.getElementById('resendModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('resendModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeResendModal();
    }
});
</script>
</body>
</html>
