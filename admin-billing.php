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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_billing') {
        $appointment_id = (int)$_POST['appointment_id'];
        $amount = (float)$_POST['amount'];
        $description = $conn->real_escape_string($_POST['description']);

        $stmt = $conn->prepare('SELECT patient_id FROM appointments WHERE id = ?');
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $patient_id = $row['patient_id'];
            
            $stmt = $conn->prepare('INSERT INTO billings (appointment_id, patient_id, amount, description, status) VALUES (?, ?, ?, ?, ?)');
            $status = 'Pending';
            $stmt->bind_param('iidss', $appointment_id, $patient_id, $amount, $description, $status);
            if ($stmt->execute()) {
                $message = 'Billing record created successfully.';
            } else {
                $error = 'Unable to create billing record.';
            }
        } else {
            $error = 'Appointment not found.';
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'update_status') {
        $billing_id = (int)$_POST['billing_id'];
        $status = $conn->real_escape_string($_POST['status']);
        if (in_array($status, ['Pending', 'Paid', 'Cancelled'])) {
            $stmt = $conn->prepare('UPDATE billings SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $billing_id);
            if ($stmt->execute()) {
                $message = 'Billing status updated successfully.';
            } else {
                $error = 'Unable to update billing status.';
            }
            $stmt->close();
        }
    }
}

$billings = [];
$result = $conn->query("SELECT b.id, p.name AS patient_name, a.appointment_date, b.amount, b.description, b.status FROM billings b JOIN patients p ON b.patient_id = p.id JOIN appointments a ON b.appointment_id = a.id ORDER BY b.created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $billings[] = $row;
    }
}

$appointments = [];
$result = $conn->query("SELECT a.id, p.name AS patient_name, d.name AS doctor_name, a.appointment_date, a.appointment_time FROM appointments a JOIN patients p ON a.patient_id = p.id JOIN doctors d ON a.doctor_id = d.id WHERE a.status = 'Approved' ORDER BY a.appointment_date DESC, a.appointment_time DESC");
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Billing Management</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #111827 35%, #0f172a 100%);
        color: #e2e8f0;
        padding: 16px;
    }
    .page { max-width: 1300px; margin: 0 auto; }
    .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    h1 { font-size: 28px; }
    @media (max-width: 640px) { h1 { font-size: 22px; } }
    .subtitle { color: #94a3b8; margin-top: 4px; font-size: 14px; }
    .button { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 14px; border: none; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 600; cursor: pointer; transition: transform .18s ease; text-decoration: none; font-size: 14px; }
    .button:hover { transform: translateY(-1px); }
    .alert { border-radius: 14px; padding: 14px; margin-bottom: 18px; font-size: 14px; }
    .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
    .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
    .grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; margin-bottom: 24px; }
    @media (max-width: 960px) { .grid { grid-template-columns: 1fr; } }
    .card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 20px; padding: 20px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22); }
    .card h2 { font-size: 18px; margin-bottom: 16px; }
    .form-group { margin-bottom: 14px; }
    label { display: block; margin-bottom: 6px; color: #cbd5e1; font-size: 13px; }
    input { width: 100%; padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(255,255,255,0.06); color: #f8fafc; font-size: 14px; }
    select { width: 100%; padding: 10px 12px; border-radius: 12px; border: 1px solid rgba(148, 163, 184, 0.18); background: #ffffff; color: #0f172a; font-size: 14px; }
    option { color: #0f172a; background: #ffffff; }
    input:focus, select:focus { outline: none; border-color: #60a5fa; background: rgba(255,255,255,0.1); }
    .button-submit { width: 100%; margin-top: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
    @media (max-width: 640px) { table { font-size: 12px; } }
    th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 600; }
    td { color: #e2e8f0; }
    .status { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .status.Pending { background: rgba(245,158,11,0.16); color: #f59e0b; }
    .status.Paid { background: rgba(34,197,94,0.16); color: #22c55e; }
    .status.Cancelled { background: rgba(239,68,68,0.16); color: #ef4444; }
    .form-inline { display: grid; grid-template-columns: auto auto; gap: 8px; align-items: center; }
    @media (max-width: 640px) { .form-inline { grid-template-columns: 1fr; } .form-inline button { width: 100%; } }
    .form-inline button { border: none; border-radius: 10px; padding: 8px 12px; background: #8b5cf6; color: white; cursor: pointer; font-size: 12px; }
    button:disabled { opacity: 0.55; cursor: not-allowed; }
    .empty { color: #94a3b8; padding: 16px 0; text-align: center; }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 10px; margin-bottom: 18px; }
        h1 { font-size: 24px; }
        .grid { gap: 16px; }
        .card { padding: 16px; border-radius: 16px; }
        .card h2 { font-size: 16px; margin-bottom: 12px; }
        table { font-size: 11px; }
        th, td { padding: 8px 10px; }
    }
    @media (max-width: 480px) {
        .topbar { flex-direction: column; align-items: flex-start; gap: 8px; }
        .page { padding: 16px 12px; }
        h1 { font-size: 20px; }
        .subtitle { font-size: 12px; }
        .button { padding: 8px 12px; font-size: 11px; }
        .card { padding: 12px; }
        table { font-size: 10px; }
        th, td { padding: 6px 8px; }
        label { font-size: 12px; }
        input, select { padding: 8px 10px; font-size: 12px; }
        .form-inline { gap: 6px; }
        .form-inline button { padding: 6px 10px; font-size: 10px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Billing Management</h1>
            <p class="subtitle">Create and manage patient billing records</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a class="button" href="index.php">Home</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php elseif ($message): ?>
        <div class="alert success"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Billing Records</h2>
            <?php if (count($billings) === 0): ?>
                <div class="empty">No billing records found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billings as $billing): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($billing['patient_name']); ?></td>
                                <td><?php echo number_format($billing['amount'], 2); ?></td>
                                <td><span class="status <?php echo htmlspecialchars($billing['status']); ?>"><?php echo htmlspecialchars($billing['status']); ?></span></td>
                                <td>
                                    <form method="POST" action="admin-billing.php" class="form-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="billing_id" value="<?php echo (int)$billing['id']; ?>">
                                        <select name="status" style="width: 100px;">
                                            <option value="Pending" <?php echo $billing['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Paid" <?php echo $billing['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="Cancelled" <?php echo $billing['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Create Billing</h2>
            <form method="POST" action="admin-billing.php">
                <input type="hidden" name="action" value="add_billing">
                <div class="form-group">
                    <label for="appointment_id">Appointment</label>
                    <select id="appointment_id" name="appointment_id" required>
                        <option value="">Select appointment</option>
                        <?php foreach ($appointments as $apt): ?>
                            <option value="<?php echo (int)$apt['id']; ?>"><?php echo htmlspecialchars($apt['patient_name']) . ' - ' . htmlspecialchars($apt['doctor_name']) . ' (' . htmlspecialchars($apt['appointment_date']) . ' ' . htmlspecialchars(substr($apt['appointment_time'], 0, 5)) . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($appointments) === 0): ?>
                        <p style="color:#f8fafc; font-size:13px; margin-top:8px; opacity:0.8;">No approved appointments found. Approve an appointment first to create billing.</p>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input id="amount" type="number" name="amount" step="0.01" placeholder="Enter amount" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input id="description" type="text" name="description" placeholder="Consultation fee, etc.">
                </div>
                <button class="button button-submit" type="submit" <?php echo count($appointments) === 0 ? 'disabled' : ''; ?>>Create Billing</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>