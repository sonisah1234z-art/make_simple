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
    if (isset($_POST['delete_doctor'])) {
        $doctor_id = (int)$_POST['doctor_id'];
        $stmt = $conn->prepare('DELETE FROM doctors WHERE id = ?');
        $stmt->bind_param('i', $doctor_id);
        if ($stmt->execute()) {
            $message = 'Doctor removed successfully.';
        } else {
            $error = 'Unable to remove doctor.';
        }
        $stmt->close();
    } elseif (isset($_POST['delete_all'])) {
        $conn->query('DELETE FROM doctors');
        $message = 'All doctors have been removed.';
    } else {
        $name = $conn->real_escape_string($_POST['name']);
        $specialty = $conn->real_escape_string($_POST['specialty']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);

        if ($name && $specialty) {
            $stmt = $conn->prepare('INSERT INTO doctors (name, specialty, email, phone) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $name, $specialty, $email, $phone);
            if ($stmt->execute()) {
                $message = 'Doctor added successfully.';
            } else {
                $error = 'Unable to add doctor. Email may already exist.';
            }
            $stmt->close();
        } else {
            $error = 'Name and specialty are required.';
        }
    }
}

$doctors = [];
$result = $conn->query('SELECT id, name, specialty, email, phone, created_at FROM doctors ORDER BY created_at DESC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Doctors</title>
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
    .layout { display: grid; grid-template-columns: 1.3fr 0.7fr; gap: 24px; }
    .table-card, .form-card { background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(148, 163, 184, 0.16); border-radius: 28px; padding: 24px; box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22); }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid rgba(148, 163, 184, 0.12); }
    th { color: #cbd5e1; font-weight: 700; }
    td { color: #e2e8f0; }
    label { display: block; margin-bottom: 8px; color: #cbd5e1; font-size: 14px; }
    input { width: 100%; padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(148, 163, 184, 0.18); background: rgba(255,255,255,0.06); color: #f8fafc; margin-bottom: 16px; }
    input:focus { outline: none; border-color: #60a5fa; background: rgba(255,255,255,0.1); }
    .button-submit { width: 100%; margin-top: 10px; }
    .alert { border-radius: 18px; padding: 18px; margin-bottom: 22px; }
    .alert.error { background: rgba(248,113,113,0.14); color: #fecaca; border: 1px solid rgba(248,113,113,0.24); }
    .alert.success { background: rgba(34,197,94,0.14); color: #bbf7d0; border: 1px solid rgba(34,197,94,0.24); }
    .button-delete { background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 6px 10px; font-size: 12px; border: 1px solid rgba(239, 68, 68, 0.3); }
    .button-delete:hover { background: rgba(239, 68, 68, 0.3); }
    .button-delete-all { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.3); margin-top: 16px; width: 100%; }
    .button-delete-all:hover { background: rgba(239, 68, 68, 0.3); }
    @media (max-width: 960px) { .layout { grid-template-columns: 1fr; } .table-card { overflow-x: auto; } }
    @media (max-width: 768px) {
        .page { padding: 20px 16px; }
        .topbar { gap: 12px; margin-bottom: 20px; flex-direction: column; align-items: flex-start; }
        h1 { font-size: 26px; }
        .layout { gap: 16px; }
        .table-card, .form-card { padding: 16px; border-radius: 20px; }
        table { font-size: 12px; }
        th, td { padding: 10px 8px; }
    }
    @media (max-width: 480px) {
        .page { padding: 16px 12px; }
        h1 { font-size: 20px; }
        .button { padding: 10px 14px; font-size: 12px; }
        table { font-size: 11px; }
        th, td { padding: 6px; }
        .table-card, .form-card { padding: 12px; }
        input { padding: 10px 12px; margin-bottom: 12px; }
        .button-delete { padding: 5px 8px; font-size: 10px; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>Manage Doctors</h1>
            <p class="subtitle">Admin <?php echo htmlspecialchars($admin_name); ?>, add new specialists and review the current roster.</p>
        </div>
        <div>
            <a class="button" href="admin-dashboard.php">Dashboard</a>
            <a href="index.php" style="color: #93c5fd; text-decoration: none; margin-left: 12px; font-weight: 600;">Home</a>
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
            <h2>Doctor Roster</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Specialty</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($doctors) === 0): ?>
                        <tr><td colspan="6" style="color:#94a3b8; padding:18px;">No doctors have been added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($doctors as $doctor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                                <td><?php echo htmlspecialchars($doctor['created_at']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this doctor?');">
                                        <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                        <button type="submit" name="delete_doctor" class="button button-delete">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (count($doctors) > 0): ?>
                <form method="POST" style="margin-top: 16px;" onsubmit="return confirm('Are you sure you want to remove ALL doctors? This action cannot be undone.');">
                    <button type="submit" name="delete_all" class="button button-delete-all">Remove All Doctors</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="form-card">
            <h2>Add Doctor</h2>
            <form method="POST" action="admin-doctors.php">
                <label for="name">Name</label>
                <input id="name" name="name" type="text" placeholder="Doctor name" required>

                <label for="specialty">Specialty</label>
                <input id="specialty" name="specialty" type="text" placeholder="Example: Cardiology" required>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="doctor@example.com">

                <label for="phone">Phone</label>
                <input id="phone" name="phone" type="text" placeholder="Contact number">

                <button class="button button-submit" type="submit">Add Doctor</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>