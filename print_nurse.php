<?php
include "db.php";

if(!isset($_GET['id'])){
    die("No ID provided");
}

$id = intval($_GET['id']);

$result = mysqli_query($conn,
"SELECT * FROM nurse_records WHERE id='$id'");

if(mysqli_num_rows($result)==0){
    die("No nursing report found for ID: ".$id);
}

$row = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html>
<head>
<title>Nursing Report</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}

.report{
    width:700px;
    margin:30px auto;
    background:white;
    padding:25px;
    border:1px solid #ccc;
}

h2{
    text-align:center;
}

p{
    font-size:18px;
    margin:12px 0;
}

button{
    padding:10px 20px;
    background:green;
    color:white;
    border:none;
    cursor:pointer;
}
</style>

</head>
<body>

<div class="report">

<h2>🏥 Nursing Report</h2>

<p><b>Patient Name:</b> <?php echo $row['patient_name']; ?></p>

<p><b>Vitals:</b> <?php echo $row['vitals']; ?></p>

<p><b>Note:</b> <?php echo $row['note']; ?></p>

<p><b>Attendance:</b> <?php echo $row['attendance']; ?></p>

<p><b>Date:</b> <?php echo date("Y-m-d H:i:s"); ?></p>

<br>

<button onclick="window.print()">Print Report</button>

</div>

</body>
</html>