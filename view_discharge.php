<?php
require_once 'auth.php';
require_once 'db.php';

if (isset($_SESSION['admin_id'])) {
    $result = $conn->query("SELECT d.*, p.name FROM discharge d LEFT JOIN patients p ON p.id = d.patient_id ORDER BY d.id ASC");
} elseif (isset($_SESSION['patient_id'])) {
    $patient_id = current_patient_id();
    $result = $conn->query("SELECT d.*, p.name FROM discharge d LEFT JOIN patients p ON p.id = d.patient_id WHERE d.patient_id = " . intval($patient_id) . " ORDER BY d.id ASC");
} else {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Discharge Records</title>

<style>
body{
    font-family:Arial, sans-serif;
    background:#f2f6ff;
    margin:0;
    padding:20px;
}

.container{
    width:95%;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.2);
}

h2{
    text-align:center;
    color:#2c7be5;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th{
    background:#2c7be5;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border:1px solid #ddd;
}

tr:nth-child(even){
    background:#f8f8f8;
}

.btn{
    display:inline-block;
    padding:10px 15px;
    background:#28a745;
    color:white;
    text-decoration:none;
    border-radius:5px;
    margin-bottom:15px;
}

.btn:hover{
    background:#218838;
}
</style>

</head>

<body>

<div class="container">

<h2><img src="assets/icons/hospital.svg" class="icon">Discharge Records</h2>

<a href="discharge.php" class="btn">
    <img src="assets/icons/plus.svg" class="icon">New Discharge
</a>

<table>

<tr>
    <th>ID</th>
    <th>Patient Name</th>
    <th>Discharge Summary</th>
    <th>Date</th>
</tr>

<?php
if($result && $result->num_rows > 0)
{
    while($row = $result->fetch_assoc())
    {
        $patient_name = !empty($row['name']) ? $row['name'] : 'Unknown Patient';

        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$patient_name}</td>
                <td>{$row['summary']}</td>
                <td>{$row['discharged_at']}</td>
              </tr>";
    }
}
else
{
    echo "
    <tr>
        <td colspan='4'>No discharge records found.</td>
    </tr>";
}
?>

</table>

</div>

</body>
</html>