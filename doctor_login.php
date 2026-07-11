<?php
session_start();
include 'db.php';
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login_user($conn, 'doctor', $identifier, $password)) {
        header('Location: doctor-dashboard.php');
        exit();
    }

    $error = 'Invalid email/name or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Login</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #e2e8f0;
}
.login-panel {
    width: min(420px, calc(100% - 32px));
    padding: 32px;
    border-radius: 24px;
    background: rgba(15, 23, 42, 0.92);
    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(148, 163, 184, 0.18);
}
.login-panel h1 {
    font-size: 28px;
    margin-bottom: 16px;
}
.login-panel p {
    margin-bottom: 24px;
    color: #cbd5e1;
    line-height: 1.6;
}
.field {
    margin-bottom: 18px;
}
label {
    display: block;
    margin-bottom: 8px;
    color: #cbd5e1;
    font-size: 14px;
}
input {
    width: 100%;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid rgba(226, 232, 240, 0.16);
    background: rgba(255, 255, 255, 0.06);
    color: #f8fafc;
    font-size: 15px;
    outline: none;
}
input:focus {
    border-color: #60a5fa;
    background: rgba(255, 255, 255, 0.1);
}
button {
    width: 100%;
    padding: 14px 16px;
    border: none;
    border-radius: 14px;
    background: #38bdf8;
    color: #0f172a;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}
button:hover {
    background: #60a5fa;
}
.error {
    margin-bottom: 18px;
    padding: 14px 16px;
    border-radius: 14px;
    background: rgba(248, 113, 113, 0.18);
    border: 1px solid rgba(248, 113, 113, 0.3);
    color: #fee2e2;
}
.link-row {
    margin-top: 16px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.link-row a {
    color: #93c5fd;
    text-decoration: none;
    font-size: 14px;
}
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>
<body>
<div class="login-panel">
    <h1>Doctor Login</h1>
    <p>Enter your hospital email or name and password to access your dashboard.</p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="field">
            <label for="identifier">Email or Name</label>
            <input id="identifier" type="text" name="identifier" placeholder="doctor@hospital.com or Dr. Name" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Enter Password" required>
        </div>

        <button type="submit">Login</button>
    </form>

    <div class="link-row">
        <a href="login.php">Back to Role Login</a>
        <a href="index.php">Home</a>
    </div>
</div>
</body>
</html>
