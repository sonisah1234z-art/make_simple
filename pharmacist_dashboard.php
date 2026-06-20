<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include "db.php";

/* LOGIN CHECK */
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'Pharmacist'){
    header("Location: pharmacist_login.php");
    exit();
}

/* TOTAL MEDICINES */
$total_medicine = mysqli_query($conn,
"SELECT COUNT(*) as total FROM medicines");
$totalMedicines = mysqli_fetch_assoc($total_medicine)['total'];

/* LOW STOCK COUNT */
$low_stock = mysqli_query($conn,
"SELECT COUNT(*) as lowstock FROM medicines WHERE stock < 20");
$lowStock = mysqli_fetch_assoc($low_stock)['lowstock'];

/* TOTAL SALES */
$total_sales = mysqli_query($conn,
"SELECT SUM(total_price) as totalsales FROM medicine_orders");
$totalSales = mysqli_fetch_assoc($total_sales)['totalsales'];

/* ALL MEDICINES */
$medicine_result = mysqli_query($conn,
"SELECT * FROM medicines");

/* LOW STOCK ALERT (Popup) */
if($lowStock > 0){
    echo "<script>
        alert('⚠️ ALERT: $lowStock medicines are LOW IN STOCK!');
    </script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Pharmacist Dashboard</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
    margin:0;
}

.sidebar{
    width:240px;
    height:100vh;
    background:#2c3e50;
    position:fixed;
    left:0;
    top:0;
    padding-top:20px;
}

.sidebar h2{
    color:white;
    text-align:center;
}

.sidebar a{
    display:block;
    color:white;
    padding:15px;
    text-decoration:none;
}

.sidebar a:hover{
    background:#34495e;
}

.main{
    margin-left:250px;
    padding:20px;
}

.topbar{
    background:white;
    padding:15px;
    border-radius:10px;
    display:flex;
    justify-content:space-between;
}

.logout{
    background:red;
    color:white;
    padding:10px;
    text-decoration:none;
    border-radius:5px;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-top:20px;
}

.card{
    background:white;
    padding:20px;
    border-radius:10px;
    text-align:center;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

.card h3{
    color:#2c3e50;
}

.card p{
    font-size:22px;
    font-weight:bold;
    color:#27ae60;
}

.alert-box{
    margin-top:20px;
    background:#ffe5e5;
    color:#a10000;
    padding:15px;
    border-left:5px solid red;
    font-weight:bold;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:white;
}

th{
    background:#2c3e50;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

.low{
    color:red;
    font-weight:bold;
}
</style>

</head>

<body>

<div class="sidebar">

<h2>Pharmacist</h2>

<a href="#">Dashboard</a>
<a href="add_medicine.php">Add Medicine</a>
<a href="medicine_orders.php">Orders</a>
<a href="#">Reports</a>

</div>

<div class="main">

<div class="topbar">

<h2>Welcome, <?php echo $_SESSION['username']; ?></h2>

<a class="logout" href="logout.php">Logout</a>

</div>

<!-- CARDS -->
<div class="cards">

<div class="card">
<h3>Total Medicines</h3>
<p><?php echo $totalMedicines; ?></p>
</div>

<div class="card">
<h3>Low Stock</h3>
<p style="color:red;"><?php echo $lowStock; ?></p>
</div>

<div class="card">
<h3>Total Sales</h3>
<p>Rs. <?php echo $totalSales; ?></p>
</div>

<div class="card">
<h3>Suppliers</h3>
<p>18</p>
            <a href="restock_medicine.php"><img src="assets/icons/capsule.svg" class="icon">Restock Medicine</a>
            <a href="delete_medicine.php"><img src="assets/icons/trash.svg" class="icon">Delete Medicine</a>
</div>

</div>

<!-- ALERT BOX -->
<?php if($lowStock > 0){ ?>
<div class="alert-box">
    <img src="assets/icons/emergency.svg" class="icon"><strong>Warning!</strong> <?php echo $lowStock; ?> medicines are LOW IN STOCK. Please restock immediately.
</div>
<?php } ?>

<!-- LOW STOCK TABLE -->
<h3 style="margin-top:30px;"><img src="assets/icons/emergency.svg" class="icon">Low Stock Medicines</h3>

<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Stock</th>
<th>Price</th>
<th>Status</th>
</tr>

<?php
$low_meds = mysqli_query($conn,
"SELECT * FROM medicines WHERE stock < 20");

while($row = mysqli_fetch_assoc($low_meds))
{
?>
<tr>
<td><?php echo $row['id']; ?></td>
<td><?php echo $row['medicine_name']; ?></td>
<td class="low"><?php echo $row['stock']; ?></td>
<td><?php echo $row['price']; ?></td>
<td class="low">LOW STOCK</td>
</tr>
<?php } ?>
</table>

<!-- ALL MEDICINES -->
<h3 style="margin-top:30px;">📦 All Medicines</h3>

<table>
<tr>
<th>ID</th>
<th>Name</th>
<th>Category</th>
<th>Stock</th>
<th>Price</th>
<th>Status</th>
</tr>

<?php while($row = mysqli_fetch_assoc($medicine_result)) { ?>
<tr>
<td><?php echo $row['id']; ?></td>
<td><?php echo $row['medicine_name']; ?></td>
<td><?php echo $row['category']; ?></td>
<td><?php echo $row['stock']; ?></td>
<td><?php echo $row['price']; ?></td>
<td>
<?php
if($row['stock'] < 20){
    echo "<span class='low'>LOW</span>";
} else {
    echo "OK";
}
?>
</td>
</tr>
<?php } ?>

</table>

</div>

</body>
</html>