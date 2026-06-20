<?php
session_start();
include 'db.php';

/* Access control:
   - If the user is not authenticated at all, redirect to login.
   - If the user is authenticated but not an admin, show a friendly forbidden message.
*/
if(!isset($_SESSION['role']) && !isset($_SESSION['patient_id'])){
    header('Location: login.php');
    exit();
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
    // Authenticated but not admin: show friendly access denied page
    ?><!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Access Denied</title>
    <style>body{font-family:Arial;background:#f8fafc;color:#334155;padding:30px} .card{max-width:700px;margin:40px auto;background:white;padding:24px;border-radius:12px;box-shadow:0 10px 30px rgba(2,6,23,0.08)} a{color:#2563eb;text-decoration:none}</style>
    </head><body>
    <div class="card">
        <h2>Access Restricted</h2>
        <p>You do not have permission to view patient feedback. This area is restricted to administrators only.</p>
        <p><a href="dashboard.php">Return to your dashboard</a> or contact an administrator for access.</p>
    </div>
    </body></html><?php
    exit();
}

/* FETCH FEEDBACK */
 $result = $conn->query("SELECT * FROM feedback ORDER BY id DESC");
 if($result === false){
     error_log('Feedback query error: ' . $conn->error);
     die('Unable to fetch feedback records.');
 }
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Feedback</title>

<style>
body{
    font-family:Arial;
    background:#f4f6ff;
    padding:20px;
}

h2{
    text-align:center;
    color:#333;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:white;
    border-radius:10px;
    overflow:hidden;
}

th{
    background:#2c7be5;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:center;
}

tr:hover{
    background:#f1f1f1;
}

.rating{
    font-weight:bold;
    color:#ff9800;
}
</style>
</head>

<body>

<h2><img src="assets/icons/star.svg" class="icon">Patient Feedback Records</h2>

<?php if($result->num_rows > 0): ?>
<table>
    <tr>
        <th>ID</th>
        <th>Patient Name</th>
        <th>Rating</th>
        <th>Comments</th>
        <th>Date</th>
    </tr>

<?php while($row = $result->fetch_assoc()) { ?>
    <tr>
        <td><?php echo (int)$row['id']; ?></td>
        <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
        <td class="rating">
            <img src="assets/icons/star.svg" class="icon" style="width:16px;height:16px;"> <?php echo htmlspecialchars($row['rating']); ?>/5
        </td>
        <td><?php echo nl2br(htmlspecialchars($row['comments'])); ?></td>
        <td><?php echo htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
    </tr>
<?php } ?>

</table>
<?php else: ?>
    <div style="text-align:center; margin-top:18px; color:#6f7c98;">
        <strong>No feedback records found.</strong>
    </div>
<?php endif; ?>

</body>
</html>