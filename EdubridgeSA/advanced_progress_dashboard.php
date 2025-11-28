<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'progress_utils.php';

// Authentication guard
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Resolve user context
$username = $_SESSION['student_name'] ?? 'Student';
$email = $_SESSION['student_email'] ?? $_SESSION['email'] ?? '';
$reference_number = $_SESSION['reference_number'] ?? 'APP2025000000';
$student_id = (int)($_SESSION['student_id'] ?? 0);

// Helper: get latest application id and some attributes
function getApplicationContext(PDO $pdo, ?string $email, ?string $reference): array {
    $ctx = [
        'application_id' => null,
        'application_status' => 'draft',
        'department' => null,
        'created_at' => null,
        'updated_at' => null,
    ];

    try {
        if ($reference) {
            $stmt = $pdo->prepare("SELECT id, application_status, department, created_at, updated_at FROM applications WHERE reference_number = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute([$reference]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $ctx = array_merge($ctx, $row); }
        }
        if (!$ctx['application_id'] && $email) {
            $stmt = $pdo->prepare("SELECT id, application_status, department, created_at, updated_at, reference_number FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $ctx = array_merge($ctx, $row);
                if (!empty($row['reference_number'])) { $GLOBALS['reference_number'] = $row['reference_number']; }
            }
        }
    } catch (Throwable $e) {
        error_log('advanced_progress getApplicationContext error: ' . $e->getMessage());
    }
    return $ctx;
}

// Helper: gather sub-statuses for pipeline
function getStageStatuses(PDO $pdo, int $application_id, ?string $email): array {
    $stages = [
        'document_submission' => [
            'label' => 'Document Submission',
            'substeps' => [
                'certified_id' => false,
                'academic_results' => false,
                'proof_of_residence' => false,
            ],
            'status' => 'pending', // on_track, delayed, blocked, pending, completed
        ],
        'verification' => [
            'label' => 'Verification',
            'substeps' => [
                'email_verified' => false,
                'documents_verified' => false,
            ],
            'status' => 'pending',
        ],
        'review' => [
            'label' => 'Review',
            'substeps' => [
                'application_review' => false,
                'academic_review' => false,
            ],
            'status' => 'pending',
        ],
        'decision' => [
            'label' => 'Decision',
            'substeps' => [
                'submitted' => false,
                'finalized' => false,
            ],
            'status' => 'pending',
        ],
    ];

    try {
        // Documents uploaded
        $stmt = $pdo->prepare("SELECT document_type FROM application_documents WHERE application_id = ?");
        $stmt->execute([$application_id]);
        $types = array_map(fn($r) => $r['document_type'] ?? '', $stmt->fetchAll(PDO::FETCH_ASSOC));
        foreach (['certified_id', 'academic_results', 'proof_of_residence'] as $req) {
            if (in_array($req, $types)) { $stages['document_submission']['substeps'][$req] = true; }
        }
        $docCount = array_sum(array_map(fn($v)=>$v?1:0, $stages['document_submission']['substeps']));
        $stages['document_submission']['status'] = $docCount >= 2 ? 'on_track' : 'delayed';
        if ($docCount === 3) { $stages['document_submission']['status'] = 'completed'; }

        // Email verification (best-effort)
        $emailVerified = false;
        if ($email) {
            // Check verification flags from users table if present
            try {
                $stmtEV = $pdo->prepare("SELECT email_verified FROM users WHERE email = ? LIMIT 1");
                $stmtEV->execute([$email]);
                $emailVerified = (bool)$stmtEV->fetchColumn();
            } catch (Throwable $e) { /* ignore if column missing */ }
        }
        $stages['verification']['substeps']['email_verified'] = $emailVerified;

        // Documents verified heuristic: if required docs exist, consider verified when older than 2 days
        $docsVerified = false;
        try {
            $stmtDV = $pdo->prepare("SELECT MIN(created_at) AS oldest FROM application_documents WHERE application_id = ?");
            $stmtDV->execute([$application_id]);
            $oldest = $stmtDV->fetchColumn();
            if ($oldest) {
                $ageDays = (time() - strtotime($oldest)) / 86400;
                $docsVerified = $ageDays >= 2 && $docCount >= 2; // simple heuristic
            }
        } catch (Throwable $e) { /* noop */ }
        $stages['verification']['substeps']['documents_verified'] = $docsVerified;
        $verCount = array_sum(array_map(fn($v)=>$v?1:0, $stages['verification']['substeps']));
        $stages['verification']['status'] = $verCount === 2 ? 'completed' : ($verCount >= 1 ? 'on_track' : 'delayed');

        // Review status from application_status
        $stmtAS = $pdo->prepare("SELECT application_status FROM applications WHERE id = ?");
        $stmtAS->execute([$application_id]);
        $appStatus = strtolower($stmtAS->fetchColumn() ?: 'draft');
        $stages['review']['substeps']['application_review'] = in_array($appStatus, ['in_review', 'submitted', 'completed']);
        $stages['review']['substeps']['academic_review'] = in_array($appStatus, ['submitted', 'completed']);
        $revCount = array_sum(array_map(fn($v)=>$v?1:0, $stages['review']['substeps']));
        $stages['review']['status'] = $revCount === 2 ? 'completed' : ($revCount >= 1 ? 'on_track' : 'pending');

        // Decision
        $stages['decision']['substeps']['submitted'] = in_array($appStatus, ['submitted','completed']);
        $stages['decision']['substeps']['finalized'] = ($appStatus === 'completed');
        $decCount = array_sum(array_map(fn($v)=>$v?1:0, $stages['decision']['substeps']));
        $stages['decision']['status'] = $decCount === 2 ? 'completed' : ($decCount >= 1 ? 'on_track' : 'pending');

    } catch (Throwable $e) {
        error_log('advanced_progress getStageStatuses error: ' . $e->getMessage());
    }

    return $stages;
}

// Smart timeline estimation using existing utilities
function buildTimeline(PDO $pdo, int $userId): array {
    $base = ['estimate_text' => 'Estimated completion: 2–3 weeks', 'confidence' => 0.7, 'status' => 'On Track', 'buffer_days' => 3, 'range_days' => [14, 21]];
    try {
        $smart = computeProgressEstimates($pdo, $userId);
        // expect keys: total_estimate_days, buffered_days, status, current_stage, elapsed_days
        $total = (int)($smart['total_estimate_days'] ?? 14);
        $buffer = (int)($smart['buffered_days'] ?? 3);
        $status = $smart['status'] ?? 'On Track';
        $elapsed = (int)($smart['elapsed_days'] ?? 0);
        $minDays = max(7, intval($total * 0.8));
        $maxDays = intval($total + $buffer);
        $confidence = 0.6; // heuristic baseline
        if (($smart['sample_size'] ?? 0) > 30) { $confidence = 0.8; }
        if (($smart['variance'] ?? 0) > 6) { $confidence -= 0.1; }
        $base = [
            'estimate_text' => "Estimated completion: {$minDays}–{$maxDays} days",
            'confidence' => max(0.3, min(0.95, $confidence)),
            'status' => $status,
            'buffer_days' => $buffer,
            'range_days' => [$minDays, $maxDays],
            'elapsed_days' => $elapsed,
            'current_stage' => $smart['current_stage'] ?? 'Document Submission',
        ];
    } catch (Throwable $e) {
        error_log('advanced_progress buildTimeline error: ' . $e->getMessage());
    }
    return $base;
}

// Bottleneck detection
function detectBottlenecks(PDO $pdo, array $stages, array $timeline, int $application_id, ?string $email): array {
    $issues = [];

    // Missing required documents
    $missing = [];
    foreach (['certified_id','academic_results'] as $req) {
        if (!$stages['document_submission']['substeps'][$req]) { $missing[] = $req; }
    }
    if ($missing) {
        $issues[] = [
            'stage' => 'Document Submission',
            'severity' => 'blocked',
            'message' => 'Required documents missing: ' . implode(', ', $missing),
            'suggestion' => 'Upload documents to proceed',
            'action_url' => 'document_upload.php' . (isset($GLOBALS['reference_number']) ? ('?ref=' . urlencode($GLOBALS['reference_number'])) : ''),
        ];
    }

    // Email not verified
    if (!$stages['verification']['substeps']['email_verified']) {
        $issues[] = [
            'stage' => 'Verification',
            'severity' => 'delayed',
            'message' => 'Email not verified',
            'suggestion' => 'Verify email to speed up processing',
            'action_url' => 'verify-email.php',
        ];
    }

    // Inactivity heuristic: if elapsed exceeds upper range by 25%
    $elapsed = (int)($timeline['elapsed_days'] ?? 0);
    $upper = (int)($timeline['range_days'][1] ?? 21);
    if ($elapsed > ($upper * 1.25)) {
        $issues[] = [
            'stage' => 'Review',
            'severity' => 'delayed',
            'message' => 'Processing taking longer than expected',
            'suggestion' => 'Follow up with support or check pending items',
            'action_url' => 'support.php',
        ];
    }

    return $issues;
}

// Comparative analytics
function comparativeAnalytics(PDO $pdo, int $application_id, ?string $department, array $timeline): array {
    $data = [
        'speed_vs_average' => +15, // percentage faster than average, default optimistic
        'department' => $department ?: 'General',
        'dept_avg_days' => null,
        'overall_avg_days' => null,
        'milestone_predictions' => [],
    ];
    try {
        // Overall average completion days
        $stmt = $pdo->query("SELECT AVG(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) AS avg_days FROM applications WHERE application_status IN ('submitted','completed')");
        $overall = (float)($stmt->fetchColumn() ?: 14);
        $data['overall_avg_days'] = round($overall, 1);

        // Department average
        if ($department) {
            $stmt2 = $pdo->prepare("SELECT AVG(DATEDIFF(COALESCE(updated_at, NOW()), created_at)) AS avg_days FROM applications WHERE department = ? AND application_status IN ('submitted','completed')");
            $stmt2->execute([$department]);
            $deptAvg = (float)($stmt2->fetchColumn() ?: $overall);
            $data['dept_avg_days'] = round($deptAvg, 1);
        } else {
            $data['dept_avg_days'] = $data['overall_avg_days'];
        }

        // Compute speed vs average
        $upper = (int)($timeline['range_days'][1] ?? $data['overall_avg_days']);
        $pct = ($upper > 0 && $data['overall_avg_days'] > 0) ? (1 - ($upper / $data['overall_avg_days'])) * 100 : 0;
        $data['speed_vs_average'] = (int)round($pct);

        // Rough milestone predictions (days from start)
        $min = (int)($timeline['range_days'][0] ?? 10);
        $data['milestone_predictions'] = [
            ['label' => 'Verification complete', 'eta_days' => max(2, intval($min * 0.3))],
            ['label' => 'Review complete', 'eta_days' => max(4, intval($min * 0.65))],
            ['label' => 'Decision', 'eta_days' => max(6, intval($min))],
        ];
    } catch (Throwable $e) {
        error_log('advanced_progress comparativeAnalytics error: ' . $e->getMessage());
    }
    return $data;
}

// Build page data
$appCtx = getApplicationContext($pdo, $email, $reference_number);
$application_id = (int)($appCtx['id'] ?? $appCtx['application_id'] ?? 0);
$stages = $application_id ? getStageStatuses($pdo, $application_id, $email) : [];
$timeline = buildTimeline($pdo, $student_id ?: 0);
$issues = $application_id ? detectBottlenecks($pdo, $stages, $timeline, $application_id, $email) : [];
$analytics = $application_id ? comparativeAnalytics($pdo, $application_id, $appCtx['department'] ?? null, $timeline) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Application Progress Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5fb4;
            --secondary: #059669;
            --royal-blue: #1e3a8a;
            --royal-blue-light: #3b82f6;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --white: #fff;
        }

        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%); min-height: 100vh; }
        .container-xl { max-width: 1200px; }
        .card { border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,.12); border:1px solid var(--gray-200); }
        .card-header { background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%); color: var(--white); border-radius: 16px 16px 0 0; }
        .stage { display:flex; align-items:center; gap:12px; padding:12px; border:1px solid var(--gray-200); border-radius:12px; background:#fff; }
        .stage.on_track { border-left: 6px solid var(--success); }
        .stage.delayed { border-left: 6px solid var(--warning); }
        .stage.blocked { border-left: 6px solid var(--danger); }
        .stage.completed { border-left: 6px solid var(--primary); opacity: .95; }
        .badge-sub { background: var(--gray-100); color: var(--gray-800); border:1px solid var(--gray-300); }
        .pipeline { display:grid; grid-template-columns: 1fr; gap:12px; }
        @media(min-width: 768px) { .pipeline { grid-template-columns: 1fr 1fr; } }
        .progress-bar-custom { height: 12px; background: var(--gray-200); border-radius: 8px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); width: 0; transition: width .4s ease; }
        .status-chip { display:inline-block; padding:6px 12px; border-radius:999px; color:#fff; font-weight:600; }
        .status-on { background: var(--primary); }
        .status-ahead { background: var(--success); }
        .status-delay { background: var(--warning); }
        .status-block { background: var(--danger); }
        .issue { border-left:4px solid var(--warning); padding-left:12px; }
        .issue.blocked { border-left-color: var(--danger); }
    </style>
</head>
<body>
    <div class="container-xl py-4">
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">Application Progress</h4>
                            <small>Welcome <?= htmlspecialchars($username) ?> — Ref: <?= htmlspecialchars($reference_number) ?></small>
                        </div>
                        <span class="status-chip <?= ($timeline['status'] ?? 'On Track') === 'Ahead of Schedule' ? 'status-ahead' : (($timeline['status'] ?? '') === 'Delayed' ? 'status-delay' : 'status-on') ?>">
                            <?= htmlspecialchars($timeline['status'] ?? 'On Track') ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-8">
                                <div class="progress-bar-custom mb-2">
                                    <?php $pct = 0; if ($timeline['elapsed_days'] ?? 0) { $range = max(1, $timeline['range_days'][1] ?? 21); $pct = min(100, intval(($timeline['elapsed_days'] / $range) * 100)); } ?>
                                    <div class="progress-fill" style="width: <?= $pct ?>%"></div>
                                </div>
                                <div class="text-muted">
                                    <?= htmlspecialchars($timeline['estimate_text']) ?> · Confidence: <?= intval(($timeline['confidence'] ?? 0.6) * 100) ?>%
                                </div>
                            </div>
                            <div class="col-md-4">
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Current stage:</strong> <?= htmlspecialchars($timeline['current_stage'] ?? 'Document Submission') ?></li>
                                    <li><strong>Elapsed:</strong> <?= intval($timeline['elapsed_days'] ?? 0) ?> days</li>
                                    <li><strong>Buffer:</strong> +<?= intval($timeline['buffer_days'] ?? 3) ?> days</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pipeline -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Multi‑Stage Pipeline</h5></div>
                    <div class="card-body">
                        <?php if (!$stages) { echo '<p class="text-muted">No application found. Start your application to see detailed progress.</p>'; } else { ?>
                        <div class="pipeline">
                            <?php foreach ($stages as $key => $s): ?>
                                <div class="stage <?= htmlspecialchars($s['status']) ?>">
                                    <i class="fa-solid fa-layer-group fa-lg text-primary"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <strong><?= htmlspecialchars($s['label']) ?></strong>
                                            <span class="badge badge-sub">
                                                <?= htmlspecialchars(ucfirst(str_replace('_',' ', $s['status']))) ?>
                                            </span>
                                        </div>
                                        <div class="mt-2 d-flex flex-wrap gap-2">
                                            <?php foreach ($s['substeps'] as $sub => $done): ?>
                                                <span class="badge <?= $done ? 'bg-success' : 'bg-secondary' ?>">
                                                    <?= htmlspecialchars(ucwords(str_replace('_',' ', $sub))) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Bottlenecks -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Bottleneck Detection</h5></div>
                    <div class="card-body">
                        <?php if (!$issues) { ?>
                            <p class="text-success mb-0"><i class="fa-regular fa-circle-check"></i> No bottlenecks detected. You're on track.</p>
                        <?php } else { foreach ($issues as $issue): ?>
                            <div class="issue <?= htmlspecialchars($issue['severity']) ?> mb-3">
                                <strong><?= htmlspecialchars($issue['stage']) ?></strong>
                                <div class="text-muted"><?= htmlspecialchars($issue['message']) ?></div>
                                <div class="mt-2 d-flex gap-2">
                                    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($issue['action_url']) ?>">
                                        Resolve Now
                                    </a>
                                    <span class="text-muted small">Suggestion: <?= htmlspecialchars($issue['suggestion']) ?></span>
                                </div>
                            </div>
                        <?php } } ?>
                    </div>
                </div>
            </div>

            <!-- Comparative Analytics -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Comparative Analytics</h5></div>
                    <div class="card-body">
                        <?php if (!$analytics) { ?>
                            <p class="text-muted mb-0">Analytics become available once your application enters review.</p>
                        <?php } else { ?>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <span class="status-chip <?= ($analytics['speed_vs_average'] ?? 0) >= 0 ? 'status-ahead' : 'status-delay' ?>">
                                    <?= ($analytics['speed_vs_average'] ?? 0) >= 0 ? 'Faster than average' : 'Slower than average' ?>
                                </span>
                                <strong><?= abs((int)$analytics['speed_vs_average']) ?>%</strong>
                            </div>
                            <ul class="list-unstyled mb-3 text-muted">
                                <li>Department: <?= htmlspecialchars($analytics['department']) ?></li>
                                <li>Dept. average: <?= htmlspecialchars($analytics['dept_avg_days']) ?> days · Overall: <?= htmlspecialchars($analytics['overall_avg_days']) ?> days</li>
                            </ul>
                            <div>
                                <strong>Milestone predictions:</strong>
                                <ul class="mt-2">
                                    <?php foreach ($analytics['milestone_predictions'] as $m): ?>
                                        <li><?= htmlspecialchars($m['label']) ?> in ~<?= intval($m['eta_days']) ?> days</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Next Best Actions</h5></div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="document_upload.php<?= isset($reference_number) ? ('?ref=' . urlencode($reference_number)) : '' ?>">
                            <i class="fa-solid fa-file-arrow-up"></i> Upload Documents
                        </a>
                        <a class="btn btn-secondary" href="verify-email.php">
                            <i class="fa-regular fa-envelope"></i> Verify Email
                        </a>
                        <a class="btn btn-secondary" href="support.php">
                            <i class="fa-regular fa-life-ring"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>