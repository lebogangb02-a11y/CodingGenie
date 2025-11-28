<?php
// Utility validation functions for SA data formats

function only_digits($s) {
    return preg_replace('/\D+/', '', (string)$s);
}

function luhn_check($number) {
    $number = only_digits($number);
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = intval($number[$i]);
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function validate_sa_id($id) {
    $id = only_digits($id);
    if (strlen($id) !== 13) return false;

    $yy = intval(substr($id, 0, 2));
    $mm = intval(substr($id, 2, 2));
    $dd = intval(substr($id, 4, 2));

    // Try 19xx then 20xx for sanity
    $validDate = checkdate($mm, $dd, 1900 + $yy) || checkdate($mm, $dd, 2000 + $yy);
    if (!$validDate) return false;

    return luhn_check($id);
}

function validate_sa_phone($phone) {
    $clean = preg_replace('/[\s\-()]+/', '', trim($phone));
    // Allow local 0XXXXXXXXX (10 digits) or +27XXXXXXXXX (11 or 12 chars with +)
    if (preg_match('/^0\d{9}$/', $clean)) return true;
    if (preg_match('/^\+?27\d{9}$/', $clean)) return true;
    return false;
}

function validate_sa_postal_code($code) {
    return preg_match('/^\d{4}$/', trim($code)) === 1;
}

function validate_sa_date($date) {
    // Expect YYYY-MM-DD
    $parts = explode('-', $date);
    if (count($parts) !== 3) return false;
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function normalize_city($city) {
    return strcasecmp(trim($city), 'Zeenust') === 0 ? 'Zeerust' : trim($city);
}

// Minimal SecurityUtils class to provide static helpers referenced across the app.
// Designed to avoid fatal errors and provide sensible defaults without altering business logic.
class SecurityUtils {
    public static function getClientIP() {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        foreach ($candidates as $ip) {
            if (!$ip) continue;
            // When a chain is present, pick the first valid IP
            if (strpos($ip, ',') !== false) {
                foreach (array_map('trim', explode(',', $ip)) as $part) {
                    if (filter_var($part, FILTER_VALIDATE_IP)) return $part;
                }
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return '0.0.0.0';
    }

    public static function sanitizeInput($input) {
        if (is_array($input)) {
            $out = [];
            foreach ($input as $k => $v) {
                $out[$k] = self::sanitizeInput($v);
            }
            return $out;
        }
        if (is_string($input)) {
            $input = trim($input);
            // Strip tags and normalize whitespace
            $input = preg_replace('/\s+/', ' ', $input);
            return strip_tags($input);
        }
        return $input;
    }

    public static function validateEmail($email) {
        $email = is_string($email) ? trim($email) : '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: false;
    }

    public static function validatePhone($phone) {
        // Reuse SA phone validation; return normalized if valid
        if (validate_sa_phone($phone)) {
            return preg_replace('/[\s\-()]+/', '', trim($phone));
        }
        return false;
    }

    public static function validateSAIdNumber($id) {
        return validate_sa_id($id);
    }

    public static function checkRateLimit($pdo, $key, $action, $limit, $windowSeconds) {
        if (!is_string($key)) $key = (string)$key;
        $bucketKey = 'rl_' . $action . '_' . md5($key);
        $now = time();
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        $events = $_SESSION['rate_limits'][$bucketKey] ?? [];
        // Drop events outside the window
        $events = array_filter($events, function ($ts) use ($now, $windowSeconds) {
            return ($ts >= ($now - $windowSeconds));
        });
        if (count($events) >= (int)$limit) {
            throw new Exception('Too many attempts. Please try again later.');
        }
        $events[] = $now;
        $_SESSION['rate_limits'][$bucketKey] = $events;
        // Optional: best-effort persistence/logging (non-fatal on failure)
        try {
            if ($pdo instanceof PDO) {
                $stmt = $pdo->prepare('INSERT INTO rate_limit_events (bucket, occurred_at) VALUES (?, NOW())');
                $stmt->execute([$bucketKey]);
            }
        } catch (Throwable $e) {
            // Silently ignore if table doesn't exist
        }
    }

    public static function logSecurityEvent($pdo, $eventType, $message, $userId, $ipAddress) {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        try {
            if ($pdo instanceof PDO) {
                $stmt = $pdo->prepare('INSERT INTO security_logs (event_type, message, user_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$eventType, $message, $userId, $ipAddress, $ua]);
                return;
            }
        } catch (Throwable $e) {
            // Fall through to error_log
        }
        error_log('[SECURITY] ' . $eventType . ' - ' . $message . ' | user=' . ($userId ?? 'null') . ' ip=' . ($ipAddress ?? 'unknown') . ' ua=' . $ua);
    }

    public static function validateUserAccess($pdo, $resourceStudentId, $sessionStudentId) {
        if (!$resourceStudentId || !$sessionStudentId || $resourceStudentId != $sessionStudentId) {
            throw new Exception('Access denied: student identity mismatch.');
        }
        return true;
    }

    public static function isActionAllowed($action, $status) {
        $action = strtolower((string)$action);
        $status = strtolower((string)$status);
        $matrix = [
            'view' => ['draft','in_progress','awaiting_documents','submitted','review','approved','rejected','needs_correction'],
            'edit' => ['draft','in_progress','awaiting_documents','needs_correction'],
            'upload' => ['draft','in_progress','awaiting_documents','needs_correction'],
            'submit' => ['in_progress','awaiting_documents'],
        ];
        $allowed = $matrix[$action] ?? ['draft'];
        return in_array($status, $allowed, true);
    }

    public static function generateCSRFToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Session should already be active via session_config.php, but ensure safety
            @session_start();
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) return false;
        if (!is_string($token)) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

?>