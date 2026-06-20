<?php
session_start();
include 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $role = $_POST['role'] ?? '';
    $password = trim($_POST['password']);

    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';

    $table = '';
    $field = '';
    $value = '';
    $redirect = '';

    // ROLE SETUP
    if ($role == 'admin') {
        $table = 'admins';
        $field = 'username';
        $value = $username;
        $redirect = 'admin_dashboard.php';

    } elseif ($role == 'pharmacist') {
        $table = 'pharmacists';
        $field = 'username';
        $value = $username;
        $redirect = 'pharmacist_dashboard.php';

    } elseif ($role == 'patient') {
        $table = 'patients';
        $field = 'email';
        $value = $email;
        $redirect = 'patient_dashboard.php';

    } elseif ($role == 'nurse') {
        $table = 'nurses';
        $field = 'email';
        $value = $email;
        $redirect = 'nurse_dashboard.php';
    }

    if (!empty($table)) {

        $query = "SELECT * FROM $table WHERE $field='$value'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {

            $user = mysqli_fetch_assoc($result);

            // SAFE PASSWORD CHECK
            if (password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $role;

                header("Location: $redirect");
                exit();

            } else {
                $error = "Incorrect Password!";
            }

        } else {
            $error = "User Not Found!";
        }

    } else {
        $error = "Please select role!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Hospital Login</title>

<style>
body{
    margin:0;
    font-family:Segoe UI;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);
}

.card{
    width:420px;
    padding:30px;
    background:rgba(255,255,255,0.1);
    backdrop-filter:blur(20px);
    border-radius:20px;
    color:white;
}

input, select{
    width:100%;
    padding:12px;
    margin-bottom:10px;
    border-radius:10px;
    border:none;
}

button{
    width:100%;
    padding:12px;
    background:#2563eb;
    color:white;
    border:none;
    border-radius:10px;
    cursor:pointer;
}

.role{
    display:flex;
    gap:5px;
    margin-bottom:10px;
}

.error{
    background:red;
    padding:10px;
    margin-bottom:10px;
    border-radius:8px;
}
</style>
</head>

<body>

<div class="card">

<h2>Hospital Login</h2>

<?php if($error): ?>
<div class="error"><?php echo $error; ?></div>
<?php endif; ?>

<form method="POST">

<select name="role" required>
    <option value="">Select Role</option>
    <option value="admin">Admin</option>
    <option value="patient">Patient</option>
    <option value="nurse">Nurse</option>
    <option value="pharmacist">Pharmacist</option>
</select>

<input type="text" name="username" placeholder="Username (Admin/Pharmacist)">
<input type="email" name="email" placeholder="Email (Patient/Nurse)">
<input type="password" name="password" placeholder="Password" required>

<button type="submit">Login</button>

</form>

</div>

</body>
</html>