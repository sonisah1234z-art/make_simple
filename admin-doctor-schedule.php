<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$admin_name = $_SESSION['admin_name'];
$message = '';
$error = '';

$conn->query("CREATE TABLE IF NOT EXISTS doctor_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    shift_date DATE NOT NULL,
    shift_type ENUM('Morning','Night','Leave') NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY doctor_shift_unique (doctor_id, shift_date, shift_type),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_shift'])) {
        $shift_id = (int)$_POST['shift_id'];
        $stmt = $conn->prepare('DELETE FROM doctor_shifts WHERE id = ?');
        $stmt->bind_param('i', $shift_id);
        if ($stmt->execute()) {
            $message = 'Shift entry removed successfully.';
        } else {
            $error = 'Unable to remove the selected shift entry.';
        }
        $stmt->close();
    } else {
        $doctor_id = (int)$_POST['doctor_id'];
        $shift_date = $conn->real_escape_string($_POST['shift_date']);
        $shift_type = $conn->real_escape_string($_POST['shift_type']);
        $notes = $conn->real_escape_string($_POST['notes']);

        if (!$doctor_id || !$shift_date || !$shift_type) {
            $error = 'Please choose a doctor, date, and shift type.';
        } else {
            $stmt = $conn->prepare('INSERT INTO doctor_shifts (doctor_id, shift_date, shift_type, notes) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $doctor_id, $shift_date, $shift_type, $notes);
            if ($stmt->execute()) {
                $message = 'Doctor shift scheduled successfully.';
            } else {
                if ($conn->errno === 1062) {
                    $error = 'This doctor already has the selected shift on that date.';
                } else {
                    $error = 'Unable to schedule shift. Please try again.';
                }
            }
            $stmt->close();
        }
    }
}

$doctors = [];
$result = $conn->query('SELECT id, name, specialty FROM doctors ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

$shifts = [];
$result = $conn->query("SELECT ds.id, ds.doctor_id, ds.shift_date, ds.shift_type, ds.notes, d.name AS doctor_name, d.specialty
                       FROM doctor_shifts ds
                       JOIN doctors d ON ds.doctor_id = d.id
                       ORDER BY ds.shift_date DESC, ds.shift_type ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $shifts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Doctor Shift Schedule</title>
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
    .layout { display: grid; grid-template-columns: 1.4fr 0.6fr; gap: 24px; }
    .table-card, .form-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22); }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 700; }
    td { color: #e2e8f0; }
    label { display: block; margin-bottom: 8px; color: #cbd5e1; font-size: 14px; }
    select, input, textarea { width: 100%; padding: 14px 16px; border-radius: 14px; border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(255,255,255,0.06); color: #f8fafc; margin-bottom: 16px; }
    select, input { height: 50px; }
    textarea { min-height: 100px; resize: vertical; }
    select:focus, input:focus, textarea:focus { outline: none; border-color: #60a5fa; background: rgba(255,255,255,0.1); }
    .button-submit { width: 100%; margin-top: 10px; }
    .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
    .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
    .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
    .button-delete { background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 8px 12px; font-size: 12px; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; cursor: pointer; }
    .button-delete:hover { background: rgba(239, 68, 68, 0.3); }
    .label-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; }
    .pill-morning { background: rgba(34, 197, 94, 0.16); color: #bbf7d0; }
    .pill-night { background: rgba(59, 130, 246, 0.16); color: #93c5fd; }
    .pill-leave { background: rgba(248, 113, 113, 0.16); color: #fecaca; }
    @media (max-width: 960px) { .layout { grid-template-columns: 1fr; } .table-card { overflow-x: auto; } }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 12px; margin-bottom: 20px; flex-direction: column; align-items: flex-start; }
        h1 { font-size: 26px; }
        .layout { gap: 16px; }
        .table-card, .form-card { padding: 16px; border-radius: 20px; }
        table { font-size: 12px; }
        th, td { padding: 10px 8px; }
        select, input, textarea { padding: 12px 14px; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; }
        h1 { font-size: 20px; }
        .button { padding: 10px 14px; font-size: 12px; }
        table { font-size: 11px; }
        th, td { padding: 6px; }
        .table-card, .form-card { padding: 12px; }
        select, input, textarea { padding: 10px 12px; margin-bottom: 12px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Doctor Shift Schedule</h1>
            <p class="subtitle">Manage morning and night shifts, and track doctor leaves from a single schedule panel.</p>
        </div>
        <div>
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a class="button" href="admin-doctors.php">Manage Doctors</a>
            <a class="button" href="logout.php">Logout</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php elseif ($message): ?>
        <div class="alert success"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="layout">
        <div class="table-card">
            <h2>Scheduled Shifts</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Shift</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($shifts) === 0): ?>
                        <tr><td colspan="6" style="color:#94a3b8; padding:18px;">No shifts have been scheduled yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['shift_date']); ?></td>
                                <td><?php echo htmlspecialchars($shift['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($shift['specialty']); ?></td>
                                <td>
                                    <?php if ($shift['shift_type'] === 'Morning'): ?>
                                        <span class="label-pill pill-morning">Morning</span>
                                    <?php elseif ($shift['shift_type'] === 'Night'): ?>
                                        <span class="label-pill pill-night">Night</span>
                                    <?php else: ?>
                                        <span class="label-pill pill-leave">Leave</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($shift['notes']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this shift entry?');">
                                        <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                                        <button type="submit" name="delete_shift" class="button-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="form-card">
            <h2>Schedule a Shift</h2>
            <form method="POST" action="admin-doctor-schedule.php">
                <label for="doctor_id">Choose Doctor</label>
                <select id="doctor_id" name="doctor_id" required>
                    <option value="">Select doctor</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo (int)$doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']) . ' — ' . htmlspecialchars($doctor['specialty']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="shift_date">Date</label>
                <input id="shift_date" type="date" name="shift_date" value="<?php echo date('Y-m-d'); ?>" required>

                <label for="shift_type">Shift Type</label>
                <select id="shift_type" name="shift_type" required>
                    <option value="">Select shift</option>
                    <option value="Morning">Morning</option>
                    <option value="Night">Night</option>
                    <option value="Leave">Leave</option>
                </select>

                <label for="notes">Notes (optional)</label>
                <textarea id="notes" name="notes" placeholder="Example: Annual leave, emergency coverage, etc."></textarea>

                <button class="button button-submit" type="submit">Save Shift</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>