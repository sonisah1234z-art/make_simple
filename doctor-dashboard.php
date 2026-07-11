<?php
require_once 'auth.php';
require_doctor();
require_once 'db.php';
require_once 'announcements.php';

$doctor_id = (int)$_SESSION['doctor_id'];
$doctor_name = $_SESSION['doctor_name'];
$statusMessage = '';
$statusError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'] ?? '';
    $validStatuses = ['Available', 'Busy', 'Offline'];
    if (in_array($newStatus, $validStatuses, true)) {
        $stmt = $conn->prepare('UPDATE doctors SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $doctor_id);
        if ($stmt->execute()) {
            $statusMessage = 'Status updated to ' . htmlspecialchars($newStatus) . '.';
        } else {
            $statusError = 'Unable to update status. Please try again.';
        }
        $stmt->close();
    } else {
        $statusError = 'Invalid status selected.';
    }
}

$doctor_status = 'Offline';
$statusQuery = $conn->prepare('SELECT status FROM doctors WHERE id = ? LIMIT 1');
$statusQuery->bind_param('i', $doctor_id);
$statusQuery->execute();
$statusResult = $statusQuery->get_result();
if ($statusRow = $statusResult->fetch_assoc()) {
    $doctor_status = $statusRow['status'] ?: 'Offline';
}
$statusQuery->close();

$appointments = [];
$stmt = $conn->prepare("SELECT a.id, p.name AS patient_name, a.appointment_date, a.appointment_time, a.status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ?
    ORDER BY a.appointment_date ASC, a.appointment_time ASC");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Doctor Dashboard</title>
<style>
body{margin:0;font-family:Arial,sans-serif;background:#f4f6f9;color:#1f2937}
.topbar{background:#1f2937;color:white;padding:20px 32px;display:flex;justify-content:space-between;align-items:center}
.topbar a{color:white;text-decoration:none;background:#2563eb;padding:10px 14px;border-radius:8px}
.container{max-width:1000px;margin:32px auto;padding:0 20px}
.card{background:white;border-radius:8px;padding:22px;box-shadow:0 8px 24px rgba(15,23,42,.08);margin-bottom:20px}
table{width:100%;border-collapse:collapse;margin-top:14px}
th,td{text-align:left;padding:12px;border-bottom:1px solid #e5e7eb}
th{background:#f8fafc;color:#475569}
.empty{color:#64748b}
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>
<body>
<div class="topbar">
    <h2>Doctor Dashboard</h2>
    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <a href="about.php" style="background:#0f766e;">About Us</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
<div class="container">
    <div class="card">
        <h1>Welcome, <?php echo htmlspecialchars($doctor_name); ?></h1>
        <p>Review your assigned appointments and patient schedule.</p>
        <?php if ($statusMessage): ?>
            <div style="background:#dcfce7;color:#166534;padding:12px;border-radius:8px;margin-top:16px;">
                <?php echo htmlspecialchars($statusMessage); ?>
            </div>
        <?php elseif ($statusError): ?>
            <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-top:16px;">
                <?php echo htmlspecialchars($statusError); ?>
            </div>
        <?php endif; ?>
        <form method="POST" style="margin-top:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
            <label for="status" style="font-weight:600; color:#34495e;">Current Status:</label>
            <select id="status" name="status" style="padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px; min-width:170px;">
                <option value="Available" <?php echo $doctor_status === 'Available' ? 'selected' : ''; ?>>Available</option>
                <option value="Busy" <?php echo $doctor_status === 'Busy' ? 'selected' : ''; ?>>Busy</option>
                <option value="Offline" <?php echo $doctor_status === 'Offline' ? 'selected' : ''; ?>>Offline</option>
            </select>
            <button type="submit" name="update_status" style="padding:12px 18px;border:none;background:#2563eb;color:white;border-radius:8px;cursor:pointer;">Update Status</button>
        </form>
    </div>

    <div class="card">
        <h2>Appointments</h2>
        <?php if (count($appointments) === 0): ?>
            <p class="empty">No appointments assigned yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo (int)$appointment['id']; ?></td>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></td>
                            <td><?php echo htmlspecialchars($appointment['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php $context = get_current_user_context(); echo render_announcements_panel($conn, $context['role'], $context['id'], 3); ?>
</div>
</body>
</html>
