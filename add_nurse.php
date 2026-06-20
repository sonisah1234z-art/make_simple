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
</head>

<body>

<div class="header">
👩‍⚕ Nurse Management System
</div>

<div class="container">

<h2>Add New Nurse</h2>

<div class="card-box">
    <div class="card card1">👩‍⚕ Nurse</div>
    <div class="card card2">🏥 Hospital</div>
    <div class="card card3">📋 Staff</div>
</div>

<form method="post">

<label>Nurse Name</label>
<input type="text" name="nurse_name" placeholder="Enter Nurse Name" required>

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

<a href="admin_dashboard.php" class="back">
⬅ Back to Dashboard
</a>

</div>

</body>
</html>