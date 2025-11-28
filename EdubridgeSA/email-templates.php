<?php
/**
 * Centralized HTML templates for emails
 */

function buildVerificationEmailHtml($name, $verificationToken, $baseUrl) {
    // Brand palette
    $blue = '#004AAD';
    $green = '#4CAF50';
    $white = '#FFFFFF';
    $safeName = htmlspecialchars($name ?: 'Student');
    // Use secure redirect for click tracking
    $link = rtrim($baseUrl, '/') . '/verify-redirect.php?t=' . urlencode($verificationToken);
    $logoUrl = rtrim($baseUrl, '/') . '/images/logo.png.jpg';
    $homeUrl = rtrim($baseUrl, '/');
    $unsubscribeUrl = rtrim($baseUrl, '/') . '/unsubscribe.php';
    $privacyUrl = rtrim($baseUrl, '/') . '/privacy.php';
    $facebook = 'https://facebook.com/EduBridgeSA';
    $linkedin = 'https://www.linkedin.com/company/edubridgesa';
    $instagram = 'https://www.instagram.com/edubridgesa';
    return "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verify Your Email - EduBridgeSA</title>
    <link rel='icon' href='{$logoUrl}'>
    <style>
        body { margin:0; padding:24px; font-family: Poppins, Inter, Arial, sans-serif; line-height: 1.6; color: #222; background: linear-gradient(135deg, {$blue} 0%, {$green} 100%); }
        img { border:0; outline:none; text-decoration:none; max-width:100%; height:auto; }
        .frame { max-width: 640px; margin: 0 auto; }
        .card { background: {$white}; border-radius: 16px; box-shadow: 0 12px 30px rgba(0,0,0,0.12); overflow:hidden; }
        .header { padding: 24px; text-align: center; border-bottom: 1px solid #f0f0f0; }
        .logo { display:inline-block; }
        .logo img { max-width: 140px; display:block; margin:0 auto; }
        .content { padding: 24px; }
        h1 { font-size: 22px; margin: 0 0 6px 0; color: {$blue}; }
        p { margin: 0 0 12px 0; }
        .verify-box { background: #F4FFF5; border: 1px solid {$green}; border-radius: 12px; padding: 16px; text-align: center; margin: 16px 0; }
        .button { display:inline-block; background: {$blue}; color: {$white}; padding: 12px 20px; text-decoration:none; border-radius: 8px; font-weight:600; }
        .button:hover { background:#003a87; }
        .long-link { word-break: break-all; background:#f7f7f8; padding:10px; border-radius:8px; font-family: ui-monospace, Menlo, Consolas, monospace; color:#333; }
        .footer { padding: 16px 24px 24px 24px; border-top: 1px solid #f0f0f0; text-align:center; font-size: 13px; color:#666; }
        .social a { text-decoration:none; color: {$blue}; margin: 0 8px; }
        .social a:hover { text-decoration:underline; }
        @media (max-width: 640px) { .content { padding: 18px; } .button { width: 100%; box-sizing: border-box; } }
    </style>
</head>
<body>
    <div class='frame'>
        <div class='card'>
            <div class='header'>
                <a class='logo' href='{$homeUrl}' aria-label='EduBridgeSA Homepage'>
                    <img src='{$logoUrl}' alt='EduBridgeSA logo'>
                </a>
            </div>
            <div class='content'>
                <h1>Verify your EduBridgeSA account</h1>
                <p>Hi {$safeName},</p>
                <p>Please verify your email address to activate your EduBridgeSA account.</p>
                <div class='verify-box'>
                    <a href='{$link}' class='button' aria-label='Verify Email'>Verify Email</a>
                    <p style='margin-top:10px;'>If the button doesn’t work, copy this link:</p>
                    <p class='long-link'>{$link}</p>
                    <p style='margin-top:10px;'>This verification link expires in 24 hours.</p>
                </div>
            </div>
            <div class='footer'>
                <div class='social'>
                    <a href='{$facebook}' aria-label='EduBridgeSA on Facebook'>Facebook</a>
                    <a href='{$linkedin}' aria-label='EduBridgeSA on LinkedIn'>LinkedIn</a>
                    <a href='{$instagram}' aria-label='EduBridgeSA on Instagram'>Instagram</a>
                </div>
                <p style='margin-top:8px;'>Support: " . ADMIN_EMAIL . " • <a href='{$privacyUrl}'>Privacy</a> • <a href='{$unsubscribeUrl}'>Unsubscribe</a></p>
            </div>
        </div>
    </div>
</body>
</html>";
}

?>