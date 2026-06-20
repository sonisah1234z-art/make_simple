<?php
session_start();
include "db.php";

$result = mysqli_query($conn,
"SELECT * FROM nurse_records ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Nurse Records</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#eef2f7;
    padding:30px;
}

.header{
    background:#2c3e50;
    color:white;
    padding:20px 30px;
    border-radius:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.header h2{
    font-size:28px;
}

.back-btn{
    text-decoration:none;
    background:#27ae60;
    color:white;
    padding:10px 18px;
    border-radius:8px;
    font-weight:bold;
}

.back-btn:hover{
    background:#219150;
}

.container{
    background:white;
    border-radius:12px;
    padding:25px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

th{
    background:#34495e;
    color:white;
    padding:14px;
}

td{
    padding:12px;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f8f9fa;
}

.present{
    background:#d4edda;
    color:#155724;
    padding:5px 10px;
    border-radius:5px;
    font-weight:bold;
}

.absent{
    background:#f8d7da;
    color:#721c24;
    padding:5px 10px;
    border-radius:5px;
    font-weight:bold;
}

.print-btn{
    margin-top:20px;
    background:#3498db;
    color:white;
    border:none;
    padding:12px 20px;
    border-radius:8px;
    cursor:pointer;
    font-size:15px;
}

.print-btn:hover{
    background:#2980b9;
}

.total-box{
    margin-bottom:20px;
    background:#3498db;
    color:white;
    padding:15px;
    border-radius:10px;
    font-size:18px;
    font-weight:bold;
}

</style>

</head>
<body>

<div class="header">
    <h2>Nurse Records Management</h2>

    <a href="admin_dashboard.php" class="back-btn">
        Back Dashboard
    </a>
</div>

<div class="container">

<?php
$total = mysqli_num_rows($result);
?>

<div class="total-box">
    Total Records: <?php echo $total; ?>
</div>

<table>

<tr>
    <th>ID</th>
    <th>Patient Name</th>
    <th>Vitals</th>
    <th>Nurse Notes</th>
    <th>Attendance</th>
</tr>

<?php
mysqli_data_seek($result,0);

while($row = mysqli_fetch_assoc($result)){
?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['patient_name']; ?></td>

<td><?php echo $row['vitals']; ?></td>

<td><?php echo $row['note']; ?></td>

<td>
<?php
if($row['attendance']=="Present"){
    echo "<span class='present'>Present</span>";
}else{
    echo "<span class='absent'>Absent</span>";
}
?>
</td>

</tr>

<?php } ?>

</table>

<button class="print-btn" onclick="window.print()">
🖨 Print Report
</button>

</div>

</body>
</html>