<?php
session_start();
include "db.php";

$nurse_name = $_SESSION['username'];

$result = mysqli_query($conn,"
SELECT * FROM nurse_shifts
WHERE nurse_name='$nurse_name'
ORDER BY shift_date DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>My Shift</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#f4f6f9;
}

/* Header */

.header{
    background:linear-gradient(135deg,#3498db,#2ecc71);
    color:white;
    text-align:center;
    padding:25px;
    font-size:28px;
    font-weight:bold;
    box-shadow:0 3px 10px rgba(0,0,0,0.2);
}

/* Container */

.container{
    width:90%;
    margin:30px auto;
}

/* Welcome Card */

.welcome{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    margin-bottom:20px;
    text-align:center;
}

.welcome h2{
    color:#2c3e50;
}

/* Table */

.table-box{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#2c3e50;
    color:white;
    padding:15px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f1f1f1;
}

/* Shift Badges */

.morning{
    background:#f39c12;
    color:white;
    padding:8px 15px;
    border-radius:20px;
    font-weight:bold;
}

.evening{
    background:#8e44ad;
    color:white;
    padding:8px 15px;
    border-radius:20px;
    font-weight:bold;
}

.night{
    background:#2c3e50;
    color:white;
    padding:8px 15px;
    border-radius:20px;
    font-weight:bold;
}

/* Back Button */

.back{
    display:inline-block;
    margin-top:20px;
    padding:12px 20px;
    background:#3498db;
    color:white;
    text-decoration:none;
    border-radius:8px;
    font-weight:bold;
}

.back:hover{
    background:#2980b9;
}

</style>

</head>

<body>

<div class="header">
📅 My Duty Shifts
</div>

<div class="container">

<div class="welcome">
    <h2>Welcome, <?php echo $_SESSION['username']; ?> 👩‍⚕️</h2>
    <p>Your assigned duty shifts are shown below.</p>
</div>

<div class="table-box">

<table>

<tr>
<th>ID</th>
<th>Shift Type</th>
<th>Shift Date</th>
<th>Status</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td>

<?php
if($row['shift_type']=="Morning Shift"){
    echo "<span class='morning'>🌞 Morning Shift</span>";
}
elseif($row['shift_type']=="Evening Shift"){
    echo "<span class='evening'>🌇 Evening Shift</span>";
}
else{
    echo "<span class='night'>🌙 Night Shift</span>";
}
?>

</td>

<td><?php echo $row['shift_date']; ?></td>

<td style="color:green;font-weight:bold;">
Assigned
</td>

</tr>

<?php } ?>

</table>

<a href="nurse_dashboard.php" class="back">
⬅ Back to Dashboard
</a>

</div>

</div>

</body>
</html>