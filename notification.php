<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';

$patient_id = current_patient_id();

$result = $conn->query("SELECT * FROM notifications WHERE patient_id='" . intval($patient_id) . "' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>Notifications</title>

<style>
body{
    font-family:Arial;
    background:#f4f7fc;
}

.container{
    width:80%;
    margin:auto;
}

.card{
    background:white;
    padding:15px;
    margin:15px 0;
    border-radius:10px;
    box-shadow:0 0 10px #ddd;
}
</style>
</head>
<body>

<div class="container">

<h2><img src="assets/icons/bell.svg" class="icon">Notifications</h2>

<?php
while($row = $result->fetch_assoc())
{
    echo "
    <div class='card'>
        <h3>{$row['title']}</h3>
        <p>{$row['message']}</p>
        <small>{$row['created_at']}</small>
    </div>
    ";
}
?>

</div>

</body>
</html>