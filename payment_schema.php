<?php
function ensure_payments_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        patient_name VARCHAR(255) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_patient_id (patient_id)
    )");
}
?>
