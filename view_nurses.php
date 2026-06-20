<?php
session_start();
include "db.php";

$result = mysqli_query($conn, "SELECT * FROM nurse_records ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>View Nurse Records</title>

<style>

body{
    font-family:Arial, sans-serif;
    background:#f4f6f9;
    margin:0;
    padding:20px;
}

.container{
    width:95%;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
}

h2{
    text-align:center;
    color:#2c3e50;
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#2c3e50;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f9f9f9;
}

.present{
    color:green;
    font-weight:bold;
}

.absent{
    color:red;
    font-weight:bold;
}

.btn{
    padding:6px 12px;
    color:white;
    text-decoration:none;
    border-radius:5px;
    margin-right:5px;
}

.edit{
    background:#3498db;
}

.delete{
    background:#e74c3c;
}

.print{
    background:#27ae60;
}

.back{
    display:inline-block;
    margin-top:20px;
    background:#34495e;
    color:white;
    padding:10px 15px;
    text-decoration:none;
    border-radius:5px;
}

</style>

</head>

<body>

<div class="container">

<h2>🏥 Nurse Records</h2>

<table>

<tr>
    <th>ID</th>
    <th>Patient Name</th>
    <th>Patient ID</th>
    <th>Vitals</th>
    <th>Note</th>
    <th>Attendance</th>
    <th>Action</th>
</tr>

<?php while($row = mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['patient_name']; ?></td>

<td>
<?php
echo isset($row['patient_id']) && $row['patient_id'] != ''
     ? $row['patient_id']
     : 'N/A';
?>
</td>

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

<td>

<a class="btn edit"
href="edit_nurses.php?id=<?php echo $row['id']; ?>">
Edit
</a>

<a class="btn delete"
href="delete_nurses.php?id=<?php echo $row['id']; ?>"
onclick="return confirm('Delete this record?')">
Delete
</a>

<a class="btn print"
href="print_nurse.php?id=<?php echo $row['id']; ?>">
Print
</a>

</td>

</tr>

<?php } ?>

</table>

<br>

<a class="back" href="nurse_dashboard.php">
⬅ Back to Dashboard
</a>

</div>

</body>
</html>