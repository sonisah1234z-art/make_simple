<?php
session_start();
include 'db.php';
require_once 'auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $identifier = trim($_POST['identifier'] ?? ($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (login_user($conn, $role, $identifier, $password)) {
        header('Location: ' . role_dashboard($_SESSION['role']));
        exit();
    }

    $error = 'Invalid role, username/email, or password.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Hospital Login</title>

<style>
body{
    margin:0;
    font-family: Arial;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(135deg,#1e293b,#0f172a);
}

.login-card{
    width:380px;
    padding:30px;
    border-radius:20px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(20px);
    color:white;
    box-shadow:0 20px 50px rgba(0,0,0,0.4);
}

h1{
    text-align:center;
    margin-bottom:10px;
}

.subtext{
    text-align:center;
    color:#cbd5e1;
    margin-bottom:20px;
}

.form-group{
    margin-bottom:15px;
}

label{
    display:block;
    margin-bottom:6px;
    font-size:14px;
    color:#cbd5e1;
}

input, select{
    width:100%;
    padding:12px;
    border-radius:12px;
    border:none;
    outline:none;
    background:rgba(255,255,255,0.1);
    color:white;
}

.btn{
    width:100%;
    padding:12px;
    border:none;
    border-radius:12px;
    background:#2563eb;
    color:white;
    font-weight:bold;
    cursor:pointer;
    margin-top:10px;
}

.btn:hover{
    background:#1d4ed8;
}

.error{
    background:rgba(255,0,0,0.2);
    padding:10px;
    border-radius:10px;
    margin-bottom:10px;
    text-align:center;
}

.link-row{
    margin-top:15px;
    display:flex;
    justify-content:space-between;
    font-size:13px;
}

.link-row a{
    color:#93c5fd;
    text-decoration:none;
}
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">

</head>

<body>

<div class="login-card">

    <h1>Hospital Login</h1>
    <p class="subtext">Admin • Patient • Nurse • Pharmacist</p>

    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Select Role</label>
            <select name="role" required>
                <option value="">-- Choose Role --</option>
                <option value="admin">Admin</option>
                <option value="doctor">Doctor</option>
                <option value="patient">Patient</option>
                <option value="nurse">Nurse</option>
                <option value="pharmacist">Pharmacist</option>
                <option value="receptionist">Receptionist</option>
            </select>
        </div>

        <div class="form-group">
            <label>Username or Email</label>
            <input type="text" name="identifier" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <button class="btn" type="submit">Login</button>

    </form>

    <div class="link-row">
        <a href="register.php">Create Account</a>
        <a href="index.php">Home</a>
    </div>

</div>

</body>
</html>
