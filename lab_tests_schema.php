<?php
require_once 'db.php';

$sql = "CREATE TABLE IF NOT EXISTS lab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    test_type VARCHAR(255) NOT NULL,
    result TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    report_date DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === false) {
    die('Failed to create lab_tests table: ' . $conn->error);
}

echo "lab_tests table ready.";
?>