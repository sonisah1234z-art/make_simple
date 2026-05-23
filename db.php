<?php
// Database connection settings for local development.
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hospital_system';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?>
