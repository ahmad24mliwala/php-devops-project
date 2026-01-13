<?php
/**
 * Avoji Foods - Branded Email Template
 * Usage: include this file and call email_template($subject, $content)
 */

function email_template($subject, $content) {
    return '
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
    </head>
    <body style="margin:0;padding:0;background-color:#f8f9fa;font-family:Arial,Helvetica,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;padding:20px 0;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 0 10px rgba(0,0,0,0.05);">
                        <!-- Header -->
                        <tr style="background:linear-gradient(90deg,#28a745,#20c997);color:#fff;">
                            <td align="center" style="padding:20px;">
                                <h1 style="margin:0;font-size:26px;letter-spacing:1px;">🥒 Avoji Foods</h1>
                                <p style="margin:5px 0 0;font-size:14px;">Authentic Taste. Homemade Perfection.</p>
                            </td>
                        </tr>
                        <!-- Body -->
                        <tr>
                            <td style="padding:30px 25px;color:#333333;font-size:16px;line-height:1.6;">
                                ' . $content . '
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr style="background-color:#f0f0f0;">
                            <td align="center" style="padding:15px 10px;font-size:13px;color:#666;">
                                © ' . date('Y') . ' Avoji Foods | Crafted with ❤️ in India<br>
                                <a href="https://avoji.webshigh.com" style="color:#28a745;text-decoration:none;">Visit Our Website</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}
?>
