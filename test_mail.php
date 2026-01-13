<?php
require_once __DIR__ . '/includes/mail.php';

if (send_email('youremail@example.com', 'PickleHub Test Mail', '<h2>Hello from PickleHub!</h2><p>Your email setup works ✅</p>')) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Failed to send email. Check error_log or mail_config.php.";
}
