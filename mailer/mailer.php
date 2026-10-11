<?php
/**
 * mailer.php - PHPMailer configuration and email sending functions
 * Supports: automated notifications, manual admin emails, payment receipts
 */

// Include PHPMailer using __DIR__ to ensure correct paths
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load email configuration from a secure file outside web root
$emailConfig = __DIR__ . '/../config/email_config.php';
if (!file_exists($emailConfig)) {
    // Fallback: define defaults but ideally this file should exist
    define('SMTP_HOST', 'smtp.hostinger.com');
    define('SMTP_USERNAME', 'info@perfectinstitute.in');
    define('SMTP_PASSWORD', ''); // MUST be set in config file
    define('SMTP_PORT', 465);
    define('SMTP_SECURE', PHPMailer::ENCRYPTION_SMTPS);
    define('MAIL_FROM', 'info@perfectinstitute.in');
    define('MAIL_FROM_NAME', 'Perfect Institute of Languages');
} else {
    require_once $emailConfig;
    // The config file should define the above constants.
    // Example content:
    // <?php
    // define('SMTP_HOST', 'smtp.hostinger.com');
    // define('SMTP_USERNAME', 'info@perfectinstitute.in');
    // define('SMTP_PASSWORD', 'your_secure_password');
    // define('SMTP_PORT', 465);
    // define('SMTP_SECURE', PHPMailer::ENCRYPTION_SMTPS);
    // define('MAIL_FROM', 'info@perfectinstitute.in');
    // define('MAIL_FROM_NAME', 'Perfect Institute of Languages');
}

// Optional: Log file for email errors
define('EMAIL_LOG_FILE', __DIR__ . '/../logs/email_errors.log');

/**
 * Send an email using SMTP
 *
 * @param string $to      Recipient email address
 * @param string $name    Recipient name (for personalization)
 * @param string $subject Email subject
 * @param string $body    HTML email body
 * @param string $altBody Plain text alternative (optional)
 * @return bool           True on success, false on failure
 */
function sendEmail($to, $name, $subject, $body, $altBody = '') {
    // Basic validation
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError("Invalid email address: $to");
        return false;
    }

    // Check if SMTP password is configured
    if (empty(SMTP_PASSWORD)) {
        logEmailError("SMTP password not configured. Please set it in config/email_config.php");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Anti-spam headers
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:' . MAIL_FROM . '?subject=unsubscribe>');
        $mail->addCustomHeader('Precedence', 'bulk');
        $mail->XMailer = 'Perfect Institute Mailer';

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        logEmailActivity("Email sent to $to ($name) - Subject: $subject");
        return true;
    } catch (Exception $e) {
        logEmailError("Mailer Error (to $to): " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send a manual payment confirmation email (triggered from admin panel)
 *
 * @param int|string $payment_id The payment record ID
 * @param mysqli     $conn       Database connection (optional, will create if not provided)
 * @return bool
 */
function sendManualPaymentEmail($payment_id, $conn = null) {
    // If no connection provided, try to create one
    if ($conn === null) {
        require_once __DIR__ . '/../config/db_connect.php';
        $db = new Database();
        $conn = $db->getConnection();
    }

    // Fetch payment details with student info
    $stmt = $conn->prepare("
        SELECT p.*, r.full_name, r.email, r.registration_id, r.course_name
        FROM payments p
        JOIN registrations r ON p.registration_id = r.registration_id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        logEmailError("Payment ID $payment_id not found for manual email.");
        return false;
    }

    $extra = [
        'reg_id' => $payment['registration_id'],
        'course' => $payment['course_name'],
        'amount' => $payment['amount']
    ];

    $subject = "Payment Confirmation – Perfect Institute (Ref: {$payment['registration_id']})";
    $body = buildEmailBody($payment['full_name'], 'payment_received', $extra);
    $altBody = "Your payment of ₹{$payment['amount']} has been received. Registration ID: {$payment['registration_id']}.";

    return sendEmail($payment['email'], $payment['full_name'], $subject, $body, $altBody);
}

/**
 * Send a custom email from admin panel
 *
 * @param string $to       Recipient email
 * @param string $name     Recipient name
 * @param string $subject  Subject line
 * @param string $message  HTML message content
 * @return bool
 */
function sendCustomEmail($to, $name, $subject, $message) {
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f8; }
            .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 12px; padding: 30px; }
            .header { background: linear-gradient(135deg, #0a192f 0%, #1a365d 100%); color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .footer { margin-top: 30px; font-size: 13px; color: #666; text-align: center; border-top: 1px solid #e5e5e5; padding-top: 20px; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h2>Perfect Institute of Languages</h2>
            </div>
            ' . $message . '
            <div class="footer">
                <p>Perfect Institute of Languages<br>Call/WhatsApp: +919805962233<br>Email: info@perfectinstitute.in</p>
            </div>
        </div>
    </body>
    </html>';

    return sendEmail($to, $name, $subject, $body, strip_tags($message));
}

/**
 * Helper to create a nice HTML email template for automated emails
 *
 * @param string $name  Recipient name
 * @param string $type  Type: 'submitted', 'approved', 'rejected', 'payment_received'
 * @param array  $extra Optional data (reg_id, course, amount, reason)
 * @return string       HTML email body
 */
function buildEmailBody($name, $type, $extra = []) {
    $header = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f4f6f8;
                margin: 0;
                padding: 0;
            }
            .email-container {
                max-width: 600px;
                margin: 20px auto;
                background-color: #ffffff;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            }
            .header {
                background: linear-gradient(135deg, #0a192f 0%, #1a365d 100%);
                color: #ffffff;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 26px;
                font-weight: 600;
            }
            .content {
                padding: 35px 25px;
                color: #333333;
                line-height: 1.6;
            }
            .content h2 {
                color: #0a192f;
                border-left: 5px solid #e63946;
                padding-left: 12px;
                font-size: 20px;
            }
            .info-box {
                background-color: #f0f7ff;
                border: 1px solid #b8d1f3;
                padding: 12px 18px;
                border-radius: 8px;
                margin: 18px 0;
            }
            .reason-box {
                background-color: #fff3f3;
                border-left: 4px solid #e63946;
                padding: 12px 18px;
                margin: 18px 0;
                border-radius: 6px;
            }
            .footer {
                background-color: #f8f9fa;
                text-align: center;
                padding: 20px;
                font-size: 13px;
                color: #666666;
                border-top: 1px solid #e5e5e5;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>Perfect Institute of Languages</h1>
            </div>
            <div class="content">
    ';

    $footer = '
            </div>
            <div class="footer">
                <p>Perfect Institute of Languages</p>
                <p>Call/WhatsApp: +919805962233</p>
                <p>Email: info@perfectinstitute.in</p>
                <p>&copy; ' . date('Y') . ' Perfect Institute. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    $infoBox = '';
    if (!empty($extra['reg_id']) || !empty($extra['course']) || !empty($extra['amount'])) {
        $infoBox .= '<div class="info-box">';
        if (!empty($extra['reg_id'])) {
            $infoBox .= '<p><strong>Registration ID:</strong> ' . htmlspecialchars($extra['reg_id']) . '</p>';
        }
        if (!empty($extra['course'])) {
            $infoBox .= '<p><strong>Course:</strong> ' . htmlspecialchars($extra['course']) . '</p>';
        }
        if (!empty($extra['amount'])) {
            $infoBox .= '<p><strong>Amount Paid:</strong> ₹' . number_format($extra['amount'], 2) . '</p>';
        }
        $infoBox .= '</div>';
    }

    switch ($type) {
        case 'submitted':
            $bodyContent = '
                <h2>Thank You for Registering</h2>
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your admission form has been successfully received.</p>
                ' . $infoBox . '
                <p>Your application details are currently under review.</p>
                <p>You will receive another update shortly.</p>
                <br>
                <p>Best Regards,<br><strong>Perfect Institute Team</strong></p>
            ';
            break;
        case 'approved':
            $bodyContent = '
                <h2>Admission Confirmed</h2>
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your admission has been successfully approved.</p>
                ' . $infoBox . '
                <p>Class details will be shared with you shortly.</p>
                <br>
                <p>Best Regards,<br><strong>Perfect Institute Team</strong></p>
            ';
            break;
        case 'rejected':
            $reason = isset($extra['reason']) ? htmlspecialchars($extra['reason']) : 'There was an issue with your submission.';
            $bodyContent = '
                <h2>Admission Status Update</h2>
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <div class="reason-box">
                    <p>' . $reason . '</p>
                </div>
                ' . $infoBox . '
                <p>Please review your details or contact support.</p>
                <br>
                <p>Best Regards,<br><strong>Perfect Institute Team</strong></p>
            ';
            break;
        case 'payment_received':
            $bodyContent = '
                <h2>Payment Received</h2>
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your payment has been successfully received.</p>
                ' . $infoBox . '
                <p>We will contact you shortly with further details.</p>
                <br>
                <p>Best Regards,<br><strong>Perfect Institute Team</strong></p>
            ';
            break;
        default:
            $bodyContent = '';
    }

    return $header . $bodyContent . $footer;
}

/**
 * Log email errors to a file
 */
function logEmailError($message) {
    $logEntry = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL;
    if (defined('EMAIL_LOG_FILE')) {
        $dir = dirname(EMAIL_LOG_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(EMAIL_LOG_FILE, $logEntry, FILE_APPEND);
    }
    error_log($message);
}

/**
 * Log successful email sends (optional)
 */
function logEmailActivity($message) {
    $logEntry = '[' . date('Y-m-d H:i:s') . '] INFO: ' . $message . PHP_EOL;
    if (defined('EMAIL_LOG_FILE')) {
        $dir = dirname(EMAIL_LOG_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(str_replace('errors', 'activity', EMAIL_LOG_FILE), $logEntry, FILE_APPEND);
    }
}