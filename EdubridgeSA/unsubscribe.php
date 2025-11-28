<?php
require_once __DIR__ . '/config_application.php';

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Unsubscribe - EduBridge SA</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f5f7fa; margin:0; padding:40px; }
    .card { max-width: 640px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.08); padding:24px; }
    .title { margin:0 0 12px; color:#1a5fb4; }
    .muted { color:#666; }
    .btn { display:inline-block; margin-top:16px; background:#1a5fb4; color:#fff; text-decoration:none; padding:10px 16px; border-radius:6px; }
  </style>
 </head>
 <body>
  <div class="card">
    <h1 class="title">Unsubscribe</h1>
    <p class="muted">You can opt out of non-essential emails from EduBridge SA. Transactional messages (like password resets and verification) may still be sent to keep your account secure.</p>
    <?php if ($email): ?>
      <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
    <?php endif; ?>
    <p>If you received an unwanted message, please contact us so we can adjust your preferences.</p>
    <p><a class="btn" href="<?php echo BASE_URL; ?>/contact">Contact Support</a></p>
  </div>
 </body>
 </html>