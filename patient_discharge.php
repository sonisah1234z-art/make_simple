<?php
require_once 'auth.php';
require_patient();
require_once 'db.php';

$patient_id = current_patient_id();

$result = $conn->query("SELECT * FROM discharge WHERE patient_id='" . intval($patient_id) . "' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
<title>My Discharge Summary</title>

<style>
body{
    font-family:Arial;
    background:#f2f6ff;
    padding:20px;
}

.card{
    background:white;
    padding:20px;
    margin:20px auto;
    width:80%;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
}

h2{
    text-align:center;
    color:#2c7be5;
}
</style>

</head>
<body>

<h2><img src="assets/icons/hospital.svg" class="icon">My Discharge Summary</h2>

<?php

if($result && $result->num_rows > 0)
{
    while($row = $result->fetch_assoc())
    {
        echo "
        <div class='card'>
            <h3>Discharge Date: {$row['discharged_at']}</h3>
            <p>{$row['summary']}</p>
        </div>
        ";
    }
}
else
{
    echo "<h3 style='text-align:center;'>No discharge records found.</h3>";
}

?>

</body>
</html>