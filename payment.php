<?php
session_start();
include 'db.php';
require_once 'payment_schema.php';

if (!isset($_SESSION['patient_id'])) {
    die("❌ Please login first");
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];
ensure_payments_table($conn);

if(isset($_POST['pay']))
{
    $amount = (float)$_POST['amount'];

    $stmt = $conn->prepare("INSERT INTO payments(patient_id, patient_name, amount, payment_status) VALUES(?, ?, ?, 'Pending')");
    $stmt->bind_param('isd', $patient_id, $patient_name, $amount);

    if($stmt->execute())
    {
        echo "<script>alert('Payment saved for " . htmlspecialchars($patient_name, ENT_QUOTES) . "');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Payment</title>

<style>
body{
    font-family: Arial;
    background: #f2f6ff;
}

.container{
    width: 500px;
    margin: 50px auto;
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 0 10px gray;
}

input{
    width: 95%;
    padding: 10px;
    margin: 10px 0;
}

button{
    width: 100%;
    padding: 12px;
    background: #28a745;
    color: white;
    border: none;
}
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">
</head>

<body>

<div class="container">

<h2><img src="assets/icons/credit-card.svg" class="icon">Hospital Payment</h2>

<p><b>Patient:</b> <?php echo $patient_name; ?></p>

<img src="payment_qr.png" width="200">

<form method="POST">

<input type="number" name="amount" placeholder="Enter Amount" required>

<button type="submit" name="pay">Pay Now</button>

</form>

</div>

</body>
</html>
