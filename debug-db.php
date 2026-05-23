<?php
include 'db.php';
echo "DB Connected\n";
$result = $conn->query('SHOW TABLES');
while($row = $result->fetch_array()) {
    echo $row[0] . "\n";
}
echo "\nBillings table check:\n";
$result = $conn->query('SELECT COUNT(*) as cnt FROM billings');
$row = $result->fetch_assoc();
echo "Billings records: " . $row['cnt'] . "\n";
?>