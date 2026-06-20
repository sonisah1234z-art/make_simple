<?php
include "db.php";

// DELIVER MEDICINE

if(isset($_GET['deliver'])){

    $id = $_GET['deliver'];

    mysqli_query($conn,

    "UPDATE medicine_orders
    SET status='Delivered'
    WHERE id='$id'");
}

// FETCH ORDERS

$result = mysqli_query($conn,

"SELECT * FROM medicine_orders");

?>

<!DOCTYPE html>
<html>
<head>

<title>Medicine Orders</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    padding:30px;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th,td{
    padding:12px;
    border:1px solid #ddd;
    text-align:center;
}

th{
    background:#2c3e50;
    color:white;
}

.deliver-btn{
    background:green;
    color:white;
    padding:8px 12px;
    text-decoration:none;
    border-radius:5px;
}

.pending{
    color:red;
    font-weight:bold;
}

.delivered{
    color:green;
    font-weight:bold;
}

</style>

</head>

<body>

<h2>Medicine Orders</h2>

<table>

<tr>

<th>ID</th>
<th>Patient</th>
<th>Medicine</th>
<th>Quantity</th>
<th>Total</th>
<th>Status</th>
<th>Action</th>

</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['patient_name']; ?></td>

<td><?php echo $row['medicine_name']; ?></td>

<td><?php echo $row['quantity']; ?></td>

<td>Rs. <?php echo $row['total_price']; ?></td>

<td>

<?php

if($row['status']=="Pending"){
    echo "<span class='pending'>Pending</span>";
}else{
    echo "<span class='delivered'>Delivered</span>";
}

?>

</td>

<td>

<?php if($row['status']=="Pending"){ ?>

<a class="deliver-btn"

href="medicine_orders.php?deliver=<?php echo $row['id']; ?>">

Deliver

</a>

<?php } else { ?>

Delivered

<?php } ?>

</td>

</tr>

<?php } ?>

</table>

</body>
</html>