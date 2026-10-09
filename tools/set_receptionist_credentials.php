<?php
require_once __DIR__ . '/..//db.php';

$desiredUsername = 'receptionist';
$plainPassword = 'reception123';
$hash = password_hash($plainPassword, PASSWORD_DEFAULT);

if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "No DB connection available.\n");
    exit(1);
}

// Ensure receptionists table exists (ensure_auth_schema usually does this)
$conn->query("CREATE TABLE IF NOT EXISTS receptionists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Try to update an existing receptionist (prefer id=1), else insert
$res = $conn->query("SELECT id FROM receptionists ORDER BY id ASC LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $id = (int)$row['id'];
    $stmt = $conn->prepare("UPDATE receptionists SET username = ?, password = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('ssi', $desiredUsername, $hash, $id);
        if ($stmt->execute()) {
            echo "Updated receptionist id={$id} username={$desiredUsername}\n";
        } else {
            fwrite(STDERR, "Failed to update receptionist: " . $stmt->error . "\n");
            exit(2);
        }
        $stmt->close();
    } else {
        fwrite(STDERR, "Prepare failed: " . $conn->error . "\n");
        exit(3);
    }
} else {
    $stmt = $conn->prepare("INSERT INTO receptionists (name, username, password) VALUES ('Hospital Receptionist', ?, ?)");
    if ($stmt) {
        $stmt->bind_param('ss', $desiredUsername, $hash);
        if ($stmt->execute()) {
            echo "Inserted receptionist id=" . $stmt->insert_id . " username={$desiredUsername}\n";
        } else {
            fwrite(STDERR, "Failed to insert receptionist: " . $stmt->error . "\n");
            exit(4);
        }
        $stmt->close();
    } else {
        fwrite(STDERR, "Prepare failed: " . $conn->error . "\n");
        exit(5);
    }
}

echo "Done. You can now login with username='{$desiredUsername}' and password='{$plainPassword}'.\n";
