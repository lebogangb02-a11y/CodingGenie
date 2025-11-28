<?php
require_once 'session_config.php';
require_once 'config.php';
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}
?>
<?php
// Generate canonical Upload Documents URL for this session
$upload_docs_url = 'document_upload.php';
try {
    $contactEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
    $resolvedRef = null;
    if (!empty($_SESSION['reference_number'])) { $resolvedRef = $_SESSION['reference_number']; }
    if (!$resolvedRef && $contactEmail) {
        $stmt = $pdo->prepare("SELECT reference_number FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$contactEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['reference_number'])) { $resolvedRef = $row['reference_number']; }
    }
    if (!$resolvedRef) {
        $sessionRef = $_SESSION['reference_number'] ?? null;
        if ($sessionRef) { $resolvedRef = $sessionRef; }
    }
    if ($resolvedRef) {
        $upload_docs_url = 'document_upload.php?ref=' . urlencode($resolvedRef);
    }
} catch (PDOException $e) {
    error_log('track-progress upload link error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application Progress - EduBridge SA</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- AOS Animations -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --edubridge-blue: #0046BE;
            --soft-gray: #f5f7fb;
        }
        body { background: var(--soft-gray); }
        .page-header {
            background: linear-gradient(135deg, #0046BE 0%, #2b6ad9 100%);
            color: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0, 70, 190, 0.25);
        }
        .motivation {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255,255,255,0.35);
            color: #fff;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .status-card {
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.08);
            border: 1px solid #e9eef6;
        }
        .status-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 999px; font-weight: 600; text-transform: uppercase;
            border: 1px solid;
        }
        .status-pending { background: #fff3cd; color: #856404; border-color: #ffe9a8; }
        .status-review { background: #e3f2fd; color: #0d47a1; border-color: #bbdefb; }
        .status-approved { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .status-rejected { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .glow {
            box-shadow: 0 0 0 0 rgba(0,70,190,0.7);
            animation: pulse 2.2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0,70,190,0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0,70,190,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,70,190,0); }
        }
        .progress { height: 20px; background: #e9eef6; }
        .progress-bar { background: linear-gradient(90deg, #0046BE, #2b6ad9); }
        .stepper { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .step {
            display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 12px;
            border: 1px dashed #d8e0ee; background: #fff;
        }
        .step .circle { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .step.completed .circle { background: #d4edda; color: #155724; }
        .step.current { border-color: #0046BE; box-shadow: 0 0 0 3px rgba(0,70,190,0.15); }
        .step.current .circle { background: #e3f2fd; color: #0d47a1; }
        .doc-status-icon { font-size: 1.1rem; }
        .cta-btn {
            background: linear-gradient(135deg, #0046BE 0%, #2b6ad9 100%);
            color: #fff; border: none; border-radius: 12px; padding: 12px 16px;
        }
        .cta-btn:hover { filter: brightness(1.05); }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="page-header mb-4" data-aos="fade-up">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1">Track Application Progress</h1>
                    <p class="mb-2">See your current status and document checklist below.</p>
                    <div class="motivation"><i class="bi bi-stars"></i> <span id="quoteText">Every great journey begins with a single step — you’re almost there!</span></div>
                </div>
                <div class="text-end">
                    <span id="statusBadge" class="status-badge status-review glow">Application Status: IN REVIEW <i class="bi bi-search"></i></span>
                </div>
            </div>
        </div>

        <!-- Status & Progress Card -->
        <div class="card status-card mb-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-md-6">
                        <h5 class="card-title mb-2">Overall Progress</h5>
                        <div class="d-flex align-items-center gap-2">
                            <strong id="progressPct" class="fs-4">0%</strong>
                            <span class="text-muted">complete</span>
                        </div>
                        <div class="progress mt-3" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div id="progressFill" class="progress-bar" style="width:0%"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="card-title mb-2">Progress Tracker</h5>
                        <div class="stepper">
                            <div class="step" id="step-1"><span class="circle">1</span> <div>Application Submitted</div> <i class="bi bi-check-circle text-success ms-auto d-none" id="step-1-check"></i></div>
                            <div class="step" id="step-2"><span class="circle">2</span> <div>Documents Uploaded</div> <i class="bi bi-gear-fill text-secondary ms-auto d-none" id="step-2-gear"></i></div>
                            <div class="step" id="step-3"><span class="circle">3</span> <div>In Review</div> <i class="bi bi-hourglass-split text-primary ms-auto d-none" id="step-3-time"></i></div>
                            <div class="step" id="step-4"><span class="circle">4</span> <div>Decision Released</div> <i class="bi bi-flag text-info ms-auto d-none" id="step-4-flag"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Checklist -->
        <div class="card mb-4" data-aos="fade-up" data-aos-delay="150">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Document Checklist</h5>
                    <a class="btn cta-btn" href="<?php echo htmlspecialchars($upload_docs_url); ?>"><i class="bi bi-upload"></i> Upload Documents</a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="docTableBody">
                            <tr>
                                <td>Certified ID Copy</td>
                                <td><span class="doc-status-icon">⏳</span> <span class="text-warning">Pending</span></td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($upload_docs_url); ?>">Upload</a></td>
                            </tr>
                            <tr>
                                <td>Academic Transcript</td>
                                <td><span class="doc-status-icon">⏳</span> <span class="text-warning">Pending</span></td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($upload_docs_url); ?>">Upload</a></td>
                            </tr>
                            <tr>
                                <td>Proof of Residence (optional)</td>
                                <td><span class="doc-status-icon">⏳</span> <span class="text-muted">Optional</span></td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($upload_docs_url); ?>">Upload</a></td>
                            </tr>
                            <tr>
                                <td>Passport Photo</td>
                                <td><span class="doc-status-icon">⏳</span> <span class="text-warning">Pending</span></td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars($upload_docs_url); ?>">Upload</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Buttons Section -->
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center" data-aos="fade-up" data-aos-delay="200">
            <div class="d-flex flex-wrap gap-2">
                <a class="btn cta-btn" href="<?php echo htmlspecialchars($upload_docs_url); ?>"><i class="bi bi-upload"></i> Upload Documents</a>
                <a class="btn btn-outline-secondary" href="application-access.php"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-primary" id="downloadSummaryBtn"><i class="bi bi-filetype-pdf"></i> Download Summary</button>
                <a class="btn btn-outline-info" href="help.php"><i class="bi bi-life-preserver"></i> Support</a>
            </div>
        </div>
    </div>

<script>
async function fetchStatus() {
  try {
    const res = await fetch('api/application-status.php', { credentials: 'same-origin', cache: 'default', headers: { 'Accept': 'application/json' } });
    if (res.status === 304) return; // Respect caching
    if (!res.ok) return;
    const data = await res.json();
    render(data);
  } catch (e) { console.error('Status error', e); }
}

function render(data) {
  // Progress
  if (typeof data.application_progress === 'number') {
    const pct = Math.max(0, Math.min(100, data.application_progress));
    document.getElementById('progressPct').textContent = pct + '%';
    animateProgress(pct);
  }
  // Status badge
  if (data.application_status) {
    const badge = document.getElementById('statusBadge');
    const status = String(data.application_status).toLowerCase();
    const labelMap = {
      'pending_review': 'Application Status: PENDING REVIEW 🟡',
      'in_review': 'Application Status: IN REVIEW 🔍',
      'approved': 'Application Status: APPROVED ✅',
      'rejected': 'Application Status: REJECTED ❌',
      'completed': 'Application Status: COMPLETED ✅'
    };
    badge.textContent = (labelMap[status] || ('Application Status: ' + String(data.application_status).toUpperCase()));
    badge.classList.remove('status-pending','status-review','status-approved','status-rejected');
    if (status.includes('pending')) badge.classList.add('status-pending');
    else if (status.includes('review')) badge.classList.add('status-review');
    else if (status.includes('approved') || status.includes('completed')) badge.classList.add('status-approved');
    else if (status.includes('rejected')) badge.classList.add('status-rejected');
  }
  // Stepper
  const currentStep = typeof data.current_application_step === 'number' ? data.current_application_step : inferStepFromStatus(data.application_status);
  updateStepper(currentStep);
  // Documents table
  if (data.documents) {
    const map = { 'Certified ID Copy': 'id_copy', 'Academic Transcript': 'transcript', 'Proof of Residence (optional)': 'residence_proof', 'Passport Photo': 'passport_photo' };
    const rows = Array.from(document.querySelectorAll('#docTableBody tr'));
    rows.forEach(row => {
      const name = row.children[0].textContent.trim();
      const key = map[name];
      const st = key ? (data.documents[key] || 'pending') : 'pending';
      const statusCell = row.children[1];
      if (st === 'uploaded') {
        statusCell.innerHTML = '<span class="doc-status-icon">✅</span> <span class="text-success">Uploaded</span>';
      } else if (name.includes('optional')) {
        statusCell.innerHTML = '<span class="doc-status-icon">⏳</span> <span class="text-muted">Optional</span>';
      } else {
        statusCell.innerHTML = '<span class="doc-status-icon">⚠️</span> <span class="text-warning">Pending</span>';
      }
    });
  }
}

function animateProgress(target) {
  const bar = document.getElementById('progressFill');
  const container = bar?.parentElement;
  const current = parseInt(bar.style.width || '0', 10);
  let value = isNaN(current) ? 0 : current;
  const step = () => {
    value = value + Math.max(1, Math.round((target - value) / 10));
    if (value > target) value = target;
    bar.style.width = value + '%';
    container?.setAttribute('aria-valuenow', String(value));
    if (value < target) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

function updateStepper(stepNum) {
  [1,2,3,4].forEach(n => {
    const el = document.getElementById('step-' + n);
    el?.classList.remove('completed','current');
    if (stepNum > n) el?.classList.add('completed');
    else if (stepNum === n) el?.classList.add('current');
  });
}

function inferStepFromStatus(status) {
  const s = String(status || '').toLowerCase();
  if (s.includes('approved') || s.includes('completed')) return 4;
  if (s.includes('rejected')) return 4;
  if (s.includes('review')) return 3;
  if (s.includes('document')) return 2;
  return 1;
}

document.addEventListener('DOMContentLoaded', () => {
  fetchStatus();
  setInterval(fetchStatus, 15000);
  // Quotes rotation
  const quotes = [
    "Every great journey begins with a single step — you’re almost there!",
    "Keep pushing — success is near! 💪",
    "Progress, not perfection. You're doing great!",
    "Small steps lead to big achievements."
  ];
  const quoteEl = document.getElementById('quoteText');
  let qIdx = 0;
  setInterval(() => { qIdx = (qIdx + 1) % quotes.length; quoteEl.textContent = quotes[qIdx]; }, 8000);
  // Summary download
  document.getElementById('downloadSummaryBtn')?.addEventListener('click', () => window.print());
  // Init AOS
  AOS.init({ once: true, duration: 600 });
});
</script>
<!-- Bootstrap & AOS JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
</body>
</html>