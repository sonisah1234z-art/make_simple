<?php
session_start();
include 'db.php';

if(!isset($_SESSION['patient_id']))
{
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];

$result = $conn->query("
SELECT * FROM notifications
WHERE patient_id='$patient_id'
ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>My Notifications</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}

.container{
    width:80%;
    margin:auto;
    margin-top:30px;
}

.notification{
    background:white;
    padding:15px;
    margin-bottom:10px;
    border-left:5px solid #007bff;
    box-shadow:0 0 5px #ccc;
}
</style>

</head>
<body>

<div class="container">

<h2>🔔 My Notifications</h2>

<?php while($row = $result->fetch_assoc()) { ?>

<div class="notification">

<b><?php echo $row['message']; ?></b>

<br>

<small>
<?php echo $row['created_at']; ?>
</small>

</div>

<?php } ?>

</div>

</body>
</html>