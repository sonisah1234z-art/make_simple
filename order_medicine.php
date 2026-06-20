<?php
include "db.php";

if(!isset($_GET['id'])){
    die("Medicine ID Missing");
}

$id = $_GET['id'];

// FETCH MEDICINE

$result = mysqli_query($conn,

"SELECT * FROM medicines WHERE id='$id'");

$row = mysqli_fetch_assoc($result);

if(!$row){
    die("Medicine Not Found");
}

// MEDICINE DATA

$medicine = $row['medicine_name'];
$price = $row['price'];
$stock = $row['stock'];

// PATIENT DATA

$patient = "Patient User";

$qty = 1;

$total = $price * $qty;

// CHECK STOCK

if($stock <= 0){

    die("Medicine Out Of Stock");

}

// NEW STOCK

$new_stock = $stock - $qty;

// UPDATE STOCK

mysqli_query($conn,

"UPDATE medicines
SET stock='$new_stock'
WHERE id='$id'");

// SAVE ORDER

mysqli_query($conn,

"INSERT INTO medicine_orders
(patient_name,medicine_name,quantity,total_price,status)

VALUES
('$patient','$medicine','$qty','$total','Pending')");

?>

<!DOCTYPE html>
<html>
<head>

<title>Order Success</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    text-align:center;
    padding-top:100px;
}

.box{
    width:400px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:10px;
}

a{
    display:inline-block;
    margin-top:20px;
    background:green;
    color:white;
    padding:10px 20px;
    text-decoration:none;
}

</style>

</head>

<body>

<div class="box">

<h2>Medicine Ordered Successfully</h2>

<p>
Medicine:
<b><?php echo $medicine; ?></b>
</p>

<p>
Total Price:
<b>Rs. <?php echo $total; ?></b>
</p>

<p>
Remaining Stock:
<b><?php echo $new_stock; ?></b>
</p>

<a href="buy_medicine.php">
Back
</a>

</div>

</body>
</html>