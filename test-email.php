<?php
require_once 'send_email.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Test email from Hospital Management System');
    $message = trim($_POST['message'] ?? 'This is a test email from the Hospital Management System.');

    if ($to === '') {
        $status = 'Please provide a recipient email address.';
    } else {
        $result = sendEmail($to, $subject, '<p>' . htmlspecialchars($message) . '</p>');
        $status = $result ? 'Email sent successfully.' : 'Email delivery failed. Check the server error log for details.';
    }
} else {
    $status = 'Submit the form to send a test email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMTP Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; line-height: 1.5; }
        form { display: grid; gap: 12px; }
        input, textarea, button { padding: 10px; font-size: 14px; }
        .status { margin-top: 16px; padding: 12px; border-radius: 6px; background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>SMTP Test</h1>
    <p>Use this page to verify that the configured SMTP credentials can send mail.</p>
    <form method="post">
        <input type="email" name="to" placeholder="Recipient email" required>
        <input type="text" name="subject" placeholder="Subject" value="SMTP test from Hospital Management System">
        <textarea name="message" rows="5" placeholder="Message body">This is a test email from the Hospital Management System.</textarea>
        <button type="submit">Send Test Email</button>
    </form>
    <?php if (!empty($status)): ?>
        <div class="status"><?php echo htmlspecialchars($status); ?></div>
    <?php endif; ?>
</body>
</html>
