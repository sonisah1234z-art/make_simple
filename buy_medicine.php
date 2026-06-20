<?php
include "db.php";

$result = mysqli_query($conn,"SELECT * FROM medicines");
?>

<!DOCTYPE html>
<html>
<head>

<title>Buy Medicine</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    padding:30px;
}

h2{
    margin-bottom:20px;
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

.buy-btn{
    background:green;
    color:white;
    padding:8px 15px;
    text-decoration:none;
    border-radius:5px;
}

.buy-btn:hover{
    background:darkgreen;
}

</style>

</head>

<body>

<h2>Available Medicines</h2>

<table>

<tr>

<th>ID</th>
<th>Medicine</th>
<th>Category</th>
<th>Price</th>
<th>Stock</th>
<th>Expiry Date</th>
<th>Action</th>

</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['medicine_name']; ?></td>

<td><?php echo $row['category']; ?></td>

<td>Rs. <?php echo $row['price']; ?></td>

<td><?php echo $row['stock']; ?></td>

<td><?php echo $row['expiry_date']; ?></td>

<td>

<a class="buy-btn"

href="order_medicine.php?id=<?php echo $row['id']; ?>">

Buy

</a>

</td>

</tr>

<?php } ?>

</table>

</body>
</html>