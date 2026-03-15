<?php
/**
 * mailer.php — Secure Email via PHPMailer + Resend SMTP
 * ─────────────────────────────────────────────────────────────
 * All credentials (API key, email addresses) are loaded from
 * the .env file above the web root — never hardcoded here.
 *
 * phpdotenv must already be loaded before this file is included
 * (db.php loads it first; if using mailer standalone, load it here).
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load .env if not already loaded (safe to call multiple times)
if (empty($_ENV['RESEND_API_KEY'])) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
}

// Validate email-specific env vars are present
$dotenv ?? Dotenv::createImmutable(__DIR__ . '/../../');
if (empty($_ENV['RESEND_API_KEY']) || empty($_ENV['FROM_EMAIL']) || empty($_ENV['ADMIN_EMAIL'])) {
    error_log('Mailer: Missing required .env variables (RESEND_API_KEY, FROM_EMAIL, ADMIN_EMAIL)');
}


/**
 * make_mailer()
 * Internal factory — builds a PHPMailer instance pointed at Resend SMTP.
 * Credentials come entirely from $_ENV.
 *
 * @return PHPMailer
 */
function make_mailer(): PHPMailer {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.resend.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'resend';                    // Always literally "resend" for Resend SMTP
    $mail->Password   = $_ENV['RESEND_API_KEY'];     // Loaded from .env — never hardcoded
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom($_ENV['FROM_EMAIL'], $_ENV['FROM_NAME']);
    $mail->CharSet = 'UTF-8';

    return $mail;
}


/**
 * send_booking_confirmation()
 * Sends a booking confirmation to the customer.
 * Only fires if the customer provided an email address.
 */
function send_booking_confirmation(array $data): bool {
    if (empty($data['email'])) return false;

    try {
        $mail = make_mailer();
        $mail->addAddress($data['email'], $data['name']);
        $mail->Subject = 'Booking Confirmed – MAS Auto';
        $mail->isHTML(true);
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a;'>
                <div style='background:#C8102E;padding:24px 32px;'>
                    <h1 style='color:#fff;margin:0;font-size:22px;'>MAS Auto – Ride With Confidence</h1>
                </div>
                <div style='padding:32px;background:#f9f9f9;'>
                    <h2 style='margin-top:0;'>Hi {$data['name']}, your booking is confirmed!</h2>
                    <p>We've received your service request. Our team will contact you on <strong>{$data['phone']}</strong> to confirm.</p>
                    <table style='width:100%;border-collapse:collapse;margin:24px 0;'>
                        <tr><td style='padding:10px 14px;border:1px solid #e0e0e0;font-weight:bold;width:40%;'>Service</td><td style='padding:10px 14px;border:1px solid #e0e0e0;'>{$data['service']}</td></tr>
                        <tr style='background:#f4f4f4;'><td style='padding:10px 14px;border:1px solid #e0e0e0;font-weight:bold;'>Vehicle</td><td style='padding:10px 14px;border:1px solid #e0e0e0;'>{$data['vehicle']}</td></tr>
                        <tr><td style='padding:10px 14px;border:1px solid #e0e0e0;font-weight:bold;'>Date</td><td style='padding:10px 14px;border:1px solid #e0e0e0;'>{$data['date']}</td></tr>
                        <tr style='background:#f4f4f4;'><td style='padding:10px 14px;border:1px solid #e0e0e0;font-weight:bold;'>Time</td><td style='padding:10px 14px;border:1px solid #e0e0e0;'>" . ($data['time'] ?: 'Flexible') . "</td></tr>
                        " . ($data['notes'] ? "<tr><td style='padding:10px 14px;border:1px solid #e0e0e0;font-weight:bold;'>Notes</td><td style='padding:10px 14px;border:1px solid #e0e0e0;'>{$data['notes']}</td></tr>" : "") . "
                    </table>
                    <p style='color:#555;font-size:14px;'>📍 Polokwane Space Park, 22 Doloriet Street, Ladanna<br>📞 060 756 0744</p>
                </div>
                <div style='padding:16px 32px;background:#1a1a1a;color:#888;font-size:12px;'>&copy; " . date('Y') . " MAS Auto. All rights reserved.</div>
            </div>
        ";
        $mail->AltBody = "Hi {$data['name']}, your booking for {$data['service']} on {$data['date']} is confirmed. We'll contact you on {$data['phone']}. – MAS Auto";
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Booking confirmation email failed: ' . $e->getMessage());
        return false;
    }
}


/**
 * send_booking_notification()
 * Internal alert to MAS Auto staff for every new booking.
 */
function send_booking_notification(array $data, int $id): bool {
    try {
        $mail = make_mailer();
        $mail->addAddress($_ENV['ADMIN_EMAIL'], 'MAS Auto Team');
        $mail->Subject = "🚗 New Booking #{$id} – {$data['name']} ({$data['service']})";
        $mail->isHTML(true);
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;color:#1a1a1a;'>
                <div style='background:#C8102E;padding:20px 28px;'>
                    <h2 style='color:#fff;margin:0;'>New Booking #{$id}</h2>
                </div>
                <div style='padding:28px;background:#f9f9f9;'>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;width:35%;'>Customer</td><td style='padding:8px 12px;border:1px solid #ddd;'>{$data['name']}</td></tr>
                        <tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;'>Phone</td><td style='padding:8px 12px;border:1px solid #ddd;'><a href='tel:{$data['phone']}'>{$data['phone']}</a></td></tr>
                        <tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;'>Vehicle</td><td style='padding:8px 12px;border:1px solid #ddd;'>{$data['vehicle']}</td></tr>
                        <tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;'>Service</td><td style='padding:8px 12px;border:1px solid #ddd;'>{$data['service']}</td></tr>
                        <tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;'>Date</td><td style='padding:8px 12px;border:1px solid #ddd;'>{$data['date']}</td></tr>
                        <tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;'>Time</td><td style='padding:8px 12px;border:1px solid #ddd;'>" . ($data['time'] ?: 'Flexible') . "</td></tr>
                        " . ($data['notes'] ? "<tr><td style='padding:8px 12px;border:1px solid #ddd;font-weight:bold;'>Notes</td><td style='padding:8px 12px;border:1px solid #ddd;'>{$data['notes']}</td></tr>" : "") . "
                    </table>
                    <p style='margin-top:20px;'>
                        <a href='https://wa.me/27" . ltrim($data['phone'], '0') . "' style='background:#25D366;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;'>WhatsApp Customer</a>
                    </p>
                </div>
            </div>
        ";
        $mail->AltBody = "New booking #{$id}: {$data['name']} | {$data['phone']} | {$data['service']} on {$data['date']}";
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Booking notification email failed: ' . $e->getMessage());
        return false;
    }
}


/**
 * send_contact_notification()
 * Internal alert to MAS Auto staff for every contact form message.
 */
function send_contact_notification(array $data, int $id): bool {
    try {
        $mail = make_mailer();
        $mail->addAddress($_ENV['ADMIN_EMAIL'], 'MAS Auto Team');
        $mail->Subject = "💬 New Message #{$id} – {$data['name']}";
        $mail->isHTML(true);
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;color:#1a1a1a;'>
                <div style='background:#1a1a1a;padding:20px 28px;'>
                    <h2 style='color:#C8102E;margin:0;'>New Contact Message #{$id}</h2>
                </div>
                <div style='padding:28px;background:#f9f9f9;'>
                    <p><strong>From:</strong> {$data['name']}</p>
                    <p><strong>Phone:</strong> <a href='tel:{$data['phone']}'>{$data['phone']}</a></p>
                    " . ($data['email'] ? "<p><strong>Email:</strong> <a href='mailto:{$data['email']}'>{$data['email']}</a></p>" : "") . "
                    <hr style='border:none;border-top:1px solid #ddd;margin:16px 0;'>
                    <p style='background:#fff;padding:16px;border-left:4px solid #C8102E;'>{$data['message']}</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Message from {$data['name']} ({$data['phone']}): {$data['message']}";
        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Contact notification email failed: ' . $e->getMessage());
        return false;
    }
}