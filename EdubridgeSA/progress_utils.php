<?php
// Progress estimation utilities
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?");
    $stmt->execute([DB_NAME, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function medianFromSeconds(array $seconds): ?float {
    $seconds = array_values(array_filter($seconds, fn($v) => $v !== null && $v >= 0));
    if (empty($seconds)) return null;
    sort($seconds);
    $count = count($seconds);
    $mid = intdiv($count, 2);
    if ($count % 2 === 0) {
        return ($seconds[$mid - 1] + $seconds[$mid]) / 2.0;
    }
    return (float)$seconds[$mid];
}

function secondsBetween(?string $start, ?string $end): ?int {
    if (!$start || !$end) return null;
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false) return null;
    return max(0, $e - $s);
}

function computeHistoricalEstimates(PDO $pdo): array {
    $estimates = [
        'email_verification_sec' => null,
        'profile_setup_sec' => null,
        'documents_upload_sec' => null,
        'application_submission_sec' => null,
    ];

    // Stage 1: verified_at - created_at
    $rows = $pdo->query("SELECT created_at, verified_at FROM users WHERE verified_at IS NOT NULL")->fetchAll();
    $dur = array_map(fn($r) => secondsBetween($r['created_at'] ?? null, $r['verified_at'] ?? null), $rows);
    $estimates['email_verification_sec'] = medianFromSeconds($dur) ?? 24 * 3600; // fallback 24h

    // Stage 2: profile_updated_at - verified_at
    $hasProfileUpdated = in_array('profile_updated_at', array_map(fn($r)=>$r['COLUMN_NAME'], $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='users'")->fetchAll()));
    if ($hasProfileUpdated) {
        $rows = $pdo->query("SELECT verified_at, profile_updated_at FROM users WHERE verified_at IS NOT NULL AND profile_updated_at IS NOT NULL")->fetchAll();
        $dur = array_map(fn($r) => secondsBetween($r['verified_at'] ?? null, $r['profile_updated_at'] ?? null), $rows);
        $estimates['profile_setup_sec'] = medianFromSeconds($dur) ?? 2 * 24 * 3600; // fallback 2d
    } else {
        $estimates['profile_setup_sec'] = 2 * 24 * 3600;
    }

    // Stage 3: documents upload (first doc) - profile_updated_at
    if (tableExists($pdo, 'application_documents')) {
        $rows = $pdo->query("SELECT u.profile_updated_at, MIN(d.uploaded_at) AS first_upload FROM users u JOIN application_documents d ON d.user_id = u.id WHERE u.profile_updated_at IS NOT NULL GROUP BY u.id")->fetchAll();
        $dur = array_map(fn($r) => secondsBetween($r['profile_updated_at'] ?? null, $r['first_upload'] ?? null), $rows);
        $estimates['documents_upload_sec'] = medianFromSeconds($dur) ?? 3 * 24 * 3600; // fallback 3d
    } else {
        $estimates['documents_upload_sec'] = 3 * 24 * 3600;
    }

    // Stage 4: application submission - first doc
    if (tableExists($pdo, 'applications')) {
        $rows = $pdo->query("SELECT MIN(d.uploaded_at) AS first_upload, a.submitted_at FROM applications a JOIN application_documents d ON d.application_id = a.id WHERE a.submitted_at IS NOT NULL GROUP BY a.id")->fetchAll();
        $dur = array_map(fn($r) => secondsBetween($r['first_upload'] ?? null, $r['submitted_at'] ?? null), $rows);
        $estimates['application_submission_sec'] = medianFromSeconds($dur) ?? 4 * 24 * 3600; // fallback 4d
    } else {
        $estimates['application_submission_sec'] = 4 * 24 * 3600;
    }

    return $estimates;
}

function computeUserStage(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT id, status, email_verified, created_at, verified_at, profile_updated_at FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) return ['stage' => 'unknown', 'stage_start' => null, 'stage_complete' => null];

    // Stage mapping
    if (!(int)$u['email_verified']) {
        return ['stage' => 'verification', 'stage_start' => $u['created_at'] ?? null, 'stage_complete' => $u['verified_at'] ?? null];
    }
    if (empty($u['profile_updated_at'])) {
        return ['stage' => 'profile_setup', 'stage_start' => $u['verified_at'] ?? $u['created_at'] ?? null, 'stage_complete' => null];
    }

    // Documents uploaded?
    $docsUploadedAt = null;
    if (tableExists($pdo, 'application_documents')) {
        $stmt = $pdo->prepare("SELECT MIN(uploaded_at) AS first_upload FROM application_documents WHERE user_id=?");
        $stmt->execute([$userId]);
        $docsUploadedAt = $stmt->fetchColumn();
    }
    if (!$docsUploadedAt) {
        return ['stage' => 'documents_upload', 'stage_start' => $u['profile_updated_at'], 'stage_complete' => null];
    }

    // Application submitted?
    $submittedAt = null;
    if (tableExists($pdo, 'applications')) {
        $stmt = $pdo->prepare("SELECT submitted_at FROM applications WHERE user_id=? ORDER BY submitted_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $submittedAt = $stmt->fetchColumn();
    }
    if (!$submittedAt) {
        return ['stage' => 'application_submission', 'stage_start' => $docsUploadedAt, 'stage_complete' => null];
    }

    // Completed initial pipeline
    return ['stage' => 'complete', 'stage_start' => $submittedAt, 'stage_complete' => $submittedAt];
}

function addBuffer(float $seconds, float $ratio = 0.2): float { return $seconds * (1 + $ratio); }

function statusAgainstEstimate(float $elapsed, float $estimate, float $bufferRatio = 0.2): string {
    $aheadThreshold = $estimate * (1 - $bufferRatio);
    $onTrackThreshold = addBuffer($estimate, $bufferRatio);
    $slightlyDelayedThreshold = $estimate * (1 + $bufferRatio * 1.5);
    if ($elapsed <= $aheadThreshold) return 'Ahead of Schedule';
    if ($elapsed <= $onTrackThreshold) return 'On Track';
    if ($elapsed <= $slightlyDelayedThreshold) return 'Slightly Delayed';
    return 'Delayed';
}

function computeProgressEstimates(PDO $pdo, int $userId): array {
    $hist = computeHistoricalEstimates($pdo);
    $stageInfo = computeUserStage($pdo, $userId);
    $now = time();
    $stageStartTs = $stageInfo['stage_start'] ? strtotime($stageInfo['stage_start']) : null;
    $elapsed = $stageStartTs ? max(0, $now - $stageStartTs) : 0;

    $stageEstimateMap = [
        'verification' => $hist['email_verification_sec'],
        'profile_setup' => $hist['profile_setup_sec'],
        'documents_upload' => $hist['documents_upload_sec'],
        'application_submission' => $hist['application_submission_sec'],
        'complete' => 0,
    ];
    $estimate = $stageEstimateMap[$stageInfo['stage']] ?? 0;
    $buffered = addBuffer($estimate, 0.2);
    $status = $estimate > 0 ? statusAgainstEstimate($elapsed, $estimate, 0.2) : 'Complete';

    // Build per-stage overview with buffered times
    $overview = [
        ['key' => 'verification', 'label' => 'Email Verification', 'estimate_sec' => addBuffer($hist['email_verification_sec'], 0.2)],
        ['key' => 'profile_setup', 'label' => 'Profile Setup', 'estimate_sec' => addBuffer($hist['profile_setup_sec'], 0.2)],
        ['key' => 'documents_upload', 'label' => 'Documents Upload', 'estimate_sec' => addBuffer($hist['documents_upload_sec'], 0.2)],
        ['key' => 'application_submission', 'label' => 'Application Submission', 'estimate_sec' => addBuffer($hist['application_submission_sec'], 0.2)],
    ];

    // Simple percentage based on stage index
    $stageOrder = ['verification', 'profile_setup', 'documents_upload', 'application_submission', 'complete'];
    $idx = array_search($stageInfo['stage'], $stageOrder, true);
    $progressPct = $idx === false ? 0 : min(100, (int)round(($idx / (count($stageOrder) - 1)) * 100));

    return [
        'stage' => $stageInfo['stage'],
        'stage_start' => $stageInfo['stage_start'],
        'elapsed_sec' => $elapsed,
        'estimate_sec' => $estimate,
        'buffered_estimate_sec' => $buffered,
        'status' => $status,
        'overview' => $overview,
        'progress_pct' => $progressPct,
    ];
}

?>