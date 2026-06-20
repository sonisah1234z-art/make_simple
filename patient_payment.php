<?php
session_start();
include 'db.php';

if(!isset($_SESSION['patient_id']))
{
    die("Please Login First");
}

$patient_id = $_SESSION['patient_id'];

$result = $conn->query("
SELECT * FROM payments
WHERE patient_id='$patient_id'
ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>My Payments</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    background:linear-gradient(135deg,#4facfe,#00f2fe);
    min-height:100vh;
    padding:30px;
}

.container{
    width:95%;
    max-width:1000px;
    margin:auto;
    background:white;
    border-radius:15px;
    padding:25px;
    box-shadow:0 0 20px rgba(0,0,0,0.2);
}

h2{
    text-align:center;
    color:#2c3e50;
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    overflow:hidden;
}

th{
    background:#2c7be5;
    color:white;
    padding:15px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f5f9ff;
}

.paid{
    background:#28a745;
    color:white;
    padding:5px 12px;
    border-radius:20px;
    font-size:13px;
}

.pending{
    background:#dc3545;
    color:white;
    padding:5px 12px;
    border-radius:20px;
    font-size:13px;
}

.header-box{
    text-align:center;
    margin-bottom:20px;
}

.header-box h1{
    color:#2c7be5;
    font-size:32px;
}

.no-record{
    text-align:center;
    color:gray;
    padding:20px;
}

</style>

</head>

<body>

<div class="container">

<div class="header-box">
    <h1><img src="assets/icons/credit-card.svg" class="icon">My Payment History</h1>
    <p>View all your hospital payments</p>
</div>

<table>

<tr>
<th>Payment ID</th>
<th>Amount</th>
<th>Status</th>
<th>Date</th>
</tr>

<?php
if($result && $result->num_rows > 0)
{
    while($row = $result->fetch_assoc())
    {
?>
<tr>

<td>#<?php echo $row['id']; ?></td>

<td>
Rs. <?php echo number_format($row['amount'],2); ?>
</td>

<td>

<?php
if($row['payment_status'] == 'Paid')
{
    echo "<span class='paid'>✓ Paid</span>";
}
else
{
    echo "<span class='pending'>⏳ Pending</span>";
}
?>

</td>

<td>
<?php echo $row['created_at']; ?>
</td>

</tr>

<?php
    }
}
else
{
    echo "
    <tr>
        <td colspan='4' class='no-record'>
        No payment records found.
        </td>
    </tr>";
}
?>

</table>

</div>

</body>
</html>