<?php
/**
 * Email Sending Utility
 * Handles email sending for hospital notifications including OTP delivery
 */

require_once __DIR__ . '/smtp-config.php';

// SMTP Configuration
$envSmtpHost = getenv('SMTP_HOST') ?: (isset($_ENV['SMTP_HOST']) ? $_ENV['SMTP_HOST'] : 'smtp.gmail.com');
$envSmtpPort = getenv('SMTP_PORT') ?: (isset($_ENV['SMTP_PORT']) ? $_ENV['SMTP_PORT'] : 587);
$envSmtpUsername = getenv('SMTP_USERNAME') ?: (isset($_ENV['SMTP_USERNAME']) ? $_ENV['SMTP_USERNAME'] : 'your-email@gmail.com');
$envSmtpPassword = getenv('SMTP_PASSWORD') ?: (isset($_ENV['SMTP_PASSWORD']) ? $_ENV['SMTP_PASSWORD'] : 'your-app-password');
$envSenderEmail = getenv('SENDER_EMAIL') ?: (isset($_ENV['SENDER_EMAIL']) ? $_ENV['SENDER_EMAIL'] : 'your-email@gmail.com');
$envSenderName = getenv('SENDER_NAME') ?: (isset($_ENV['SENDER_NAME']) ? $_ENV['SENDER_NAME'] : 'Hospital Management System');
$envSmtpEncryption = getenv('SMTP_ENCRYPTION') ?: (isset($_ENV['SMTP_ENCRYPTION']) ? $_ENV['SMTP_ENCRYPTION'] : 'tls');

define('SMTP_HOST', $envSmtpHost);
define('SMTP_PORT', (int)$envSmtpPort);
define('SMTP_USERNAME', $envSmtpUsername);
define('SMTP_PASSWORD', $envSmtpPassword);
define('SENDER_EMAIL', $envSenderEmail);
define('SENDER_NAME', $envSenderName);
define('SMTP_ENCRYPTION', $envSmtpEncryption);

/**
 * Send email using PHPMailer (if available) or PHP mail() function
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email HTML body
 * @param array $attachments Optional array of file paths to attach
 * @return bool True if email sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body, $attachments = []) {
    $recipient = is_string($to) ? trim($to) : '';
    if ($recipient === '') {
        error_log('Email delivery skipped because no recipient email address was provided.');
        return false;
    }

    $smtpConfigured = !empty(SMTP_HOST)
        && !empty(SMTP_USERNAME)
        && !empty(SMTP_PASSWORD)
        && !empty(SENDER_EMAIL)
        && strpos(strtolower((string)SMTP_USERNAME), 'your-email') === false
        && strpos(strtolower((string)SMTP_USERNAME), 'your-real-email') === false
        && strpos(strtolower((string)SMTP_PASSWORD), 'your-app-password') === false
        && strpos(strtolower((string)SMTP_PASSWORD), 'your-real-app-password') === false
        && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;

    if ($smtpConfigured && file_exists(__DIR__ . '/PHPMailer-master/src/PHPMailer.php')) {
        $sent = sendEmailWithPHPMailer($recipient, $subject, $body, $attachments);
        if ($sent) {
            return true;
        }
    }

    if (function_exists('mail')) {
        return sendEmailWithPhpMail($recipient, $subject, $body);
    }

    error_log('Email delivery failed because neither SMTP nor PHP mail() is available for ' . $recipient . '.');
    return false;
}

/**
 * Send email using PHPMailer (recommended for production)
 */
function sendEmailWithPHPMailer($to, $subject, $body, $attachments = []) {
    try {
        $autoloadPath = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
        if (!file_exists($autoloadPath)) {
            error_log('PHPMailer source files are missing.');
            return false;
        }

        if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD) || empty(SENDER_EMAIL)) {
            error_log('SMTP configuration is incomplete.');
            return false;
        }

        require_once $autoloadPath;
        require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
        require_once __DIR__ . '/PHPMailer-master/src/Exception.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer();

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPAutoTLS = true;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($to);
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Add attachments if provided
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                $mail->addAttachment($file);
            }
        }

        $sent = $mail->send();
        if (!$sent) {
            error_log('PHPMailer delivery failed for ' . $to . ': ' . $mail->ErrorInfo);
        }
        return $sent;
    } catch (Exception $e) {
        error_log('PHPMailer Error for ' . $to . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHP mail function (fallback for development)
 * Note: This method is less reliable and should be used for development only
 */
function sendEmailWithPhpMail($to, $subject, $body) {
    if (!function_exists('mail')) {
        error_log('PHP mail() is unavailable.');
        return false;
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SENDER_NAME . " <" . SENDER_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SENDER_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    if (stripos(PHP_OS, 'WIN') === 0 && ini_get('SMTP') === '' && ini_get('smtp_port') === '') {
        error_log('PHP mail() fallback skipped because php.ini SMTP and smtp_port are not configured on Windows.');
        return false;
    }

    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        error_log('PHP mail() delivery failed for ' . $to . '.');
    }
    return $sent;
}

/**
 * Generate OTP email template
 * 
 * @param string $patientName Patient's name
 * @param string $doctorName Doctor's name
 * @param string $department Department name
 * @param string $appointmentDate Date of appointment
 * @param string $appointmentTime Time of appointment
 * @param string $otp The OTP code
 * @return string HTML email body
 */
function getOTPEmailTemplate($patientName, $doctorName, $department, $appointmentDate, $appointmentTime, $otp) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; background: #f9fafb; padding: 20px; border-radius: 8px; }
            .header { background: linear-gradient(135deg, #60a5fa, #2563eb); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
            .content { background: white; padding: 30px; }
            .otp-box { background: #f0f9ff; border-left: 4px solid #2563eb; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .otp-code { font-size: 32px; font-weight: bold; color: #2563eb; letter-spacing: 3px; text-align: center; }
            .details { margin: 20px 0; }
            .detail-item { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
            .detail-label { color: #6b7280; font-weight: 500; }
            .detail-value { color: #111827; margin-top: 5px; }
            .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; color: #92400e; }
            .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Appointment Verification OTP</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($patientName) . "</strong>,</p>
                
                <p>Your appointment has been <strong style='color: #22c55e;'>APPROVED</strong> by our hospital. Your verification OTP has been generated below.</p>
                
                <div class='otp-box'>
                    <p style='margin: 0 0 10px 0; color: #6b7280;'>Your Verification OTP:</p>
                    <div class='otp-code'>" . htmlspecialchars($otp) . "</div>
                    <p style='margin: 10px 0 0 0; color: #6b7280; text-align: center; font-size: 12px;'>(Valid for 24 hours)</p>
                </div>
                
                <h3 style='color: #111827; margin-top: 30px;'>Appointment Details:</h3>
                <div class='details'>
                    <div class='detail-item'>
                        <div class='detail-label'>Doctor:</div>
                        <div class='detail-value'>" . htmlspecialchars($doctorName) . "</div>
                    </div>
                    <div class='detail-item'>
                        <div class='detail-label'>Department:</div>
                        <div class='detail-value'>" . htmlspecialchars($department) . "</div>
                    </div>
                    <div class='detail-item'>
                        <div class='detail-label'>Date:</div>
                        <div class='detail-value'>" . htmlspecialchars($appointmentDate) . "</div>
                    </div>
                    <div class='detail-item'>
                        <div class='detail-label'>Time:</div>
                        <div class='detail-value'>" . htmlspecialchars($appointmentTime) . "</div>
                    </div>
                </div>
                
                <div class='warning'>
                    <strong>Important:</strong> Please keep this OTP confidential. When you arrive at the hospital for your appointment, present this OTP to our reception staff for verification.
                </div>
                
                <p style='color: #6b7280; margin-top: 20px;'>If you did not request this appointment or have any concerns, please contact our hospital immediately.</p>
                
                <p style='color: #6b7280;'>Best regards,<br><strong>Hospital Management System</strong></p>
                
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " Hospital Management System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Generate OTP verification notification email
 */
function getOTPVerificationEmailTemplate($patientName, $verificationTime) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; background: #f9fafb; padding: 20px; border-radius: 8px; }
            .header { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
            .content { background: white; padding: 30px; }
            .success-box { background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 20px 0; border-radius: 4px; color: #166534; }
            .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✓ Appointment Verified</h1>
            </div>
            <div class='content'>
                <p>Dear <strong>" . htmlspecialchars($patientName) . "</strong>,</p>
                
                <div class='success-box'>
                    <p style='margin: 0;'><strong>Your OTP has been successfully verified!</strong></p>
                    <p style='margin: 10px 0 0 0; font-size: 14px;'>Verification Time: " . htmlspecialchars($verificationTime) . "</p>
                </div>
                
                <p>Your appointment has been verified by our hospital staff. You are all set for your consultation.</p>
                
                <p>If you have any questions or need to reschedule, please contact our reception desk.</p>
                
                <p style='color: #6b7280;'>Best regards,<br><strong>Hospital Management System</strong></p>
                
                <div class='footer'>
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; " . date('Y') . " Hospital Management System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

?>
