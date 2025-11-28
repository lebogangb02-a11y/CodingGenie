<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_functions.php';

// CSRF helpers for multi-step wizard
function ensure_csrf_token() {
    if (!defined('CSRF_TOKEN_NAME')) {
        define('CSRF_TOKEN_NAME', 'csrf_token');
    }
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !is_string($_SESSION[CSRF_TOKEN_NAME]) || $_SESSION[CSRF_TOKEN_NAME] === '') {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION['csrf_generated_at'] = time();
    }
}
function regenerate_csrf_token() {
    if (!defined('CSRF_TOKEN_NAME')) {
        define('CSRF_TOKEN_NAME', 'csrf_token');
    }
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    $_SESSION['csrf_generated_at'] = time();
}
function check_csrf() {
    $key = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
    if (!isset($_POST[$key], $_SESSION[$key])) {
        throw new Exception('Invalid CSRF token');
    }
    $sent = (string)$_POST[$key];
    $sess = (string)$_SESSION[$key];
    if (!hash_equals($sess, $sent)) {
        throw new Exception('Invalid CSRF token');
    }
}

// Ensure token exists for initial render and each step
ensure_csrf_token();

// Helper: sanitize
function s($v) { return trim(filter_var($v, FILTER_SANITIZE_STRING)); }
// Lightweight SA ID DOB parser: returns 'YYYY-MM-DD' or null
function parse_sa_id_dob(string $id): ?string {
    $digits = preg_replace('/\D/', '', $id);
    if (strlen($digits) !== 13) return null;
    $yy = (int)substr($digits, 0, 2);
    $mm = (int)substr($digits, 2, 2);
    $dd = (int)substr($digits, 4, 2);
    $year = ($yy <= 49) ? (2000 + $yy) : (1900 + $yy);
    if (!checkdate($mm, $dd, $year)) return null;
    $date = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $mm, $dd));
    if (!$date) return null;
    $today = new DateTime('today');
    // Fallback: if 20xx leads to future date, switch to 19xx
    if ($date > $today) {
        $year2 = 1900 + $yy;
        if (!checkdate($mm, $dd, $year2)) return null;
        $date = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year2, $mm, $dd));
        if (!$date) return null;
    }
    return $date->format('Y-m-d');
}
// Helper: generate unique reference number
function generate_ref(PDO $pdo) {
    do {
        $ref = 'EDU' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); // e.g., EDU20251027-AB12CD
        $stmt = $pdo->prepare('SELECT id FROM applications WHERE reference_number = ? LIMIT 1');
        $stmt->execute([$ref]);
    } while ($stmt->fetch());
    return $ref;
}
// Helper: ensure application exists in session
function ensure_app(PDO $pdo) {
    if (!isset($_SESSION['application_id'])) {
        $ref = generate_ref($pdo);
        $stmt = $pdo->prepare("INSERT INTO applications (reference_number, status, application_status, step_completed, created_at, updated_at) VALUES (?, 'draft', 'draft', 0, NOW(), NOW())");
        $stmt->execute([$ref]);
        $_SESSION['reference_number'] = $ref;
        $_SESSION['application_id'] = (int)$pdo->lastInsertId();
        $_SESSION['application_status'] = 'draft';
    }
}
// Helper: fetch current application
function get_app(PDO $pdo) {
    if (!isset($_SESSION['application_id'])) return null;
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
    $stmt->execute([$_SESSION['application_id']]);
    return $stmt->fetch();
}
// Helper: calculate field-based progress across steps
function calculate_step_progress(int $step, array $data): float {
    $required = [
        1 => ['title','full_name','surname','id_number','gender','date_of_birth','email_address','cellphone_number','home_language','nationality','country_of_residence','city','province','physical_address','postal_code'],
        2 => ['school_province','high_school_name','matric_year','aps'],
        3 => ['maths_level','english_level','exam_number','institution_choice_1','program_choice_1','institution_choice_2','program_choice_2','institution_choice_3','program_choice_3']
    ];
    if (!isset($required[$step])) return 0.0;
    $fields = $required[$step];
    $done = 0; $total = count($fields);
    foreach ($fields as $f) {
        $v = isset($data[$f]) ? trim((string)$data[$f]) : '';
        if ($v !== '') { $done++; }
    }
    if ($total === 0) return 0.0;
    return round(($done / $total) * 100, 2);
}

// Ensure a column exists on a table (minimal migration helper)
function ensure_column_exists(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `".$table."` LIKE ?");
        $stmt->execute([$column]);
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `".$table."` ADD COLUMN `".$column."` " . $definition);
        }
    } catch (Throwable $e) {
        // Best-effort: ignore if cannot alter (shared hosting / permissions)
    }
}

// Helper: compute overall progress (field-based + documents + submit)
function compute_overall_progress(PDO $pdo, array $appRow): int {
    $p1 = calculate_step_progress(1, $appRow);
    $p2 = calculate_step_progress(2, $appRow);
    $p3 = calculate_step_progress(3, $appRow);

    // Documents progress: check available uploads
    $docsPct = 0;
    try {
        $appId = (int)($appRow['id'] ?? 0);
        if ($appId > 0) {
            $requiredDocsA = ['certified_id','academic_results'];
            $requiredDocsB = ['id_document','matric_certificate','academic_transcript'];
            $uploaded = [];
            try {
                $sA = $pdo->prepare('SELECT document_type FROM application_documents WHERE application_id = ?');
                $sA->execute([$appId]);
                foreach ($sA->fetchAll(PDO::FETCH_COLUMN) as $t) { $uploaded[] = strtolower(trim((string)$t)); }
            } catch (Throwable $e) { /* ignore */ }
            try {
                $sB = $pdo->prepare('SELECT doc_type FROM documents WHERE application_id = ?');
                $sB->execute([$appId]);
                foreach ($sB->fetchAll(PDO::FETCH_COLUMN) as $t) { $uploaded[] = strtolower(trim((string)$t)); }
            } catch (Throwable $e) { /* ignore */ }
            $req = (count(array_intersect($uploaded, $requiredDocsB)) > 0) ? $requiredDocsB : $requiredDocsA;
            if (count($req) > 0) {
                $count = count(array_intersect($req, $uploaded));
                $docsPct = round(($count / count($req)) * 100, 2);
            }
        }
    } catch (Throwable $e) { /* keep docsPct = 0 */ }

    // Submission progress: signature + terms + privacy
    $submitPct = 0;
    $sigOk = !empty($appRow['signature']);
    $termsOk = !empty($appRow['terms_conditions']);
    $privacyOk = !empty($appRow['privacy_policy']);
    if ($sigOk && $termsOk && $privacyOk) { $submitPct = 100; }

    // Academic combined = average of Step 2 and Step 3
    $pAcademic = ($p2 + $p3) / 2;
    // Weighted sum: Personal (40), Academic (40), Documents (15), Submit (5)
    $overall = (0.40 * $p1) + (0.40 * $pAcademic) + (0.15 * $docsPct) + (0.05 * $submitPct);
    $overall = (int)round(max(0, min(100, $overall)));
    return $overall;
}

// Helper: push progress into session
function update_session_progress(PDO $pdo) {
    $app = get_app($pdo);
    if (!$app) return;
    $_SESSION['application_progress'] = compute_overall_progress($pdo, $app);
    $_SESSION['current_application_step'] = isset($_GET['step']) ? (int)$_GET['step'] : 1;
    $_SESSION['application_last_updated'] = time();
}
// Helper: create upload dirs if missing
function ensure_upload_dirs() {
    $dirs = [UPLOAD_DIR . 'id_documents', UPLOAD_DIR . 'matric_certificates', UPLOAD_DIR . 'proof_of_residence'];
    foreach ($dirs as $d) { if (!is_dir($d)) @mkdir($d, 0775, true); }
}
// Helper: handle file upload
function handle_upload($field, $subdir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error for ' . $field);
    if ($file['size'] > MAX_FILE_SIZE) throw new Exception('File too large for ' . $field);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_FILE_TYPES)) throw new Exception('Invalid file type for ' . $field);
    $mime = mime_content_type($file['tmp_name']);
    $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($mime, $allowed_mime)) throw new Exception('Invalid file format for ' . $field);
    ensure_upload_dirs();
    $name = ($_SESSION['reference_number'] ?? 'APP') . '-' . $field . '-' . time() . '.' . $ext;
    $dest = UPLOAD_DIR . $subdir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Failed to store file for ' . $field);
    return $dest; // relative path under uploads/
}

// (Removed duplicate check_csrf; using the hash_equals-based version declared at top)

// Determine step
$step = isset($_GET['step']) ? max(1, min(5, (int)$_GET['step'])) : 1;
$errors = [];
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF for every submission
        check_csrf();
        ensure_app($pdo);
        $action = $_POST['action'] ?? 'next';
        $nextStep = $step;

        if ($step === 1) {
            $title = s($_POST['title'] ?? '');
            $full_name = s($_POST['full_name'] ?? '');
            $surname = s($_POST['surname'] ?? '');
            $id_number = s($_POST['id_number'] ?? '');
            $gender = s($_POST['gender'] ?? '');
            $date_of_birth = s($_POST['date_of_birth'] ?? '');
            // New fields: education status and parent/guardian ID
            $education_status = s($_POST['education_status'] ?? '');
            $parent_id_number = s($_POST['parent_id'] ?? '');
            // Enforce DOB extraction from SA ID when empty
            if ($date_of_birth === '' && preg_match('/^\d{13}$/', $id_number)) {
                $parsedDob = parse_sa_id_dob($id_number);
                if ($parsedDob) { $date_of_birth = $parsedDob; }
            }
            $email_address = s($_POST['email_address'] ?? '');
            $cellphone_number = s($_POST['cellphone_number'] ?? '');
            if (!$full_name || !$surname || !$email_address || !$cellphone_number) throw new Exception('Please fill all required fields.');
            if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email address.');
            if (!preg_match('/^\+?\d{10,15}$/', $cellphone_number)) throw new Exception('Invalid cellphone number.');
            // Validate education_status selection if provided (normalized values)
            $allowedEdu = ['grade12','completed','other'];
            if ($education_status !== '' && !in_array($education_status, $allowedEdu, true)) {
                throw new Exception('Invalid education status selection.');
            }
            // Basic ID number sanity check (optional but helpful)
            if ($id_number !== '' && !preg_match('/^\d{13}$/', $id_number)) {
                throw new Exception('ID Number must be 13 digits.');
            }
            // If applicant is under 18, require parent/guardian ID number
            $requireParentId = false;
            try {
                if ($date_of_birth !== '') {
                    $dob = new DateTime($date_of_birth);
                    $today = new DateTime('today');
                    $age = (int)$dob->diff($today)->y;
                    if ($age < 18) { $requireParentId = true; }
                }
            } catch (Throwable $e) { /* ignore parse errors; client-side should auto-fill */ }
            if ($requireParentId) {
                if ($parent_id_number === '' || !preg_match('/^\d{13}$/', $parent_id_number)) {
                    throw new Exception('Parent/Guardian ID is required for applicants under 18 and must be 13 digits.');
                }
            }
            $home_language = s($_POST['home_language'] ?? '');
            $nationality = s($_POST['nationality'] ?? '');
            $country_of_residence = s($_POST['country_of_residence'] ?? '');
            $city = s($_POST['city'] ?? '');
            $province = s($_POST['province'] ?? '');
            $physical_address = s($_POST['physical_address'] ?? '');
            $postal_code = s($_POST['postal_code'] ?? '');
            if (!$province || !$physical_address || !$postal_code || !$country_of_residence) throw new Exception('Please complete address details.');
            // Ensure columns exist for new fields (best-effort)
            ensure_column_exists($pdo, 'applications', 'education_status', 'VARCHAR(30) DEFAULT NULL');
            ensure_column_exists($pdo, 'applications', 'parent_id_number', 'VARCHAR(20) DEFAULT NULL');
            // Persist including new fields; fallback if host disallows column changes
            try {
                $stmt = $pdo->prepare("UPDATE applications SET title=?, full_name=?, surname=?, id_number=?, gender=?, date_of_birth=?, education_status=?, parent_id_number=?, email_address=?, cellphone_number=?, home_language=?, nationality=?, country_of_residence=?, city=?, province=?, physical_address=?, postal_code=?, updated_at=NOW(), step_completed=GREATEST(step_completed,1) WHERE id=?");
                $stmt->execute([$title,$full_name,$surname,$id_number,$gender,$date_of_birth,$education_status,$parent_id_number,$email_address,$cellphone_number,$home_language,$nationality,$country_of_residence,$city,$province,$physical_address,$postal_code,$_SESSION['application_id']]);
            } catch (Throwable $e) {
                $stmt = $pdo->prepare("UPDATE applications SET title=?, full_name=?, surname=?, id_number=?, gender=?, date_of_birth=?, email_address=?, cellphone_number=?, home_language=?, nationality=?, country_of_residence=?, city=?, province=?, physical_address=?, postal_code=?, updated_at=NOW(), step_completed=GREATEST(step_completed,1) WHERE id=?");
                $stmt->execute([$title,$full_name,$surname,$id_number,$gender,$date_of_birth,$email_address,$cellphone_number,$home_language,$nationality,$country_of_residence,$city,$province,$physical_address,$postal_code,$_SESSION['application_id']]);
            }
            update_session_progress($pdo);
            $nextStep = 2;
        } elseif ($step === 2) {
            // Step 2 now captures High School details
            $school_province = s($_POST['school_province'] ?? '');
            $high_school_name = s($_POST['high_school_name'] ?? '');
            $school_type = s($_POST['school_type'] ?? '');
            $previous_quals = s($_POST['previous_quals'] ?? '');
            $household_income = (int)($_POST['household_income'] ?? 0);
            $academic_performance = s($_POST['academic_performance'] ?? '');
            $matric_year = (int)($_POST['matric_year'] ?? 0);
            $aps = (int)($_POST['aps'] ?? 0);
            if (!$school_province) throw new Exception('Please select your school province.');
            $validProv = ['Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga','Northern Cape','North West','Western Cape'];
            if (!in_array($school_province, $validProv, true)) throw new Exception('Invalid school province selection.');
            if (!$high_school_name) throw new Exception('Please select your high school.');
            // Validate school type if provided
            $validSchoolTypes = ['public','private','international'];
            if ($school_type !== '' && !in_array($school_type, $validSchoolTypes, true)) {
                throw new Exception('Invalid school type.');
            }
            // Validate bursary-related fields if provided
            if ($household_income < 0 || $household_income > 1000000000) {
                throw new Exception('Please provide a valid annual household income.');
            }
            $validPerf = ['average','good','excellent',''];
            if (!in_array(strtolower($academic_performance), $validPerf, true)) {
                throw new Exception('Invalid academic performance value.');
            }
            $currentYear = (int)date('Y');
            if ($matric_year < 1980 || $matric_year > $currentYear) throw new Exception('Please provide a valid matric year.');
            if ($aps < 0 || $aps > 42) throw new Exception('APS must be between 0 and 42.');
            // Ensure 'school_province' column exists (safe on hosts where allowed)
            ensure_column_exists($pdo, 'applications', 'school_province', 'VARCHAR(100) DEFAULT NULL');
            ensure_column_exists($pdo, 'applications', 'school_type', 'VARCHAR(30) DEFAULT NULL');
            ensure_column_exists($pdo, 'applications', 'previous_quals', 'TEXT DEFAULT NULL');
            ensure_column_exists($pdo, 'applications', 'household_income', 'INT UNSIGNED DEFAULT NULL');
            ensure_column_exists($pdo, 'applications', 'academic_performance', 'VARCHAR(20) DEFAULT NULL');
            try {
                $stmt = $pdo->prepare("UPDATE applications SET school_province=?, high_school_name=?, school_type=?, previous_quals=?, household_income=?, academic_performance=?, matric_year=?, aps=?, updated_at=NOW(), step_completed=GREATEST(step_completed,2) WHERE id=?");
                $stmt->execute([$school_province,$high_school_name,$school_type,$previous_quals,$household_income,strtolower($academic_performance),$matric_year,$aps,$_SESSION['application_id']]);
            } catch (Throwable $e) {
                // Fallback without school_province if column addition failed
                $stmt = $pdo->prepare("UPDATE applications SET high_school_name=?, matric_year=?, aps=?, updated_at=NOW(), step_completed=GREATEST(step_completed,2) WHERE id=?");
                $stmt->execute([$high_school_name,$matric_year,$aps,$_SESSION['application_id']]);
            }
            update_session_progress($pdo);
            $nextStep = 3;
        } elseif ($step === 3) {
            $maths_level = s($_POST['maths_level'] ?? '');
            $english_level = s($_POST['english_level'] ?? '');
            $exam_number = s($_POST['exam_number'] ?? '');
            $institution_choice_1 = s($_POST['institution_choice_1'] ?? '');
            $program_choice_1 = s($_POST['program_choice_1'] ?? '');
            $institution_choice_2 = s($_POST['institution_choice_2'] ?? '');
            $program_choice_2 = s($_POST['program_choice_2'] ?? '');
            $institution_choice_3 = s($_POST['institution_choice_3'] ?? '');
            $program_choice_3 = s($_POST['program_choice_3'] ?? '');
            $program_specialization_1 = s($_POST['program_specialization_1'] ?? '');
            $program_other_comment_1 = s($_POST['program_other_comment_1'] ?? '');
            $program_specialization_2 = s($_POST['program_specialization_2'] ?? '');
            $program_other_comment_2 = s($_POST['program_other_comment_2'] ?? '');
            $program_specialization_3 = s($_POST['program_specialization_3'] ?? '');
            $program_other_comment_3 = s($_POST['program_other_comment_3'] ?? '');
            if (!$institution_choice_1 || !$program_choice_1) throw new Exception('Select an institution and program for your first choice.');
            // Optional choices: if university or course is provided, require both
            if (($institution_choice_2 && !$program_choice_2) || (!$institution_choice_2 && $program_choice_2)) {
                throw new Exception('For your second choice, please select both a university and a course or leave both blank.');
            }
            if (($institution_choice_3 && !$program_choice_3) || (!$institution_choice_3 && $program_choice_3)) {
                throw new Exception('For your third choice, please select both a university and a course or leave both blank.');
            }
            // Duplicate prevention: disallow identical university+course combos across choices
            $combos = [];
            $norm = function($u,$c){ return strtolower(trim($u)).'|'.strtolower(trim($c)); };
            $combos[] = $norm($institution_choice_1,$program_choice_1);
            if ($institution_choice_2 && $program_choice_2) {
                $c2 = $norm($institution_choice_2,$program_choice_2);
                foreach ($combos as $cx) { if ($cx === $c2) throw new Exception('Your second choice duplicates another selection. Please choose a different university/course combination.'); }
                $combos[] = $c2;
            }
            if ($institution_choice_3 && $program_choice_3) {
                $c3 = $norm($institution_choice_3,$program_choice_3);
                foreach ($combos as $cx) { if ($cx === $c3) throw new Exception('Your third choice duplicates another selection. Please choose a different university/course combination.'); }
                $combos[] = $c3;
            }
            $stmt = $pdo->prepare("UPDATE applications SET maths_level=?, english_level=?, exam_number=?, institution_choice_1=?, program_choice_1=?, institution_choice_2=?, program_choice_2=?, institution_choice_3=?, program_choice_3=?, program_specialization_1=?, program_other_comment_1=?, program_specialization_2=?, program_other_comment_2=?, program_specialization_3=?, program_other_comment_3=?, updated_at=NOW(), step_completed=GREATEST(step_completed,3) WHERE id=?");
            $stmt->execute([$maths_level,$english_level,$exam_number,$institution_choice_1,$program_choice_1,$institution_choice_2,$program_choice_2,$institution_choice_3,$program_choice_3,$program_specialization_1,$program_other_comment_1,$program_specialization_2,$program_other_comment_2,$program_specialization_3,$program_other_comment_3,$_SESSION['application_id']]);
            update_session_progress($pdo);
            $nextStep = 4;
        } elseif ($step === 4) {
            // Review step: no persistence, advance to submit
            update_session_progress($pdo);
            $nextStep = 5;
        } elseif ($step === 5) {
            $signature = s($_POST['signature'] ?? '');
            $terms = isset($_POST['terms_conditions']) ? 1 : 0;
            $privacy = isset($_POST['privacy_policy']) ? 1 : 0;
            if (!$signature || !$terms || !$privacy) throw new Exception('Please sign and accept terms & privacy.');
            $stmt = $pdo->prepare("UPDATE applications SET signature=?, terms_conditions=?, privacy_policy=?, status='Submitted (without docs)', application_status='submitted', submitted_at=NOW(), updated_at=NOW(), step_completed=5 WHERE id=?");
            $stmt->execute([$signature,$terms,$privacy,$_SESSION['application_id']]);
            update_session_progress($pdo);
            // Fetch fresh application data for email
            $stmt2 = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
            $stmt2->execute([$_SESSION['application_id']]);
            $appForEmail = $stmt2->fetch(PDO::FETCH_ASSOC);

            // Attempt to send confirmation email
            $emailResult = null;
            if ($appForEmail && !empty($appForEmail['email_address'])) {
                $emailResult = sendApplicationConfirmationEmail($appForEmail, $_SESSION['reference_number']);
            }

            // Pass email status to success page
            $emailFlag = (is_array($emailResult) && !empty($emailResult['success'])) ? '1' : '0';
            header('Location: application_success.php?ref=' . urlencode($_SESSION['reference_number']) . '&email_sent=' . $emailFlag);
            exit;
        }

        // Rotate CSRF token after successful handling to prevent replay
        regenerate_csrf_token();

        if ($action === 'save') {
            $success = 'Draft saved.';
        } else {
            header('Location: edubridge_wizard.php?step=' . $nextStep);
            exit;
        }

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Progress (field-based + documents + submit)
$app = get_app($pdo);
if ($app) {
    $progress = compute_overall_progress($pdo, $app);
} else {
    $progress = (int)round(($step / 5) * 100);
}
update_session_progress($pdo);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>EduBridgeSA Application Wizard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>.step{max-width:820px;margin:auto}.required::after{content:' *';color:#d00}.footer-btns{display:flex;gap:.5rem;justify-content:space-between}</style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="h3 mb-3">EduBridgeSA – University Application</h1>
    <div class="progress mb-4" style="height:10px"><div class="progress-bar" role="progressbar" style="width: <?=$progress?>%" aria-valuenow="<?=$progress?>" aria-valuemin="0" aria-valuemax="100"></div></div>
    <?php if ($errors): ?><div class="alert alert-danger"><?php foreach($errors as $err){echo '<div>'.htmlspecialchars($err).'</div>'; }?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>

    <div class="card step">
        <div class="card-header">Step <?=$step?> of 5</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="<?=CSRF_TOKEN_NAME?>" value="<?=htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '')?>">
                <?php if ($step===1): ?>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Title</label>
                            <select name="title" class="form-select">
                                <?php $titles=['','Mr','Ms','Mrs','Dr']; $cur=$app['title']??''; foreach($titles as $t){ $sel=$cur===$t?'selected':''; echo "<option value=\"$t\" $sel>".($t?:'Select Title')."</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label required">First Name</label><input name="full_name" class="form-control" required value="<?=htmlspecialchars($app['full_name'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label required">Surname</label><input name="surname" class="form-control" required value="<?=htmlspecialchars($app['surname'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label">ID Number</label><input id="id_number" name="id_number" class="form-control" value="<?=htmlspecialchars($app['id_number'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">Select</option><option value="Male" <?=(($app['gender']??'')==='Male'?'selected':'')?>>Male</option><option value="Female" <?=(($app['gender']??'')==='Female'?'selected':'')?>>Female</option><option value="Other" <?=(($app['gender']??'')==='Other'?'selected':'')?>>Other</option></select></div>
                        <div class="col-md-6"><label class="form-label">Date of Birth</label><input id="dob" type="date" name="date_of_birth" class="form-control" value="<?=htmlspecialchars($app['date_of_birth'] ?? '')?>"></div>
                        <div class="col-12" id="parent_id_section" style="display: none;">
                            <label class="form-label">Parent/Guardian ID Number *</label>
                            <input type="text" name="parent_id" class="form-control" placeholder="Required for applicants under 18">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Current Education Status *</label>
                            <select name="education_status" class="form-select" required>
                                <option value="">Select</option>
                                <option value="grade12" <?=(($app['education_status']??'')==='grade12'?'selected':'')?>>In Grade 12</option>
                                <option value="completed" <?=(($app['education_status']??'')==='completed'?'selected':'')?>>Completed Matric</option>
                                <option value="other" <?=(($app['education_status']??'')==='other'?'selected':'')?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label required">Email Address</label><input type="email" name="email_address" class="form-control" required value="<?=htmlspecialchars($app['email_address'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label required">Cellphone Number</label><input name="cellphone_number" class="form-control" required value="<?=htmlspecialchars($app['cellphone_number'] ?? '')?>" placeholder="e.g. 0712345678"></div>
                        <div class="col-md-6"><label class="form-label">Home Language</label><input name="home_language" class="form-control" value="<?=htmlspecialchars($app['home_language'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label">Nationality</label><input name="nationality" class="form-control" value="<?=htmlspecialchars($app['nationality'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label required">Country of Residence</label><input name="country_of_residence" class="form-control" required value="<?=htmlspecialchars($app['country_of_residence'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" value="<?=htmlspecialchars($app['city'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label required">Province</label><select name="province" class="form-select" required>
                            <?php $provList=['Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga','Northern Cape','North West','Western Cape']; $cur=$app['province']??''; echo '<option value="">Select Province</option>'; foreach($provList as $p){$sel=$cur===$p?'selected':''; echo "<option $sel>$p</option>"; } ?>
                        </select></div>
                        <div class="col-md-6"><label class="form-label required">Address</label><input name="physical_address" class="form-control" required value="<?=htmlspecialchars($app['physical_address'] ?? '')?>"></div>
                        <div class="col-md-6"><label class="form-label required">Postal Code</label><input name="postal_code" class="form-control" required value="<?=htmlspecialchars($app['postal_code'] ?? '')?>"></div>
                    </div>
                <?php elseif ($step===2): ?>
                    <div class="row g-3">
                        <div class="col-12"><h5>High School Information</h5></div>
                        <div class="col-md-4">
                            <label class="form-label required">Province (for school)</label>
                            <select id="school_province" name="school_province" class="form-select" required>
                                <option value="">Select Province</option>
                                <?php $provList=['Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga','Northern Cape','North West','Western Cape']; $cur=($app['school_province'] ?? ''); foreach($provList as $p){ $sel=$cur===$p?'selected':''; echo "<option value=\"$p\" $sel>$p</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">High School</label>
                            <select id="high_school_name" name="high_school_name" class="form-select" required>
                                <option value="">Select High School</option>
                            </select>
                            <div class="form-text">Use the dropdown to search by name.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">School Type *</label>
                            <select name="school_type" class="form-select" required>
                                <?php $cur=($app['school_type'] ?? ''); ?>
                                <option value="">Select</option>
                                <option value="public" <?=($cur==='public'?'selected':'')?>>Public School</option>
                                <option value="private" <?=($cur==='private'?'selected':'')?>>Private School</option>
                                <option value="international" <?=($cur==='international'?'selected':'')?>>International School</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Matric Year</label>
                            <select name="matric_year" id="matric_year" class="form-select" required>
                                <option value="">Select Year</option>
                                <?php $currentYear=(int)date('Y'); for($y=$currentYear;$y>=1980;$y--){ $sel=((int)($app['matric_year']??0) === $y)?'selected':''; echo "<option value=\"$y\" $sel>$y</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">APS Score</label>
                            <input type="number" name="aps" id="aps_score" class="form-control" min="0" max="42" required value="<?=htmlspecialchars($app['aps'] ?? '')?>" placeholder="0–42">
                            <small class="text-muted">Admission Point Score</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Annual Household Income</label>
                            <input type="number" name="household_income" class="form-control" min="0" step="1000" value="<?=htmlspecialchars($app['household_income'] ?? '')?>" placeholder="Annual Household Income">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Academic Performance</label>
                            <?php $perf= strtolower($app['academic_performance'] ?? ''); ?>
                            <select name="academic_performance" class="form-select">
                                <option value="">Select</option>
                                <option value="average" <?=($perf==='average'?'selected':'')?>>Average</option>
                                <option value="good" <?=($perf==='good'?'selected':'')?>>Good</option>
                                <option value="excellent" <?=($perf==='excellent'?'selected':'')?>>Excellent</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div id="application_fee_display" class="alert alert-secondary">Application Fee: R0</div>
                        </div>
                        <div class="col-12" id="previous_qualifications">
                            <label class="form-label">Previous Qualifications</label>
                            <textarea name="previous_quals" class="form-control" placeholder="List any previous qualifications"><?=htmlspecialchars($app['previous_quals'] ?? '')?></textarea>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info">Document upload has moved to the dedicated <strong>Upload Documents</strong> page. Continue when ready.</div>
                        </div>
                    </div>
                <?php elseif ($step===3): ?>
                    <div class="row g-3">
                        <div class="col-12"><h6>First Choice (Required)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label required">University</label>
                            <select id="institution_choice_1" name="institution_choice_1" class="form-select" required>
                                <option value="">Select University</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course Category</label>
                            <select id="course_category" class="form-select">
                                <option value="">All Categories</option>
                                <?php $cats=['Education','Business','IT & Computing','Health & Sciences','Engineering','Humanities','Art & Design']; foreach($cats as $c){ echo "<option>$c</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">Course / Programme</label>
                            <select id="program_choice_1" name="program_choice_1" class="form-select" required>
                                <option value="">Select Course</option>
                            </select>
                            <small id="dup_msg_1" class="text-danger" style="display:none;"></small>
                        </div>
                        <div class="col-md-6"><label class="form-label">Specialization</label>
                            <select name="program_specialization_1" id="program_specialization_1" class="form-select">
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-12"><h6>Second Choice (Optional)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label">University</label>
                            <select id="institution_choice_2" name="institution_choice_2" class="form-select">
                                <option value="">Select University</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course Category</label>
                            <select id="course_category_2" class="form-select">
                                <option value="">All Categories</option>
                                <?php $cats=['Education','Business','IT & Computing','Health & Sciences','Engineering','Humanities','Art & Design']; foreach($cats as $c){ echo "<option>$c</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Course / Programme</label>
                            <select id="program_choice_2" name="program_choice_2" class="form-select">
                                <option value="">Select Course</option>
                            </select>
                            <small id="dup_msg_2" class="text-danger" style="display:none;"></small>
                        </div>
                        <div class="col-md-6"><label class="form-label">Specialization</label>
                            <select name="program_specialization_2" id="program_specialization_2" class="form-select">
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                        <div class="col-12"><hr></div>
                        <div class="col-12"><h6>Third Choice (Optional)</h6></div>
                        <div class="col-md-6">
                            <label class="form-label">University</label>
                            <select id="institution_choice_3" name="institution_choice_3" class="form-select">
                                <option value="">Select University</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course Category</label>
                            <select id="course_category_3" class="form-select">
                                <option value="">All Categories</option>
                                <?php $cats=['Education','Business','IT & Computing','Health & Sciences','Engineering','Humanities','Art & Design']; foreach($cats as $c){ echo "<option>$c</option>"; } ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Course / Programme</label>
                            <select id="program_choice_3" name="program_choice_3" class="form-select">
                                <option value="">Select Course</option>
                            </select>
                            <small id="dup_msg_3" class="text-danger" style="display:none;"></small>
                        </div>
                        <div class="col-md-6"><label class="form-label">Specialization</label>
                            <select name="program_specialization_3" id="program_specialization_3" class="form-select">
                                <option value="">Select Specialization</option>
                            </select>
                        </div>
                        <div class="col-12"><hr></div>
                    </div>
                <?php elseif ($step===4): ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-info">Please review your details before submitting.</div>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tbody>
                                        <tr><th>Full Name</th><td><?=htmlspecialchars(($app['title'] ?? '') ? ($app['title'].' ') : '')?><?=htmlspecialchars($app['full_name'] ?? '')?> <?=htmlspecialchars($app['surname'] ?? '')?></td></tr>
                                        <tr><th>Email</th><td><?=htmlspecialchars($app['email_address'] ?? '')?></td></tr>
                                        <tr><th>Cellphone</th><td><?=htmlspecialchars($app['cellphone_number'] ?? '')?></td></tr>
                                        <tr><th>Home Language</th><td><?=htmlspecialchars($app['home_language'] ?? '')?></td></tr>
                                        <tr><th>Nationality</th><td><?=htmlspecialchars($app['nationality'] ?? '')?></td></tr>
                                        <tr><th>Province</th><td><?=htmlspecialchars($app['province'] ?? '')?></td></tr>
                                        <tr><th>School Province</th><td><?=htmlspecialchars($app['school_province'] ?? '')?></td></tr>
                                        <tr><th>City</th><td><?=htmlspecialchars($app['city'] ?? '')?></td></tr>
                                        <tr><th>Country of Residence</th><td><?=htmlspecialchars($app['country_of_residence'] ?? '')?></td></tr>
                                        <tr><th>Address</th><td><?=htmlspecialchars($app['physical_address'] ?? '')?></td></tr>
                                        <tr><th>Postal Code</th><td><?=htmlspecialchars($app['postal_code'] ?? '')?></td></tr>
                                        <tr><th>High School</th><td><?=htmlspecialchars($app['high_school_name'] ?? '')?></td></tr>
                                        <tr><th>Matric Year</th><td><?=htmlspecialchars($app['matric_year'] ?? '')?></td></tr>
                                        <tr><th>APS</th><td><?=htmlspecialchars($app['aps'] ?? '')?></td></tr>
                                        <tr><th>Maths Level</th><td><?=htmlspecialchars($app['maths_level'] ?? '')?></td></tr>
                                        <tr><th>English Level</th><td><?=htmlspecialchars($app['english_level'] ?? '')?></td></tr>
                                        <tr><th>Exam Number</th><td><?=htmlspecialchars($app['exam_number'] ?? '')?></td></tr>
                                        <tr><th>University</th><td><?=htmlspecialchars($app['institution_choice_1'] ?? '')?></td></tr>
                                        <tr><th>Course</th><td><?=htmlspecialchars($app['program_choice_1'] ?? '')?></td></tr>
                                        <tr><th>First Choice Specialization</th><td><?=htmlspecialchars($app['program_specialization_1'] ?? '')?></td></tr>
                                        <tr><th>Second Choice University</th><td><?=htmlspecialchars($app['institution_choice_2'] ?? '')?></td></tr>
                                        <tr><th>Second Choice Course</th><td><?=htmlspecialchars($app['program_choice_2'] ?? '')?></td></tr>
                                        <tr><th>Second Choice Specialization</th><td><?=htmlspecialchars($app['program_specialization_2'] ?? '')?></td></tr>
                                        <tr><th>Third Choice University</th><td><?=htmlspecialchars($app['institution_choice_3'] ?? '')?></td></tr>
                                        <tr><th>Third Choice Course</th><td><?=htmlspecialchars($app['program_choice_3'] ?? '')?></td></tr>
                                        <tr><th>Third Choice Specialization</th><td><?=htmlspecialchars($app['program_specialization_3'] ?? '')?></td></tr>
                                        <tr><th>Reference</th><td><?=htmlspecialchars($_SESSION['reference_number'] ?? '')?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted">Use Previous to revise any section, then Next to continue.</p>
                        </div>
                    </div>
                <?php elseif ($step===5): ?>
                    <div class="mb-3"><label class="form-label required">Signature (type your full name)</label><input name="signature" class="form-control" required value="<?=htmlspecialchars($app['signature'] ?? '')?>"></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="terms" name="terms_conditions" required <?=(($app['terms_conditions']??0)?'checked':'')?>><label for="terms" class="form-check-label">I agree to the Terms & Conditions</label></div>
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="privacy" name="privacy_policy" required <?=(($app['privacy_policy']??0)?'checked':'')?>><label for="privacy" class="form-check-label">I agree to the Privacy Policy</label></div>
                    <p class="text-muted">Your reference number: <strong><?=htmlspecialchars($_SESSION['reference_number'] ?? 'Pending')?></strong></p>
                <?php endif; ?>

                <div class="footer-btns mt-4">
                    <div>
                        <?php if ($step>1): ?><a class="btn btn-outline-secondary" href="edubridge_wizard.php?step=<?=($step-1)?>">Previous</a><?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save" class="btn btn-secondary">Save as Draft</button>
                        <?php if ($step<5): ?><button type="submit" name="action" value="next" class="btn btn-primary">Next</button><?php else: ?><button type="submit" name="action" value="submit" class="btn btn-success">Submit Application</button><?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="text-center mt-3 text-muted">Progress: <?=$progress?>%</div>
</div>
<script>
window.INIT_UNI="<?= addslashes($app['institution_choice_1'] ?? '') ?>";
window.INIT_COURSE="<?= addslashes($app['program_choice_1'] ?? '') ?>";
window.INIT_SCHOOL="<?= addslashes($app['high_school_name'] ?? '') ?>";
window.INIT_UNI_2="<?= addslashes($app['institution_choice_2'] ?? '') ?>";
window.INIT_COURSE_2="<?= addslashes($app['program_choice_2'] ?? '') ?>";
window.INIT_UNI_3="<?= addslashes($app['institution_choice_3'] ?? '') ?>";
window.INIT_COURSE_3="<?= addslashes($app['program_choice_3'] ?? '') ?>";
</script>
<script src="assets/wizard_linked_dropdowns.js?v=11"></script>
<script>
// Real-time SA ID parsing with 2000+ century handling
document.addEventListener('DOMContentLoaded', function(){
  function parseSaIdDob(id){
    var digits = String(id).replace(/\D/g,'');
    if(digits.length !== 13) return null;
    var yy = parseInt(digits.slice(0,2),10);
    var mm = parseInt(digits.slice(2,4),10);
    var dd = parseInt(digits.slice(4,6),10);
    var year = (yy <= 49 ? 2000 + yy : 1900 + yy);
    // Build date and adjust if in the future
    var y = year.toString().padStart(4,'0');
    var m = String(mm).padStart(2,'0');
    var d = String(dd).padStart(2,'0');
    var iso = y+'-'+m+'-'+d;
    var dt = new Date(iso);
    if(isNaN(dt.getTime())) return null;
    var today = new Date(); today.setHours(0,0,0,0);
    if(dt > today){
      var y2 = (1900 + yy).toString();
      iso = y2+'-'+m+'-'+d;
      dt = new Date(iso);
      if(isNaN(dt.getTime())) return null;
    }
    return iso;
  }

  function calcAgeFromIso(iso){
    var dt = new Date(iso);
    if(isNaN(dt.getTime())) return null;
    var today = new Date();
    var age = today.getFullYear() - dt.getFullYear();
    var m = today.getMonth() - dt.getMonth();
    if(m < 0 || (m === 0 && today.getDate() < dt.getDate())) age--;
    return age;
  }

  var idInput = document.getElementById('id_number');
  var dobEl = document.getElementById('dob');
  var parentSection = document.getElementById('parent_id_section');
  if(!idInput) return;

  function handleIdChange(val){
    var iso = parseSaIdDob(val);
    if(!iso) return;
    if(dobEl){ dobEl.value = iso; }
    var age = calcAgeFromIso(iso);
    if(parentSection && age !== null){ parentSection.style.display = (age < 18 ? 'block' : 'none'); }
  }

  idInput.addEventListener('input', function(e){ handleIdChange(e.target.value.trim()); });
  idInput.addEventListener('blur', function(e){ handleIdChange(e.target.value.trim()); });
});
</script>
<script>
// Form submission routing: handles applicant types and conditions
document.addEventListener('DOMContentLoaded', function(){
  function calculateAgeFromID(id){
    var digits = String(id || '').replace(/\D/g,'');
    if(digits.length !== 13) return 0;
    var yy = parseInt(digits.slice(0,2),10);
    var mm = parseInt(digits.slice(2,4),10) - 1;
    var dd = parseInt(digits.slice(4,6),10);
    var year = (yy <= 49 ? 2000 + yy : 1900 + yy);
    var birthDate = new Date(year, mm, dd);
    if(isNaN(birthDate.getTime())) return 0;
    var today = new Date();
    var age = today.getFullYear() - birthDate.getFullYear();
    var m = today.getMonth() - birthDate.getMonth();
    if(m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
    return age;
  }

  var form = document.querySelector('form');
  if(!form) return;
  form.addEventListener('submit', function(e){
    var eduEl = document.querySelector('[name="education_status"]');
    var educationStatus = eduEl ? String(eduEl.value || '').toLowerCase() : '';
    var idVal = (document.getElementById('id_number')?.value || '').trim();
    var age = calculateAgeFromID(idVal);
    var schoolEl = document.querySelector('[name="school_type"]');
    var schoolType = schoolEl ? String(schoolEl.value || '').toLowerCase() : '';

    // Non-Grade 12 applicants go to payment
    if(educationStatus && educationStatus !== 'grade12'){
      e.preventDefault();
      window.location.href = '/payment.php?type=non_grade12';
      return;
    }
    // Private school students special handling
    if(schoolType === 'private'){
      e.preventDefault();
      window.location.href = '/private_school_processing.php';
      return;
    }
    // Under 18 requires parent/guardian ID
    if(age > 0 && age < 18){
      var parentID = document.querySelector('[name="parent_id"]');
      if(!parentID || !parentID.value || parentID.value.replace(/\D/g,'').length !== 13){
        e.preventDefault();
        alert('Certified parent/guardian ID required for applicants under 18');
        return;
      }
    }
  });
});
</script>
<script>
// Bursary eligibility check
function checkBursaryEligibility(){
  var incomeEl = document.querySelector('[name="household_income"]');
  var perfEl = document.querySelector('[name="academic_performance"]');
  if(!incomeEl || !perfEl) return;
  var householdIncome = parseInt(incomeEl.value || '0', 10);
  var academicPerformance = String(perfEl.value || '').toLowerCase();
  if(householdIncome < 350000 && academicPerformance === 'excellent'){
    showBursaryOption();
  }
}
function showBursaryOption(){
  var form = document.querySelector('form');
  if(!form) return;
  if(document.querySelector('.bursary-alert')) return; // avoid duplicates
  var bursarySection = document.createElement('div');
  bursarySection.className = 'bursary-alert alert alert-success mt-2';
  bursarySection.innerHTML = '<strong>You may qualify for a bursary!</strong> <a href="/bursary_application.php">Apply Now</a>';
  form.prepend(bursarySection);
}

// Dynamic fee calculation
function calculateApplicationFee(){
  var eduEl = document.querySelector('[name="education_status"]');
  var schoolEl = document.querySelector('[name="school_type"]');
  var educationStatus = eduEl ? String(eduEl.value || '').toLowerCase() : '';
  var schoolType = schoolEl ? String(schoolEl.value || '').toLowerCase() : '';
  var fee = 0;
  if(educationStatus === 'grade12') fee = 0;
  else if(schoolType === 'international') fee = 500;
  else fee = 250;
  var feeEl = document.getElementById('application_fee_display');
  if(feeEl) feeEl.innerText = 'Application Fee: R' + fee;
}

document.addEventListener('DOMContentLoaded', function(){
  var incomeEl = document.querySelector('[name="household_income"]');
  var perfEl = document.querySelector('[name="academic_performance"]');
  var eduEl = document.querySelector('[name="education_status"]');
  var schoolEl = document.querySelector('[name="school_type"]');
  [incomeEl, perfEl].forEach(function(el){ if(el){ el.addEventListener('change', checkBursaryEligibility); }});
  [eduEl, schoolEl].forEach(function(el){ if(el){ el.addEventListener('change', calculateApplicationFee); }});
  // Initial evaluations
  checkBursaryEligibility();
  calculateApplicationFee();
});
</script>
<script>(function(){'use strict';var forms=document.querySelectorAll('.needs-validation');Array.prototype.slice.call(forms).forEach(function(f){f.addEventListener('submit',function(e){if(!f.checkValidity()){e.preventDefault();e.stopPropagation();}f.classList.add('was-validated');},false);});})();</script>
</body>
</html>