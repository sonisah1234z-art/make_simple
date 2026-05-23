<?php
session_start();
include 'db.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: admin-dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM admins WHERE username = '$username'");
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: admin-dashboard.php');
            exit();
        } else {
            $error = 'Username found but password is incorrect. Try again.';
        }
    } else {
        $error = 'Username not found. Try admin or contact support.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at top left, #0f172a, #1f2937 30%, #0f172a 100%);
        color: #e2e8f0;
        padding: 20px;
    }
    .login-panel {
        width: 100%;
        max-width: 420px;
        background: rgba(15, 23, 42, 0.94);
        border: 1px solid rgba(148, 163, 184, 0.18);
        border-radius: 28px;
        box-shadow: 0 40px 120px rgba(15, 23, 42, 0.35);
        overflow: hidden;
    }
    .hero {
        background: linear-gradient(135deg, rgba(96, 165, 250, 0.95), rgba(59, 130, 246, 0.9));
        padding: 36px 28px;
        text-align: center;
    }
    .hero h1 {
        font-size: 28px;
        margin-bottom: 10px;
        letter-spacing: -0.04em;
    }
    .hero p {
        color: rgba(226, 232, 240, 0.95);
        font-size: 15px;
        line-height: 1.6;
    }
    .hero .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 18px auto 0;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: rgba(255,255,255,0.18);
        font-size: 26px;
        color: white;
    }
    .form-body {
        padding: 32px 28px 28px;
    }
    .form-body label {
        display: block;
        margin-bottom: 8px;
        color: #cbd5e1;
        font-size: 14px;
    }
    .form-body input {
        width: 100%;
        background: rgba(226, 232, 240, 0.06);
        border: 1px solid rgba(148, 163, 184, 0.24);
        color: #f8fafc;
        border-radius: 14px;
        padding: 14px 16px;
        font-size: 15px;
        margin-bottom: 18px;
        outline: none;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    .form-body input:focus {
        border-color: #60a5fa;
        background: rgba(226, 232, 240, 0.1);
    }
    .form-body button {
        width: 100%;
        padding: 14px 16px;
        border: none;
        border-radius: 14px;
        background: #60a5fa;
        color: #0f172a;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
    }
    .form-body button:hover {
        background: #93c5fd;
        transform: translateY(-1px);
    }
    .form-body .meta {
        margin-top: 18px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        font-size: 14px;
        color: #94a3b8;
    }
    .form-body .meta a {
        color: #93c5fd;
        text-decoration: none;
    }
    .footer-link {
        display: block;
        text-align: center;
        margin-top: 20px;
        color: #94a3b8;
        font-size: 14px;
        text-decoration: none;
    }
    .footer-link:hover {
        color: #ffffff;
    }
    .message {
        padding: 14px 16px;
        border-radius: 16px;
        margin: 0 28px 18px;
        background: rgba(248,113,113,0.14);
        color: #fecaca;
        border: 1px solid rgba(248,113,113,0.2);
    }
    @media (max-width: 480px) {
        body { padding: 12px; }
        .login-panel { border-radius: 20px; }
        .hero { padding: 28px 20px; }
        .hero h1 { font-size: 24px; }
        .hero p { font-size: 13px; }
        .hero .badge { width: 48px; height: 48px; font-size: 22px; }
        .form-body { padding: 24px 20px 20px; }
        .form-body input { padding: 12px 14px; font-size: 14px; margin-bottom: 14px; }
        .form-body button { padding: 12px 14px; font-size: 14px; }
        .form-body .meta { font-size: 12px; }
        .message { margin: 0 20px 14px; padding: 12px 14px; font-size: 13px; }
        .footer-link { font-size: 12px; margin-top: 14px; }
    }
</style>
</head>
<body>
<div class="login-panel">
    <div class="hero">
        <div class="badge">A</div>
        <h1>Admin Access</h1>
        <p>Secure admin login for managing the hospital system.</p>
    </div>
    <?php if ($error): ?>
        <div class="message"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST" action="admin-login.php" class="form-body">
        <label for="username">Admin Username</label>
        <input id="username" type="text" name="username" placeholder="Enter admin username" required>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" placeholder="Enter your password" required>

        <button type="submit">Sign In</button>

        <div class="meta">
            <span>Remember me</span>
            <a href="#">Forgot password?</a>
        </div>

        <a class="footer-link" href="index.php">Back to Hospital</a>
    </form>
</div>
</body>
</html>