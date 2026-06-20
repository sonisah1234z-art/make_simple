<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];
$message = '';
$error = '';

// Fetch patient's appointment with OTP status
$appointments = [];
$result = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.status,
           d.name as doctor_name, d.specialty,
           ao.otp_code, ao.verification_status, ao.expires_at
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    LEFT JOIN appointment_otps ao ON a.id = ao.appointment_id
    WHERE a.patient_id = $patient_id
    ORDER BY a.appointment_date DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Appointments & OTP Status</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
            color: #e2e8f0;
            padding: 24px;
        }
        .page { max-width: 1000px; margin: 0 auto; }
        .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 18px; margin-bottom: 28px; }
        h1 { font-size: 34px; }
        .subtitle { color: #94a3b8; margin-top: 6px; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 12px 20px; border-radius: 16px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 700; cursor: pointer; transition: transform .18s ease; text-decoration: none; }
        .button:hover { transform: translateY(-1px); }
        .appointment-card {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
        }
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .appointment-title {
            font-size: 20px;
            font-weight: 700;
            color: #f8fafc;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }
        .status-badge.Pending { background: rgba(245,158,11,0.16); color: #f59e0b; }
        .status-badge.Approved { background: rgba(34,197,94,0.16); color: #22c55e; }
        .status-badge.Cancelled { background: rgba(239,68,68,0.16); color: #ef4444; }
        .status-badge.Verified { background: rgba(34,197,94,0.16); color: #22c55e; }
        .status-badge.Expired { background: rgba(239,68,68,0.16); color: #ef4444; }
        .appointment-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .detail-item {
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border-left: 3px solid #60a5fa;
        }
        .detail-label {
            color: #94a3b8;
            font-size: 12px;
            margin-bottom: 4px;
        }
        .detail-value {
            color: #f8fafc;
            font-weight: 600;
            font-size: 16px;
        }
        .otp-section {
            background: rgba(34,197,94,0.10);
            border: 1px solid rgba(34,197,94,0.20);
            border-radius: 16px;
            padding: 16px;
            margin-top: 16px;
        }
        .otp-section.expired {
            background: rgba(239,68,68,0.10);
            border-color: rgba(239,68,68,0.20);
        }
        .otp-code {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            font-weight: bold;
            color: #22c55e;
            letter-spacing: 3px;
            text-align: center;
            padding: 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 8px;
            margin: 12px 0;
        }
        .otp-code.expired {
            color: #ef4444;
        }
        .otp-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #cbd5e1;
            margin-top: 8px;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state h3 {
            margin-bottom: 10px;
            color: #cbd5e1;
        }
        @media (max-width: 768px) {
            .appointment-details { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .appointment-header { flex-direction: column; }
            .otp-code { font-size: 20px; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>My Appointments & OTP Status</h1>
            <p class="subtitle">View your appointment details and OTP verification status</p>
        </div>
        <div>
            <a class="button" href="dashboard.php">Back to Dashboard</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <?php if (count($appointments) === 0): ?>
        <div class="appointment-card empty-state">
            <h3>No Appointments Found</h3>
            <p>You haven't booked any appointments yet.</p>
            <a class="button" href="book-appointment.php" style="margin-top: 16px;">Book an Appointment</a>
        </div>
    <?php else: ?>
        <?php foreach ($appointments as $appointment): ?>
            <div class="appointment-card">
                <div class="appointment-header">
                    <div class="appointment-title">
                        <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                        <span style="color: #94a3b8; font-size: 14px; font-weight: 400;">
                            - <?php echo htmlspecialchars($appointment['specialty']); ?>
                        </span>
                    </div>
                    <span class="status-badge <?php echo htmlspecialchars($appointment['status']); ?>">
                        <?php echo htmlspecialchars($appointment['status']); ?>
                    </span>
                </div>

                <div class="appointment-details">
                    <div class="detail-item">
                        <div class="detail-label">Appointment Date</div>
                        <div class="detail-value"><?php echo htmlspecialchars($appointment['appointment_date']); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Appointment Time</div>
                        <div class="detail-value"><?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></div>
                    </div>
                </div>

                <!-- OTP Section -->
                <?php if ($appointment['status'] === 'Approved'): ?>
                    <?php 
                        $isExpired = $appointment['expires_at'] && strtotime($appointment['expires_at']) < time();
                        $isVerified = $appointment['verification_status'] === 'Verified';
                    ?>
                    <div class="otp-section <?php echo $isExpired ? 'expired' : ''; ?>">
                        <div style="color: #cbd5e1; font-size: 12px; margin-bottom: 8px;">
                            <?php if ($isVerified): ?>
                                ✓ <strong>Verified</strong>
                            <?php elseif ($isExpired): ?>
                                ✗ <strong>Expired</strong>
                            <?php else: ?>
                                <strong>Your Verification OTP</strong>
                            <?php endif; ?>
                        </div>

                        <?php if ($appointment['otp_code']): ?>
                            <div class="otp-code <?php echo $isExpired ? 'expired' : ''; ?>">
                                <?php echo htmlspecialchars($appointment['otp_code']); ?>
                            </div>
                            <div class="otp-info">
                                <span>Generated: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($appointment['appointment_date']))); ?></span>
                                <span>Expires: 
                                    <?php 
                                        if ($appointment['expires_at']) {
                                            $expiryTime = strtotime($appointment['expires_at']);
                                            if ($isExpired) {
                                                echo '<span style="color: #ef4444;">' . htmlspecialchars(date('M d, Y H:i', $expiryTime)) . '</span>';
                                            } else {
                                                echo htmlspecialchars(date('M d, Y H:i', $expiryTime));
                                            }
                                        }
                                    ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <p style="color: #94a3b8; margin: 12px 0; text-align: center;">
                                OTP is being generated. Please check your email shortly.
                            </p>
                        <?php endif; ?>

                        <?php if ($isVerified): ?>
                            <p style="color: #22c55e; margin: 12px 0; text-align: center; font-weight: 600;">
                                ✓ Your appointment has been verified. You're all set!
                            </p>
                        <?php elseif (!$isExpired && $appointment['otp_code']): ?>
                            <p style="color: #cbd5e1; margin: 12px 0; text-align: center; font-size: 12px;">
                                Present this OTP to the reception desk when you arrive for your appointment.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php elseif ($appointment['status'] === 'Pending'): ?>
                    <div style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.20); border-radius: 16px; padding: 16px; margin-top: 16px; color: #f59e0b; font-size: 14px;">
                        ⏳ Waiting for hospital approval. Your OTP will be sent once your appointment is approved.
                    </div>
                <?php elseif ($appointment['status'] === 'Cancelled'): ?>
                    <div style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.20); border-radius: 16px; padding: 16px; margin-top: 16px; color: #ef4444; font-size: 14px;">
                        ✗ This appointment has been cancelled.
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
