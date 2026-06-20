<?php
include "db.php";

if(isset($_POST['assign']))
{
    $nurse_name = $_POST['nurse_name'];
    $shift_type = $_POST['shift_type'];
    $shift_date = $_POST['shift_date'];

    mysqli_query($conn,"
    INSERT INTO nurse_shifts
    (nurse_name,shift_type,shift_date)
    VALUES
    ('$nurse_name','$shift_type','$shift_date')
    ");

    echo "<script>alert('Shift Assigned Successfully');</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Shift Management</title>

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
    margin-bottom:20px;
    color:#2c3e50;
}

label{
    font-weight:bold;
    color:#34495e;
}

input,
select{
    width:100%;
    padding:12px;
    margin-top:5px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:15px;
}

input:focus,
select:focus{
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

.shift-box{
    margin-top:20px;
    display:flex;
    justify-content:space-between;
    gap:10px;
}

.card{
    flex:1;
    text-align:center;
    padding:15px;
    border-radius:10px;
    color:white;
    font-weight:bold;
}

.morning{
    background:#f39c12;
}

.evening{
    background:#8e44ad;
}

.night{
    background:#2c3e50;
}

.back{
    display:block;
    text-align:center;
    margin-top:20px;
    text-decoration:none;
    color:#2c3e50;
    font-weight:bold;
}

.back:hover{
    color:#3498db;
}

</style>

</head>

<body>

<div class="header">
🏥 Nurse Shift Management
</div>

<div class="container">

<h2>Assign Nurse Duty Shift</h2>

<form method="post">

<label>Nurse Name</label>
<input type="text" name="nurse_name" placeholder="Enter Nurse Name" required>

<label>Shift Type</label>
<select name="shift_type">
    <option>Morning Shift</option>
    <option>Evening Shift</option>
    <option>Night Shift</option>
</select>

<label>Shift Date</label>
<input type="date" name="shift_date" required>

<button type="submit" name="assign">
Assign Shift
</button>

</form>

<div class="shift-box">

<div class="card morning">
🌞 Morning Shift
</div>

<div class="card evening">
🌇 Evening Shift
</div>

<div class="card night">
🌙 Night Shift
</div>

</div>

<a class="back" href="admin_dashboard.php">
⬅ Back to Dashboard
</a>

</div>

</body>
</html>