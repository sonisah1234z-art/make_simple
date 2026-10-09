<?php
$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'hospital_system';
$port = 3306;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("DATABASE CONNECTION FAILED: " . $conn->connect_error);
}

echo "DATABASE CONNECTION: OK<br>";
echo "DATABASE: " . $database . "<br>";
echo "PORT: " . $port . "<br>";

$result = $conn->query("SELECT DATABASE() AS db, @@port AS port");

if ($result) {
    $row = $result->fetch_assoc();
    echo "CONNECTED DATABASE: " . $row['db'] . "<br>";
    echo "CONNECTED PORT: " . $row['port'] . "<br>";
}

$result = $conn->query("SELECT COUNT(*) AS total FROM doctors");

if ($result) {
    $row = $result->fetch_assoc();
    echo "DOCTORS: " . $row['total'] . "<br>";
} else {
    echo "DOCTORS QUERY FAILED: " . $conn->error . "<br>";
}

$conn->close();
?>
