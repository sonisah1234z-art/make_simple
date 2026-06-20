<?php
include "db.php";

$result = mysqli_query($conn,
"SELECT * FROM nurse_shifts ORDER BY shift_date DESC");
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
    background:#f4f6f9;
}

.header{
    background:linear-gradient(135deg,#3498db,#2ecc71);
    color:white;
    text-align:center;
    padding:25px;
    font-size:30px;
    font-weight:bold;
    box-shadow:0 4px 10px rgba(0,0,0,0.2);
}

.container{
    width:95%;
    margin:30px auto;
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

.title{
    text-align:center;
    color:#2c3e50;
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    overflow:hidden;
    border-radius:10px;
}

th{
    background:#2c3e50;
    color:white;
    padding:15px;
    text-align:center;
}

td{
    padding:14px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f8f9fa;
}

.morning{
    background:#f39c12;
    color:white;
    padding:6px 12px;
    border-radius:20px;
    font-weight:bold;
}

.evening{
    background:#8e44ad;
    color:white;
    padding:6px 12px;
    border-radius:20px;
    font-weight:bold;
}

.night{
    background:#2c3e50;
    color:white;
    padding:6px 12px;
    border-radius:20px;
    font-weight:bold;
}

.card-box{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-bottom:25px;
}

.card{
    padding:20px;
    border-radius:12px;
    color:white;
    text-align:center;
    font-size:18px;
    font-weight:bold;
}

.card1{
    background:#3498db;
}

.card2{
    background:#27ae60;
}

.card3{
    background:#e67e22;
}

.back-btn{
    display:inline-block;
    margin-top:20px;
    padding:12px 20px;
    background:#34495e;
    color:white;
    text-decoration:none;
    border-radius:8px;
    font-weight:bold;
}

.back-btn:hover{
    background:#2c3e50;
}

</style>

</head>

<body>

<div class="header">
🏥 Nurse Shift Management
</div>

<div class="container">

<h2 class="title">📅 Nurse Shift List</h2>

<div class="card-box">

<div class="card card1">
👩‍⚕ Total Nurses<br>
<?php echo mysqli_num_rows($result); ?>
</div>

<div class="card card2">
🌞 Morning / Evening / Night
</div>

<div class="card card3">
📋 Shift Schedule
</div>

</div>

<table>

<tr>
<th>ID</th>
<th>Nurse Name</th>
<th>Shift</th>
<th>Date</th>
</tr>

<?php
mysqli_data_seek($result,0);

while($row=mysqli_fetch_assoc($result)){
?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['nurse_name']; ?></td>

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

</tr>

<?php } ?>

</table>

<a href="admin_dashboard.php" class="back-btn">
⬅ Back to Dashboard
</a>

</div>

</body>
</html>