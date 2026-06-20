<?php
session_start();
require_once 'db.php';

if(!isset($_SESSION['patient_id']))
{
    die("Please login first.");
}

$patient_id = $_SESSION['patient_id'];

$result = $conn->query("
SELECT *
FROM discharge
WHERE patient_id='$patient_id'
ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>My Discharge Summary</title>

<style>
body{
    font-family:Arial;
    background:#f2f6ff;
}

.container{
    width:80%;
    margin:auto;
    margin-top:30px;
}

.card{
    background:white;
    padding:20px;
    margin-bottom:20px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
}
</style>
</head>
<body>

<div class="container">

<h2><img src="assets/icons/hospital.svg" class="icon">My Discharge Summary</h2>

<?php
if($result->num_rows > 0)
{
    while($row = $result->fetch_assoc())
    {
        echo "
        <div class='card'>
            <h3>Discharge Date: {$row['discharged_at']}</h3>
            <p>{$row['summary']}</p>
        </div>";
    }
}
else
{
    echo "<p>No discharge records found.</p>";
}
?>

</div>

</body>
</html>