<?php
include "config.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}
?>

<!DOCTYPE html>

<html>

<head>

<title>Hospital Announcements</title>

<style>

body{

background:#eef2f5;
font-family:Arial;

}

.container{

width:800px;
margin:auto;

}

.box{

background:white;
margin:20px 0;
padding:20px;
border-radius:8px;
box-shadow:0 0 5px #ccc;

}

.date{

color:gray;
font-size:13px;

}

</style>

</head>

<body>

<div class="container">

<h1>📢 Hospital Announcements</h1>

<?php
$result = $conn->query("SELECT * FROM announcements ORDER BY id DESC");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
?>

<div class="box">

<h2><?php echo $row['title']; ?></h2>

<p><?php echo nl2br($row['message']); ?></p>

<div class="date">

Posted:
<?php echo $row['created_at']; ?>

</div>

</div>

<?php
    }
} else {
?>
<div class="box">
    <h2>No announcements yet</h2>
    <p>There are currently no hospital announcements to display.</p>
</div>
<?php
}
?>

</div>

</body>

</html>