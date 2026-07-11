<?php
$conn = new mysqli('localhost', 'root', '', 'hospital_system');
if ($conn->connect_error) {
    echo 'ERR:' . $conn->connect_error;
    exit(1);
}
$sql = "SELECT id, name, specialty, status, availability_status, department, available_slots FROM doctors LIMIT 50";
$res = $conn->query($sql);
if (!$res) {
    echo 'QUERY_ERR:' . $conn->error;
    exit(1);
}
echo "ALL_DOCTORS_COUNT:" . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['specialty'] . ' | ' . $row['status'] . ' | ' . $row['availability_status'] . ' | ' . $row['department'] . ' | ' . $row['available_slots'] . "\n";
}
$sql2 = "SELECT id, name, specialty, status, availability_status, department, available_slots FROM doctors WHERE (status = 'Available' OR availability_status = 'Available') ORDER BY name ASC";
$res2 = $conn->query($sql2);
if (!$res2) {
    echo 'QUERY2_ERR:' . $conn->error;
    exit(1);
}
echo "AVAILABLE_DOCTORS_COUNT:" . $res2->num_rows . "\n";
while ($row = $res2->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['specialty'] . ' | ' . $row['status'] . ' | ' . $row['availability_status'] . ' | ' . $row['department'] . ' | ' . $row['available_slots'] . "\n";
}
?>