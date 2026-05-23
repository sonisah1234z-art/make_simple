<?php
session_start();
include 'db.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: login.php');
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];
$error = '';
$message = '';

$doctors = [];
$result = $conn->query('SELECT id, name, specialty FROM doctors ORDER BY name ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)$_POST['doctor_id'];
    $date = $conn->real_escape_string($_POST['appointment_date']);
    $time = $conn->real_escape_string($_POST['appointment_time']);

    if (!$doctor_id || !$date || !$time) {
        $error = 'Please select a doctor and choose a date/time.';
    } else {
        $stmt = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?)');
        $status = 'Pending';
        $stmt->bind_param('iisss', $patient_id, $doctor_id, $date, $time, $status);
        if ($stmt->execute()) {
            $message = 'Appointment request sent successfully. Admin will confirm it shortly.';
        } else {
            $error = 'Unable to book appointment at this time.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Appointment</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #111827 38%, #0f172a 100%);
        color: #e2e8f0;
        padding: 24px;
    }
    .page {
        max-width: 960px;
        margin: 0 auto;
    }
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 18px;
        margin-bottom: 30px;
    }
    h1 {
        font-size: 34px;
        font-weight: 700;
        color: #ffffff;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.35);
        letter-spacing: 0.02em;
        text-rendering: optimizeLegibility;
    }
    .subtitle { color: #cbd5e1; margin-top: 6px; }
    .card {
        background: rgba(15, 23, 42, 0.96);
        border: 1px solid rgba(148, 163, 184, 0.16);
        border-radius: 28px;
        padding: 32px;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.22);
    }
    .grid { display: grid; grid-template-columns: 1fr 360px; gap: 26px; }
    label { display: block; margin-bottom: 8px; color: #cbd5e1; font-size: 14px; }
    select, input { width: 100%; padding: 14px 16px; border-radius: 16px; border: 1px solid rgba(148, 163, 184, 0.35); background: #ffffff; color: #000000; font-size: 15px; margin-bottom: 18px; transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease; }
    select option { color: #000000; background: #ffffff; }
    select option[value=""] { color: #6b7280; }
    input:focus, select:focus {
        outline: none;
        border-color: #7c3aed;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.18);
    }
    select option:checked { background: #93c5fd; color: #000000; }
    .button { border: none; border-radius: 16px; padding: 14px 18px; background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; font-weight: 700; cursor: pointer; transition: transform .18s ease; }
    .button:hover { transform: translateY(-2px); }
    .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
    .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
    .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
    .doctor-card { background: rgba(255,255,255,0.05); border-radius: 22px; padding: 20px; }
    .doctor-card h3 { margin-bottom: 10px; font-size: 20px; }
    .doctor-card p { color: #cbd5e1; line-height: 1.7; }
    .link { color: #93c5fd; text-decoration: none; }
    .link:hover { text-decoration: underline; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 12px; margin-bottom: 20px; flex-direction: column; align-items: flex-start; }
        h1 { font-size: 26px; }
        .card { padding: 20px 16px; border-radius: 20px; }
        select, input { padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .button { padding: 12px 16px; font-size: 14px; }
        .doctor-card { padding: 16px; border-radius: 18px; }
        .doctor-card h3 { font-size: 16px; }
        .doctor-card p { font-size: 13px; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; }
        .topbar { gap: 8px; }
        h1 { font-size: 20px; }
        .subtitle { font-size: 12px; }
        .card { padding: 14px 12px; border-radius: 16px; }
        .grid { gap: 12px; }
        select, input { padding: 10px 12px; font-size: 13px; margin-bottom: 12px; }
        label { font-size: 13px; }
        .button { padding: 10px 14px; font-size: 12px; border-radius: 12px; }
        .doctor-card { padding: 12px; border-radius: 14px; }
        .doctor-card h3 { font-size: 14px; }
        .doctor-card p { font-size: 12px; }
        .link { font-size: 12px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Book Appointment</h1>
            <p class="subtitle">Hello, <?php echo htmlspecialchars($patient_name); ?>. Choose a doctor and request your appointment.</p>
        </div>
        <div>
            <a class="link" href="dashboard.php">Back to Dashboard</a>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 20px; font-weight: 600;">Home</a>
        </div>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php elseif (!empty($message)): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grid">
            <div>
                <h2>Choose your appointment</h2>
                <form method="POST" action="book-appointment.php">
                    <label for="doctor_id">Select Doctor</label>
                    <select id="doctor_id" name="doctor_id" required>
                        <option value="">Pick a doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo (int)$doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']) . ' — ' . htmlspecialchars($doctor['specialty']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="appointment_date">Date</label>
                    <input id="appointment_date" type="date" name="appointment_date" required>

                    <label for="appointment_time">Time</label>
                    <input id="appointment_time" type="time" name="appointment_time" required>

                    <button class="button" type="submit">Request Appointment</button>
                </form>
            </div>
            <div class="doctor-card">
                <h3>Need help?</h3>
                <p>Appointments are reviewed by the hospital admin and updated automatically once confirmed. You can track your booking status from the dashboard.</p>
                <p>If you don't see enough doctors listed, ask admin for support.</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>