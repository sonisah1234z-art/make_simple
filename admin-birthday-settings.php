<?php
require_once 'auth.php';
require_admin();
require_once 'db.php';

if (!function_exists('get_system_setting')) {
    function get_system_setting(mysqli $conn, string $key, string $default = ''): string {
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        if (!$stmt) {
            return $default;
        }

        $stmt->bind_param('s', $key);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row['setting_value'] ?? $default;
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting(mysqli $conn, string $key, string $value): bool {
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $key, $value);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

$message = '';
$error = '';

$enabled = get_system_setting($conn, 'birthday_wishes_enabled', '1') === '1';
$birthdayMessage = get_system_setting($conn, 'birthday_wishes_message', 'Happy Birthday {name}! Wishing you a healthy and joyful year ahead from all of us at the hospital.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $birthdayMessage = trim($_POST['birthday_message'] ?? '');

    if ($birthdayMessage === '') {
        $error = 'The birthday message cannot be empty.';
    } else {
        $savedEnabled = set_system_setting($conn, 'birthday_wishes_enabled', $enabled ? '1' : '0');
        $savedMessage = set_system_setting($conn, 'birthday_wishes_message', $birthdayMessage);

        if ($savedEnabled && $savedMessage) {
            $message = 'Birthday wishes settings have been updated successfully.';
        } else {
            $error = 'Unable to save settings. Please try again.';
        }
    }
}
?>
<?php include 'header.php'; ?>
    <div class="container-fluid py-4">
        <div class="card rounded-4 shadow-sm p-4" style="max-width: 980px; margin: 0 auto;">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="h3">Birthday Wishes Settings</h1>
                    <p class="text-muted mb-0">Enable or disable the birthday greeting feature and customize the message shown to patients on their birthday.</p>
                </div>
                <a class="btn btn-outline-secondary" href="admin-dashboard.php">Back to Dashboard</a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="admin-birthday-settings.php">
                <div class="mb-4">
                    <label class="form-label">Enable Birthday Wishes</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enabled">Show birthday greetings on patient dashboards and send email wishes when configured</label>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="birthday_message">Birthday Message Template</label>
                    <textarea class="form-control" id="birthday_message" name="birthday_message" rows="8" required><?php echo htmlspecialchars($birthdayMessage); ?></textarea>
                    <div class="form-text">Use <code>{name}</code> to insert the patient's name automatically.</div>
                </div>

                <div class="mb-4 p-3 rounded-3 bg-light border">
                    <div class="text-muted"><strong>Note:</strong> If email settings are configured in <code>send_email.php</code>, the birthday wish email will be sent automatically once per year.</div>
                </div>

                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
<?php include 'footer.php'; ?>
