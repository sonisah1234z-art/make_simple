<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $nurse_name = trim($_POST['nurse_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';

    $username = trim($_POST['username'] ?? '');
    if ($nurse_name === '' || $email === '' || $password === '' || $username === '') {
        $error = 'Nurse name, username, email, and password are required.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO nurses (nurse_name, email, username, phone, department, password) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssssss', $nurse_name, $email, $username, $phone, $department, $hash);

        if ($stmt->execute()) {
            $message = 'Nurse added successfully.';
        } else {
            $error = 'Unable to add nurse. Email or username may already exist.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Add Nurse</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:linear-gradient(135deg,#3498db,#2ecc71);
    min-height:100vh;
}

.header{
    background:#2c3e50;
    color:white;
    text-align:center;
    padding:20px;
    font-size:28px;
    font-weight:bold;
    box-shadow:0 3px 10px rgba(0,0,0,0.2);
}

.container{
    width:500px;
    margin:40px auto;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
}

.container h2{
    text-align:center;
    color:#2c3e50;
    margin-bottom:20px;
}

label{
    font-weight:bold;
    color:#34495e;
}

input{
    width:100%;
    padding:12px;
    margin-top:5px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:15px;
}

input:focus{
    border-color:#3498db;
    outline:none;
}

button{
    width:100%;
    padding:14px;
    background:#27ae60;
    color:white;
    border:none;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#219150;
}

.card-box{
    display:flex;
    gap:10px;
    margin-bottom:20px;
}

.card{
    flex:1;
    text-align:center;
    color:white;
    padding:12px;
    border-radius:10px;
    font-weight:bold;
}

.card1{
    background:#3498db;
}

.card2{
    background:#e67e22;
}

.card3{
    background:#27ae60;
}

.back{
    display:block;
    text-align:center;
    margin-top:15px;
    text-decoration:none;
    font-weight:bold;
    color:#2c3e50;
}

.back:hover{
    color:#3498db;
}

</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>

<body>

<div class="header">
👩‍⚕ Nurse Management System
</div>

<div class="container">

<h2>Add New Nurse</h2>

<?php if ($message): ?>
<div style="background:#dcfce7;color:#166534;padding:12px;border-radius:8px;margin-bottom:15px;"><?php echo htmlspecialchars($message); ?></div>
<?php elseif ($error): ?>
<div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:15px;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card-box">
    <div class="card card1">👩‍⚕ Nurse</div>
    <div class="card card2">🏥 Hospital</div>
    <div class="card card3">📋 Staff</div>
</div>

<form method="post">

<label>Nurse Name</label>
<input type="text" name="nurse_name" placeholder="Enter Nurse Name" required>

<label>Username</label>
<input type="text" name="username" placeholder="Enter Username" required>

<label>Email</label>
<input type="email" name="email" placeholder="Enter Email" required>

<label>Phone Number</label>
<input type="text" name="phone" placeholder="Enter Phone Number" required>

<label>Department</label>
<input type="text" name="department" placeholder="Enter Department" required>

<label>Password</label>
<input type="password" name="password" placeholder="Enter Password" required>

<button type="submit" name="save">
➕ Add Nurse
</button>

</form>

<a href="admin-dashboard.php" class="back">
⬅ Back to Dashboard
</a>

</div>

</body>
</html>
