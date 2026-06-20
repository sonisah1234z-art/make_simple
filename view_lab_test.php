<?php
require_once 'auth.php';
require_once 'db.php';

if (isset($_SESSION['admin_id'])) {
    // Admin: show all lab tests
    $result = $conn->query("SELECT l.*, p.name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id ORDER BY l.id DESC");
} elseif (isset($_SESSION['patient_id'])) {
    // Patient: show only own lab tests
    $patient_id = current_patient_id();
    $result = $conn->query("SELECT l.*, p.name FROM lab_tests l LEFT JOIN patients p ON p.id = l.patient_id WHERE l.patient_id = " . intval($patient_id) . " ORDER BY l.id DESC");
} else {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Lab Reports</title>

<style>
body{
    font-family:Arial;
    background:#f2f6ff;
}

.container{
    width:95%;
    margin:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th{
    background:#2c7be5;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    text-align:center;
}

img{
    width:80px;
    height:80px;
}
</style>
</head>

<body>

<div class="container">

<h2 style="text-align:center;"><img src="assets/icons/hospital.svg" class="icon">Lab Test Reports</h2>

<table border="1">

<tr>
<th>ID</th>
<th>Patient</th>
<th>Test</th>
<th>Result</th>
<th>Image</th>
<th>Date</th>
</tr>

<?php
while($row = $result->fetch_assoc())
{
    echo "<tr>
        <td>{$row['id']}</td>
        <td>{$row['name']}</td>
        <td>{$row['test_type']}</td>
        <td>{$row['result']}</td>
        <td>";

    if(!empty($row['image'])){
        echo "<img src='uploads/{$row['image']}'>";
    } else {
        echo "No Image";
    }

    echo "</td>
        <td>{$row['report_date']}</td>
    </tr>";
}
?>

</table>

</div>

</body>
</html>