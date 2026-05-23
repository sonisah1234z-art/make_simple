<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        $result = $conn->query("SELECT id FROM patients WHERE email = '$email'");
        if ($result && $result->num_rows > 0) {
            $error = 'This email is already registered';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO patients (name, email, phone, password) VALUES ('$name', '$email', '$phone', '$hash')";
            if ($conn->query($sql) === TRUE) {
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = 'Unable to create account, try again later';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Register</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: radial-gradient(circle at top left, #0f172a, #334155 35%, #0f172a 100%);
        color: #e2e8f0;
    }
    .register-card {
        width: 100%;
        max-width: 520px;
        background: rgba(15, 23, 42, 0.94);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 28px;
        padding: 40px 38px;
        box-shadow: 0 32px 90px rgba(15, 23, 42, 0.35);
        backdrop-filter: blur(12px);
    }
    h1 { font-size: 34px; margin-bottom: 8px; }
    p.subtitle { color: #94a3b8; line-height: 1.7; margin-bottom: 26px; }
    .field { margin-bottom: 18px; }
    label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        color: #cbd5e1;
    }
    input {
        width: 100%;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.16);
        background: rgba(255,255,255,0.08);
        color: #f8fafc;
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    input::placeholder { color: rgba(226, 232, 240, 0.5); }
    input:focus { border-color: #60a5fa; background: rgba(255,255,255,0.12); }
    .button {
        width: 100%;
        margin-top: 28px;
        padding: 16px 18px;
        border: none;
        border-radius: 18px;
        background: linear-gradient(135deg, #60a5fa, #2563eb);
        color: white;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .button:hover { transform: translateY(-2px); box-shadow: 0 18px 40px rgba(37, 99, 235, 0.24); }
    .error {
        background: rgba(248, 113, 113, 0.14);
        color: #fecaca;
        border: 1px solid rgba(248, 113, 113, 0.22);
        padding: 14px 16px;
        border-radius: 16px;
        margin-bottom: 18px;
    }
    .links { margin-top: 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 14px; color: #94a3b8; }
    .links a { color: #93c5fd; text-decoration: none; }
    .links a:hover { text-decoration: underline; }
    @media (max-width: 480px) {
        body { padding: 12px; }
        .register-card { max-width: 100%; padding: 28px 20px; border-radius: 20px; }
        h1 { font-size: 24px; }
        p.subtitle { font-size: 13px; }
        .field { margin-bottom: 12px; }
        label { font-size: 13px; }
        input { padding: 12px 14px; font-size: 14px; margin-bottom: 0; }
        .button { margin-top: 20px; padding: 12px 14px; font-size: 14px; }
        .error { padding: 12px 14px; font-size: 12px; }
        .links { gap: 8px; flex-direction: column; margin-top: 14px; font-size: 12px; }
    }
</style>
</head>
<body>
<div class="register-card">
    <h1>Register Patient</h1>
    <p class="subtitle">Create your patient account and book appointments with top doctors.</p>
    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" action="register.php">
        <div class="field">
            <label for="name">Full Name</label>
            <input id="name" type="text" name="name" placeholder="Enter your full name" required>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="patient@example.com" required>
        </div>
        <div class="field">
            <label for="phone">Phone</label>
            <input id="phone" type="text" name="phone" placeholder="Phone number" required>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Create a password" required>
        </div>
        <div class="field">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" type="password" name="confirm_password" placeholder="Confirm password" required>
        </div>
        <button class="button" type="submit">Create Account</button>
    </form>
    <div class="links">
        <span>Already have an account?</span>
        <a href="login.php">Login here</a>
    </div>
</div>
</body>
</html>