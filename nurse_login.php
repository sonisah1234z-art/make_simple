<?php
session_start();
include "db.php";
require_once 'auth.php';

if(isset($_POST['login']))
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if(login_user($conn, 'nurse', $email, $password))
    {
        header("Location: nurse_dashboard.php");
        exit();
    }
    else
    {
        $error = "Invalid Email or Password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Login</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:linear-gradient(135deg,#3498db,#2ecc71);
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.login-box{
    width:400px;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
}

.login-box h2{
    text-align:center;
    margin-bottom:20px;
    color:#2c3e50;
}

input{
    width:100%;
    padding:12px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    border:none;
    background:#27ae60;
    color:white;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
}

button:hover{
    background:#219150;
}

.error{
    color:red;
    text-align:center;
    margin-bottom:10px;
}

</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>

<body>

<div class="login-box">

<h2>👩‍⚕️ Nurse Login</h2>

<?php
if(isset($error)){
    echo "<div class='error'>$error</div>";
}
?>

<form method="post">

<input type="text"
name="email"
placeholder="Enter Username or Email"
required>

<input type="password"
name="password"
placeholder="Enter Password"
required>

<button type="submit" name="login">
Login
</button>

</form>

</div>

</body>
</html>
