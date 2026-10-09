<?php
if (!function_exists('ensure_billing_schema')) {
    function ensure_billing_schema(mysqli $conn): void
    {
        ensure_db_connection($conn);

        $conn->query("CREATE TABLE IF NOT EXISTS billings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NULL,
            patient_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            description VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'Pending',
            bill_number VARCHAR(50) DEFAULT NULL,
            consultation_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            lab_test_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            medicine_charges DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            room_charges DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            other_charges DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            discount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            tax DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            remaining_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(50) DEFAULT NULL,
            payment_status VARCHAR(50) DEFAULT 'Unpaid',
            billing_date DATE DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $columnsToAdd = [
            'bill_number' => "ALTER TABLE billings ADD COLUMN bill_number VARCHAR(50) DEFAULT NULL",
            'consultation_fee' => "ALTER TABLE billings ADD COLUMN consultation_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'lab_test_fee' => "ALTER TABLE billings ADD COLUMN lab_test_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'medicine_charges' => "ALTER TABLE billings ADD COLUMN medicine_charges DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'room_charges' => "ALTER TABLE billings ADD COLUMN room_charges DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'other_charges' => "ALTER TABLE billings ADD COLUMN other_charges DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'discount' => "ALTER TABLE billings ADD COLUMN discount DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'tax' => "ALTER TABLE billings ADD COLUMN tax DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'grand_total' => "ALTER TABLE billings ADD COLUMN grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'amount_paid' => "ALTER TABLE billings ADD COLUMN amount_paid DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'remaining_balance' => "ALTER TABLE billings ADD COLUMN remaining_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00",
            'payment_method' => "ALTER TABLE billings ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL",
            'payment_status' => "ALTER TABLE billings ADD COLUMN payment_status VARCHAR(50) DEFAULT 'Unpaid'",
            'billing_date' => "ALTER TABLE billings ADD COLUMN billing_date DATE DEFAULT NULL",
            'updated_at' => "ALTER TABLE billings ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($columnsToAdd as $column => $sql) {
            $check = $conn->query("SHOW COLUMNS FROM billings LIKE '$column'");
            if ($check && $check->num_rows === 0) {
                $conn->query($sql);
            }
        }

        ensure_bill_numbers($conn);
    }

    function ensure_bill_numbers(mysqli $conn): void
    {
        $stmt = $conn->prepare('SELECT id, bill_number FROM billings WHERE bill_number IS NULL OR TRIM(bill_number) = "" ORDER BY id');
        if (!$stmt) {
            return;
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $missingRows = [];
        while ($row = $result->fetch_assoc()) {
            $missingRows[] = (int)$row['id'];
        }
        $stmt->close();

        foreach ($missingRows as $billingId) {
            $billNumber = generate_bill_number($conn);
            $updateStmt = $conn->prepare('UPDATE billings SET bill_number = ? WHERE id = ?');
            if ($updateStmt) {
                $updateStmt->bind_param('si', $billNumber, $billingId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    }

    function calculate_billing_totals(array $values): array
    {
        $consultationFee = (float)($values['consultation_fee'] ?? 0);
        $labTestFee = (float)($values['lab_test_fee'] ?? 0);
        $medicineCharges = (float)($values['medicine_charges'] ?? 0);
        $roomCharges = (float)($values['room_charges'] ?? 0);
        $otherCharges = (float)($values['other_charges'] ?? 0);
        $discount = (float)($values['discount'] ?? 0);
        $tax = (float)($values['tax'] ?? 0);
        $amountPaid = (float)($values['amount_paid'] ?? 0);

        $subTotal = $consultationFee + $labTestFee + $medicineCharges + $roomCharges + $otherCharges;
        $grandTotal = max(0, $subTotal - $discount + $tax);
        $remainingBalance = max(0, $grandTotal - $amountPaid);

        return [
            'grand_total' => round($grandTotal, 2),
            'remaining_balance' => round($remainingBalance, 2),
            'amount_paid' => round($amountPaid, 2),
        ];
    }

    function format_currency($value): string
    {
        return number_format((float)$value, 2);
    }

    function generate_bill_number(mysqli $conn): string
    {
        $stmt = $conn->prepare('SELECT bill_number FROM billings WHERE bill_number IS NOT NULL AND TRIM(bill_number) <> ""');
        if (!$stmt) {
            return 'INV-000001';
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $usedSequences = [];
        while ($row = $result->fetch_assoc()) {
            $billNumber = trim((string)($row['bill_number'] ?? ''));
            if ($billNumber === '') {
                continue;
            }

            if (preg_match('/^INV-(\d{6})$/', $billNumber, $matches)) {
                $usedSequences[] = (int)$matches[1];
            } elseif (preg_match('/^INV-(\d{8})-(\d{6})$/', $billNumber, $matches)) {
                $usedSequences[] = (int)$matches[2];
            }
        }
        $stmt->close();

        $sequence = 1;
        while (in_array($sequence, $usedSequences, true)) {
            $sequence++;
        }

        return 'INV-' . str_pad((string)$sequence, 6, '0', STR_PAD_LEFT);
    }
}
?>
