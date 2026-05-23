<?php
session_start();
include 'db.php';

if (isset($_SESSION['patient_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $result = $conn->query("SELECT * FROM patients WHERE email = '$email'");
    if ($result && $result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        if (password_verify($password, $patient['password'])) {
            $_SESSION['patient_id'] = $patient['id'];
            $_SESSION['patient_name'] = $patient['name'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Email found but password is incorrect. Try again.';
        }
    } else {
        $error = 'Email not found. Please register first or check your email.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Patient Login</title>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at top left, #5b6ff0, #29335c 28%, #0f172a 100%);
        color: #e2e8f0;
    }
    .login-card {
        width: 100%;
        max-width: 420px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 28px;
        padding: 36px 32px;
        box-shadow: 0 32px 90px rgba(15, 23, 42, 0.32);
        backdrop-filter: blur(20px);
    }
    .login-card h1 {
        margin-bottom: 12px;
        font-size: 32px;
        letter-spacing: -0.04em;
    }
    .login-card p.subtext {
        margin-bottom: 28px;
        color: #cbd5e1;
        line-height: 1.7;
    }
    .form-group {
        margin-bottom: 18px;
    }
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
        border: 1px solid rgba(226,232,240,0.15);
        background: rgba(255,255,255,0.08);
        color: #f8fafc;
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s ease, background 0.2s ease;
    }
    input:focus {
        border-color: #93c5fd;
        background: rgba(255,255,255,0.14);
    }
    .btn {
        width: 100%;
        padding: 14px 16px;
        border: none;
        border-radius: 16px;
        background: linear-gradient(135deg, #60a5fa, #2563eb);
        color: white;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 40px rgba(37, 99, 235, 0.24);
    }
    .link-row {
        margin-top: 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .link-row a {
        color: #93c5fd;
        text-decoration: none;
        font-size: 14px;
    }
    .error {
        margin-bottom: 18px;
        padding: 12px 14px;
        border-radius: 14px;
        background: rgba(248, 113, 113, 0.12);
        color: #fecaca;
        border: 1px solid rgba(248, 113, 113, 0.2);
        font-size: 14px;
    }
    .help-link {
        margin-top: 18px;
        display: block;
        text-align: center;
        color: #94a3b8;
        text-decoration: none;
        font-size: 14px;
    }
    .help-link:hover {
        color: #f8fafc;
    }
    @media (max-width: 480px) {
        body { padding: 12px; }
        .login-card { max-width: 100%; padding: 28px 20px; border-radius: 20px; }
        .login-card h1 { font-size: 24px; margin-bottom: 10px; }
        .login-card p.subtext { margin-bottom: 20px; font-size: 13px; }
        .form-group { margin-bottom: 14px; }
        label { font-size: 13px; }
        input { padding: 12px 14px; font-size: 14px; }
        .btn { padding: 12px 14px; font-size: 14px; }
        .link-row { margin-top: 14px; gap: 8px; flex-direction: column; }
        .link-row a { font-size: 12px; }
        .error { margin-bottom: 14px; padding: 10px 12px; font-size: 12px; }
        .help-link { margin-top: 14px; font-size: 12px; }
    }
</style>
</head>
<body>
<div class="login-card">
    <h1>Welcome Back</h1>
    <p class="subtext">Sign in to continue to your user dashboard and access all features securely.</p>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php if (strpos($error, 'Database connection failed') !== false): ?>
            <p style="font-size: 13px; color: #94a3b8; margin-bottom: 18px;">
                <a href="setup.php" style="color: #93c5fd;">Initialize the database first</a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="login.php" <?php echo ($error && strpos($error, 'Database connection failed') !== false) ? 'style="display:none;"' : ''; ?>>
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="patient@example.com" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Enter your password" required>
        </div>
        <button class="btn" type="submit">Login</button>
    </form>

    <div class="link-row">
        <a href="register.php">Create account</a>
        <a href="#">Forgot password?</a>
    </div>
    <a class="help-link" href="index.php">Back to Hospital</a>
</div>
</body>
</html>