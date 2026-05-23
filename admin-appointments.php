<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$admin_name = $_SESSION['admin_name'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int)$_POST['appointment_id'];
    $status = $conn->real_escape_string($_POST['status']);
    if ($appointment_id && in_array($status, ['Pending', 'Approved', 'Cancelled'])) {
        $stmt = $conn->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $appointment_id);
        if ($stmt->execute()) {
            $message = 'Appointment status updated successfully.';
        } else {
            $error = 'Unable to update appointment status.';
        }
        $stmt->close();
    }
}

$appointments = [];
$result = $conn->query("SELECT a.id, p.name AS patient_name, d.name AS doctor_name, d.specialty, a.appointment_date, a.appointment_time, a.status FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC, a.appointment_time DESC");
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
<title>Admin Appointments</title>
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
    .table-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22); }
    .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
    .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
    .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 700; }
    td { color: #e2e8f0; }
    select { width: auto; padding: 10px 12px; border-radius: 14px; border: 1px solid rgba(148,163,184,0.18); background: rgba(255,255,255,0.08); color: #f8fafc; }
    .status { display: inline-flex; align-items: center; padding: 8px 10px; border-radius: 999px; font-size: 13px; font-weight: 700; }
    .status.Pending { background: rgba(245,158,11,0.16); color: #f59e0b; }
    .status.Approved { background: rgba(34,197,94,0.16); color: #22c55e; }
    .status.Cancelled { background: rgba(239,68,68,0.16); color: #ef4444; }
    .form-inline { display: grid; grid-template-columns: auto auto; gap: 10px; align-items: center; }
    .form-inline button { border: none; border-radius: 14px; padding: 10px 14px; background: #8b5cf6; color: white; cursor: pointer; }
    @media (max-width: 900px) { .topbar { flex-direction: column; align-items: flex-start; } .table-card { overflow-x: auto; } }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 12px; margin-bottom: 20px; }
        h1 { font-size: 26px; }
        .table-card { padding: 16px; border-radius: 20px; }
        table { font-size: 12px; }
        th, td { padding: 10px 8px; }
        select { width: 100%; }
        .form-inline { grid-template-columns: 1fr 1fr; gap: 8px; }
        .form-inline button { width: 100%; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; }
        .topbar { flex-direction: column; align-items: flex-start; gap: 8px; }
        h1 { font-size: 20px; }
        .button { padding: 10px 14px; font-size: 12px; }
        table { font-size: 11px; }
        th, td { padding: 6px; }
        .table-card { padding: 12px; }
        .form-inline { grid-template-columns: 1fr; gap: 6px; }
        .form-inline button { width: 100%; padding: 8px 10px; font-size: 11px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Appointments</h1>
            <p class="subtitle">Welcome, <?php echo htmlspecialchars($admin_name); ?>. Review and manage appointment requests.</p>
        </div>
        <div>
            <a class="button" href="admin-dashboard.php">Back to Dashboard</a>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 12px; font-weight: 600;">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php elseif ($message): ?>
        <div class="alert success"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Specialty</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($appointments) === 0): ?>
                    <tr><td colspan="7" style="color:#94a3b8; padding:18px;">No appointments found.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['specialty']); ?></td>
                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                            <td><?php echo htmlspecialchars(substr($appointment['appointment_time'], 0, 5)); ?></td>
                            <td><span class="status <?php echo htmlspecialchars($appointment['status']); ?>"><?php echo htmlspecialchars($appointment['status']); ?></span></td>
                            <td>
                                <form method="POST" action="admin-appointments.php" class="form-inline">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                    <select name="status">
                                        <option value="Pending" <?php echo $appointment['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Approved" <?php echo $appointment['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Cancelled" <?php echo $appointment['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>