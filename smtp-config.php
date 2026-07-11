<?php
/**
 * Secure SMTP configuration for PHPMailer-based email delivery.
 *
 * Copy this file to smtp-config.local.php and replace the placeholder values
 * with your real SMTP credentials. The application will use the local file
 * first when present.
 */

if (file_exists(__DIR__ . '/smtp-config.local.php')) {
    require_once __DIR__ . '/smtp-config.local.php';
} else {
    putenv('SMTP_HOST=smtp.gmail.com');
    putenv('SMTP_PORT=587');
    putenv('SMTP_USERNAME=your-email@gmail.com');
    putenv('SMTP_PASSWORD=your-app-password');
    putenv('SENDER_EMAIL=your-email@gmail.com');
    putenv('SENDER_NAME=Hospital Management System');
}
