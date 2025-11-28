<?php
require_once 'config.php';

/**
 * Robust email existence check
 * Returns associative array with:
 * - exists (bool)
 * - verified (bool)
 * - status (string|null)
 * - user_id (int|null)
 * - can_resend (bool)
 */
function checkEmailExistence($email) {
    $result = [
        'exists' => false,
        'verified' => false,
        'status' => null,
        'user_id' => null,
        'can_resend' => false,
    ];

    try {
        $pdo = isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $stmt = $pdo->prepare("SELECT id, status, email_verified, verification_token FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $result['exists'] = true;
            $result['verified'] = (int)($user['email_verified'] ?? 0) === 1;
            $result['status'] = $user['status'] ?? null;
            $result['user_id'] = (int)($user['id'] ?? 0) ?: null;
            $result['can_resend'] = !$result['verified'] && !empty($user['verification_token']);
        }
    } catch (Throwable $e) {
        error_log('checkEmailExistence error: ' . $e->getMessage());
    }

    return $result;
}

?>