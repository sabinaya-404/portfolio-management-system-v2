<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Sends a verification email to the given email address with the 6-digit code.
 *
 * @param string $email The recipient email address.
 * @param string $code The plain 6-digit verification code.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function sendVerificationEmail(string $email, string $code): bool
{
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'] ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
        $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);

        //Recipients
        error_log('MAIL_FROM diagnostics: _ENV[MAIL_FROM_ADDRESS] set=' .
            (isset($_ENV['MAIL_FROM_ADDRESS']) ? 'true' : 'false') .
            ', value=' .
            (isset($_ENV['MAIL_FROM_ADDRESS']) ? $_ENV['MAIL_FROM_ADDRESS'] : 'null') .
            ', _ENV[MAIL_FROM_NAME] set=' .
            (isset($_ENV['MAIL_FROM_NAME']) ? 'true' : 'false') .
            ', value=' .
            (isset($_ENV['MAIL_FROM_NAME']) ? $_ENV['MAIL_FROM_NAME'] : 'null'));
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? '', $_ENV['MAIL_FROM_NAME'] ?? 'Portfolio Management System');
        $mail->addAddress($email);

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Email Verification Code';
        $mail->Body    = '<p>Hello,</p>'
                        . '<p>Thank you for registering with Portfolio Management System.</p>'
                        . '<p>Your verification code is: <strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong></p>'
                        . '<p>Please enter this code on the verification page to verify your email address.</p>'
                        . '<p>This code will expire in 10 minutes.</p>'
                        . '<p>If you did not register for an account, please ignore this email.</p>';

        $mail->send();
        return true;
    } catch (Exception $e) {
        // MAIL-002 Fix: Enhanced error handling - log internal details safely, return generic response
        error_log('Mail error: ' . $mail->ErrorInfo); // Log detailed error for debugging
        return false; // Return generic failure to caller
    }
}