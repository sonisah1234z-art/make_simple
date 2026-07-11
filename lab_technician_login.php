<?php
session_start();
include "db.php";
require_once 'auth.php';

if(isset($_POST['login'])){

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if(login_user($conn, 'lab_technician', $username, $password)){

        header("Location: lab_technician_dashboard.php");
        exit();

    } else {
        $error = "Invalid Login Credentials!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Lab Technician Login</title>
<link rel="stylesheet" href="assets/css/compact-ui.css">
<style>
body{margin:0;padding:0;font-family:Arial;background:linear-gradient(135deg,#4facfe,#00f2fe);height:100vh;display:flex;justify-content:center;align-items:center}
.login-box{background:white;padding:40px;width:320px;border-radius:15px;box-shadow:0 10px 25px rgba(0,0,0,0.2);text-align:center}
input{width:100%;padding:12px;margin:10px 0;border:1px solid #ddd;border-radius:8px;outline:none}
button{width:100%;padding:12px;background:#2c7be5;color:white;border:none;border-radius:8px;cursor:pointer;font-size:16px}
button:hover{background:#1a5fd1}
.error{color:red;margin-bottom:10px}
</style>
</head>
<body>
<div class="login-box">
    <h2>🔬 Lab Technician Login</h2>
    <?php if(isset($error)) echo "<div class='error'>$error</div>"; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username or Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
</div>
</body>
</html>