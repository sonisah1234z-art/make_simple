<?php
require_once 'auth.php';
require_admin();
include 'db.php';

$result = $conn->query("
SELECT * FROM payments
ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Payment Records</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
}

table{
    width:90%;
    margin:30px auto;
    border-collapse:collapse;
    background:white;
}

th{
    background:#007bff;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    border:1px solid #ddd;
    text-align:center;
}

h2{
    text-align:center;
}

</style>

</head>

<body>

<h2><img src="assets/icons/credit-card.svg" class="icon">Payment Records</h2>

<table>

<tr>
<th>ID</th>
<th>Patient Name</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['patient_name']; ?></td>

<td>Rs. <?php echo $row['amount']; ?></td>

<td><?php echo $row['payment_status']; ?></td>

<td><?php echo $row['created_at']; ?></td>

</tr>

<?php } ?>

</table>

</body>
</html>