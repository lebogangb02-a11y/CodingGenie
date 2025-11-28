<?php
// scripts/monitor-email-health.php
require_once __DIR__ . '/../config/email.php';

$failures = checkEmailHealth();
echo "Today's failed emails: {$failures}\n";

// Optional: exit non-zero on high failures for external monitors
if ($failures > 10) {
    exit(2);
}
exit(0);
?>