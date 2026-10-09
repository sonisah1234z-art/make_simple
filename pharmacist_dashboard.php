<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'auth.php';
require_pharmacist();
include "db.php";
include_once "announcements.php";
ensure_medicines_table($conn);
ensure_medicine_orders_table($conn);sync_medicine_expiry_alerts($conn);

$acknowledgementMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_alert'], $_POST['alert_id'])) {
    $alertId = (int)$_POST['alert_id'];
    $comment = trim($_POST['comment'] ?? '');
    $pharmacistName = $_SESSION['pharmacist_name'] ?? $_SESSION['username'] ?? 'Pharmacist';
    $stmt = $conn->prepare("UPDATE medicine_expiry_alerts SET alert_status = 'Acknowledged', acknowledgement_comment = ?, acknowledged_by = ?, acknowledged_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ssi', $comment, $pharmacistName, $alertId);
        if ($stmt->execute()) {
            $acknowledgementMessage = 'Expiry alert acknowledged successfully.';
        } else {
            $acknowledgementMessage = 'Unable to acknowledge the expiry alert. Please try again.';
        }
        $stmt->close();
    }
}
function renderStockStatus($stock): string
{
    $stock = (int)($stock ?? 0);

    if ($stock > 0) {
        return "<span class='status-badge status-available'>Available</span>";
    }

    return "<span class='status-badge status-out'>Out of Stock</span>";
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
"SELECT * FROM medicines ORDER BY id ASC");

$searchTerm = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$filterSql = "WHERE 1=1";
if ($searchTerm !== '') {
    $escapedSearch = $conn->real_escape_string('%' . $searchTerm . '%');
    $filterSql .= " AND (m.medicine_name LIKE '$escapedSearch' OR m.category LIKE '$escapedSearch' OR mea.alert_message LIKE '$escapedSearch')";
}
if ($statusFilter === 'Pending' || $statusFilter === 'Acknowledged') {
    $filterSql .= " AND mea.alert_status = '$statusFilter'";
}

$expiryAlertsResult = $conn->query("SELECT mea.*, m.medicine_name, m.category, m.stock FROM medicine_expiry_alerts mea JOIN medicines m ON m.id = mea.medicine_id $filterSql ORDER BY FIELD(mea.alert_status, 'Pending', 'Acknowledged'), mea.expiry_date ASC");
$expiryAlertTotal = (int)($conn->query("SELECT COUNT(*) AS total FROM medicine_expiry_alerts")->fetch_assoc()['total'] ?? 0);
$expiryAlertPending = (int)($conn->query("SELECT COUNT(*) AS total FROM medicine_expiry_alerts WHERE alert_status = 'Pending'")->fetch_assoc()['total'] ?? 0);

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

.table-responsive{
    overflow-x:auto;
    margin-top:20px;
}

th,
td{
    padding:12px 14px;
    white-space:nowrap;
    vertical-align:middle;
}

th{
    background:#2c3e50;
    color:white;
    text-align:left;
}

td{
    text-align:left;
    border-bottom:1px solid #ddd;
}

td.center,
th.center {
    text-align:center;
}

@media (max-width: 767px) {
    th,
    td {
        padding:10px 12px;
    }
}

.status-badge{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:bold;
    text-transform:uppercase;
    letter-spacing:0.04em;
}

.status-available{
    background:#e8f8ef;
    color:#1f8f4d;
}

.status-out{
    background:#fdecea;
    color:#c0392b;
}

.low{
    color:red;
    font-weight:bold;
}
</style>
<link rel="stylesheet" href="assets/css/compact-ui.css">

</head>

<body>

<div class="sidebar">

<h2>Pharmacist</h2>

<a href="#">Dashboard</a>
<a href="add_medicine.php">Add Medicine</a>
<a href="medicine_orders.php">Orders</a>
<a href="leave_request.php">Leave</a>
<a href="leave_history.php">Leave History</a>
<a href="#">Reports</a>
<a href="about.php">About Us</a>

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

<div class="card">
<h3>Expiry Alerts</h3>
<p style="color:#d35400;"><?php echo $expiryAlertPending; ?> Pending</p>
<small><?php echo $expiryAlertTotal; ?> Total alerts</small>
</div>

</div>

<!-- ALERT BOX -->
<?php $context = get_current_user_context(); echo render_announcements_panel($conn, $context['role'], $context['id'], 3); ?>

<?php if ($acknowledgementMessage !== ''): ?>
<div class="alert-box" style="background:#e8f7ff;color:#055160;border-left-color:#2196f3;">
    <?php echo htmlspecialchars($acknowledgementMessage); ?>
</div>
<?php endif; ?>

<?php if($lowStock > 0){ ?>
<div class="alert-box">
    <img src="assets/icons/emergency.svg" class="icon"><strong>Warning!</strong> <?php echo $lowStock; ?> medicines are LOW IN STOCK. Please restock immediately.
</div>
<?php } ?>

<!-- EXPIRY ALERTS -->
<div class="alert-box" style="background:#fff4e5;color:#7a4f01;border-left-color:#f39c12;">
    <strong>Expiry Monitoring:</strong> <?php echo $expiryAlertPending; ?> pending expiry alert<?php echo $expiryAlertPending !== 1 ? 's' : ''; ?>. Use the table below to acknowledge alerts and track soon-expiring stock.
</div>

<div style="margin-top:20px; padding:20px; background:#fff; border-radius:12px; box-shadow:0 0 10px rgba(0,0,0,0.05);">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <h3 style="margin:0;">📌 Medicine Expiry Alerts</h3>
            <p style="margin:4px 0 0; color:#555;">Filter, review and acknowledge soon-to-expire medicines.</p>
        </div>
        <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="search" name="search" placeholder="Search name, category or alert" value="<?php echo htmlspecialchars($searchTerm); ?>" style="padding:10px 12px; border:1px solid #ccc; border-radius:8px; min-width:220px;">
            <select name="status" style="padding:10px 12px; border:1px solid #ccc; border-radius:8px;">
                <option value="">All Status</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Acknowledged" <?php echo $statusFilter === 'Acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
            </select>
            <button type="submit" style="padding:10px 16px; background:#2c3e50; color:white; border:none; border-radius:8px;">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table>
            <tr>
                <th class="center">#</th>
                <th>Medicine</th>
                <th>Category</th>
                <th class="center">Stock</th>
                <th class="center">Expiry</th>
                <th>Status</th>
                <th>Acknowledge</th>
            </tr>
            <?php
            $alertIndex = 1;
            if ($expiryAlertsResult && $expiryAlertsResult->num_rows > 0):
                while ($alert = $expiryAlertsResult->fetch_assoc()):
                    $statusClass = $alert['alert_status'] === 'Pending' ? 'status-out' : 'status-available';
            ?>
            <tr>
                <td class="center"><?php echo $alertIndex++; ?></td>
                <td><?php echo htmlspecialchars($alert['medicine_name']); ?></td>
                <td><?php echo htmlspecialchars($alert['category']); ?></td>
                <td class="center"><?php echo htmlspecialchars($alert['stock']); ?></td>
                <td class="center"><?php echo htmlspecialchars($alert['expiry_date']); ?></td>
                <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($alert['alert_status']); ?></span></td>
                <td>
                    <?php if ($alert['alert_status'] === 'Pending'): ?>
                        <form method="POST" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <input type="hidden" name="alert_id" value="<?php echo (int)$alert['id']; ?>">
                            <input type="text" name="comment" placeholder="Comment" style="padding:6px 10px; border:1px solid #ccc; border-radius:6px; min-width:140px;">
                            <button type="submit" name="acknowledge_alert" style="padding:8px 12px; background:#27ae60; color:white; border:none; border-radius:6px;">Acknowledge</button>
                        </form>
                    <?php else: ?>
                        <span style="font-size:0.9rem; color:#555;">Acknowledged</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="7" style="padding:24px; text-align:center; color:#666;">No expiry alerts found for the current filters.</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- LOW STOCK TABLE -->
<h3 style="margin-top:30px;"><img src="assets/icons/emergency.svg" class="icon">Low Stock Medicines</h3>

<div class="table-responsive">
<table>
<tr>
<th class="center">ID</th>
<th>Name</th>
<th class="center">Stock</th>
<th class="center">Price</th>
<th>Status</th>
</tr>

<?php
$low_meds = mysqli_query($conn,
"SELECT * FROM medicines WHERE stock < 20 ORDER BY id ASC");
$lowDisplayIndex = 1;

while($row = mysqli_fetch_assoc($low_meds)) {
?>
<tr>
<td class="center"><?php echo $lowDisplayIndex++; ?></td>
<td><?php echo $row['medicine_name']; ?></td>
<td class="center low"><?php echo $row['stock']; ?></td>
<td class="center">Rs. <?php echo number_format((float)($row['price'] ?? 0), 2); ?></td>
<td><?php echo renderStockStatus($row['stock']); ?></td>
</tr>
<?php } ?>
</table>
</div>

<!-- ALL MEDICINES -->
<h3 style="margin-top:30px;">📦 All Medicines</h3>

<div class="table-responsive">
<table>
<tr>
<th class="center">ID</th>
<th>Name</th>
<th>Category</th>
<th class="center">Stock</th>
<th class="center">Price</th>
<th>Status</th>
</tr>

<?php
$displayIndex = 1;
while($row = mysqli_fetch_assoc($medicine_result)) { ?>
<tr>
<td class="center"><?php echo $displayIndex++; ?></td>
<td><?php echo $row['medicine_name']; ?></td>
<td><?php echo $row['category']; ?></td>
<td class="center"><?php echo $row['stock']; ?></td>
<td class="center">Rs. <?php echo number_format((float)($row['price'] ?? 0), 2); ?></td>
<td><?php echo renderStockStatus($row['stock']); ?></td>
</tr>
<?php } ?>

</table>
</div>

</div>

</body>
</html>
