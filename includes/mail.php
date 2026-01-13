<?php
// includes/mail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// Load mail_config only once
if (!isset($MAIL_CONFIG)) {
    $MAIL_CONFIG = require __DIR__ . '/mail_config.php';
}

if (!function_exists('get_mail_config')) {
    function get_mail_config() {
        global $MAIL_CONFIG;
        return $MAIL_CONFIG;
    }
}

/**
 * Avoji Foods HTML Email Template
 */
if (!function_exists('avoji_template')) {
    function avoji_template($subject, $bodyContent) {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title></head>
        <body style="margin:0;padding:0;background:#f8f9fa;font-family:Arial,Helvetica,sans-serif;">
            <table width="100%" cellspacing="0" cellpadding="0" style="background:#f8f9fa;padding:20px 0;">
                <tr><td align="center">
                    <table width="600" cellspacing="0" cellpadding="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                        <tr style="background:linear-gradient(90deg,#28a745,#20c997);color:#fff;">
                            <td align="center" style="padding:20px;">
                                <h1 style="margin:0;font-size:26px;">Avoji Foods</h1>
                                <p style="margin:5px 0 0;font-size:14px;">Authentic Taste. Homemade Perfection.</p>
                            </td>
                        </tr>
                        <tr><td style="padding:30px 25px;color:#333;font-size:16px;line-height:1.6;">
                            ' . $bodyContent . '
                        </td></tr>
                        <tr style="background:#f0f0f0;">
                            <td align="center" style="padding:15px;font-size:13px;color:#666;">
                                © ' . date('Y') . ' Avoji Foods | Crafted with ❤️ in India<br>
                                <a href="https://avojifoods.com" style="color:#28a745;text-decoration:none;">Visit Our Website</a>
                            </td>
                        </tr>
                    </table>
                </td></tr>
            </table>
        </body></html>';
    }
}

/**
 * Send Email via PHPMailer (SMTP)
 */
if (!function_exists('send_email')) {
    function send_email($to, $subject, $htmlBody, $altBody = '') {
        $cfg = get_mail_config();
        $mail = new PHPMailer(true);
        $logFile = __DIR__ . '/../mail_debug.txt';

        try {
            // SMTP config
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];

            // FIXED ENCRYPTION
            $mail->SMTPSecure = ($cfg['secure'] === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Port       = $cfg['port'];
            $mail->Timeout    = 30;
            $mail->SMTPKeepAlive = false;

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Debug disabled
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = function($str, $level) use ($logFile) {
                file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] [SMTP DEBUG] $str\n", FILE_APPEND);
            };

            // Sender / Recipient
            $mail->setFrom($cfg['from_email'], $cfg['from_name']);
            $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = avoji_template($subject, $htmlBody);
            $mail->AltBody = $altBody ?: strip_tags($htmlBody);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->send();

            file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] ✅ SUCCESS → To: $to | Subject: $subject\n", FILE_APPEND);
            return true;

        } catch (Exception $e) {
            $error = $mail->ErrorInfo ?: $e->getMessage();
            file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] ❌ FAILED → To: $to | Error: $error\n", FILE_APPEND);
            error_log("Mail send failed: $error");
            return false;
        }
    }
}
?>
