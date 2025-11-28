<?php
/**
 * Application Success Page
 * Displays confirmation and next steps after successful application submission
 */

require_once 'config_application.php';

// Get reference number from URL
$reference_number = $_GET['ref'] ?? '';
// If email_sent query param is present, normalize to boolean; otherwise leave null
$email_sent = isset($_GET['email_sent']) ? ($_GET['email_sent'] == '1') : null;

if (empty($reference_number)) {
    header('Location: student-application.php');
    exit;
}

// Fetch application details
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT a.*, pg.full_name as parent_name, uc.university_1, uc.course_first_choice 
        FROM applications a 
        LEFT JOIN parent_guardian_details pg ON a.id = pg.application_id 
        LEFT JOIN university_choices uc ON a.id = uc.application_id 
        WHERE a.reference_number = ?
    ");
    $stmt->execute([$reference_number]);
    $application = $stmt->fetch();
    
    // Derive email sent status from logs when param not provided
    if ($email_sent === null && $application && !empty($application['id'])) {
        try {
            $stmt2 = $pdo->prepare("SELECT sent_status FROM email_notifications WHERE application_id = ? AND email_type = 'confirmation' ORDER BY id DESC LIMIT 1");
            $stmt2->execute([$application['id']]);
            $row = $stmt2->fetch();
            $email_sent = ($row && strtolower($row['sent_status']) === 'sent');
        } catch (Exception $e2) {
            error_log('Failed to derive email status: ' . $e2->getMessage());
            $email_sent = false;
        }
    }
    
    if (!$application) {
        header('Location: student-application.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error fetching application: " . $e->getMessage());
    $application = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted Successfully</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .success-header {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .success-icon {
            font-size: 4em;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .success-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .success-header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .reference-box {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .reference-box::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.1) 10px,
                rgba(255,255,255,0.1) 20px
            );
            animation: shine 3s linear infinite;
        }
        
        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%);
            }
            100% {
                transform: translateX(100%) translateY(100%);
            }
        }
        
        .reference-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 2px;
            position: relative;
            z-index: 1;
        }
        
        .reference-label {
            font-size: 1.1em;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .info-card p {
            color: #7f8c8d;
            line-height: 1.5;
        }
        
        .next-steps {
            background: #e8f5e8;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #27ae60;
            margin: 30px 0;
        }
        
        .next-steps h3 {
            color: #27ae60;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .steps-list {
            list-style: none;
            counter-reset: step-counter;
        }
        
        .steps-list li {
            counter-increment: step-counter;
            margin-bottom: 15px;
            padding-left: 40px;
            position: relative;
            line-height: 1.6;
            color: #2c3e50;
        }
        
        .steps-list li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            background: #27ae60;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .important-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .important-note h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .important-note p {
            color: #856404;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }
            
            .success-header {
                padding: 30px 20px;
            }
            
            .success-header h1 {
                font-size: 2em;
            }
            
            .content {
                padding: 20px;
            }
            
            .reference-number {
                font-size: 2em;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-header">
            <div class="success-icon">🎉</div>
            <h1>Application Submitted Successfully!</h1>
            <p>Your university application has been received and processed</p>
        </div>
        
        <div class="content">
            <div class="reference-box">
                <div class="reference-label">Your Reference Number</div>
                <div class="reference-number"><?php echo htmlspecialchars($reference_number); ?></div>
                <p>Save this number - you'll need it to upload documents and track your application</p>
            </div>
            
            <!-- Email Confirmation Status -->
            <div class="info-card" style="margin-bottom: 20px; <?php echo $email_sent ? 'border-left-color: #27ae60; background: #e8f5e8;' : 'border-left-color: #f39c12; background: #fff3cd;'; ?>">
                <h3><?php echo $email_sent ? '✅ Email Confirmation Sent' : '⚠️ Email Notification'; ?></h3>
                <?php if ($email_sent): ?>
                    <p style="color: #27ae60;">A confirmation email has been sent to your registered email address with your application details and next steps.</p>
                <?php else: ?>
                    <p style="color: #856404;">We were unable to send a confirmation email at this time. Please save your reference number and check your application status later.</p>
                <?php endif; ?>
            </div>
            
            <?php if ($application): ?>
            <div class="info-grid">
                <div class="info-card">
                    <h3>👤 Applicant Details</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($application['full_name'] . ' ' . $application['surname']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email_address']); ?></p>
                    <p><strong>ID Number:</strong> <?php echo htmlspecialchars($application['id_number']); ?></p>
                </div>
                
                <div class="info-card">
                    <h3>🎓 University Choice</h3>
                    <p><strong>First Choice:</strong> <?php echo htmlspecialchars($application['university_1'] ?? 'Not specified'); ?></p>
                    <p><strong>Course:</strong> <?php echo htmlspecialchars($application['course_first_choice'] ?? 'Not specified'); ?></p>
                </div>
                
                <div class="info-card">
                    <h3>📅 Application Status</h3>
                    <p><strong>Submitted:</strong> <?php echo date('d M Y, H:i', strtotime($application['submitted_at'])); ?></p>
                    <p><strong>Status:</strong> <span style="color: #27ae60; font-weight: bold;">Submitted</span></p>
                </div>
                
                <div class="info-card">
                    <h3>📄 Documents</h3>
                    <p><strong>Status:</strong> <span style="color: #f39c12; font-weight: bold;">Pending Upload</span></p>
                    <p>You can upload your documents anytime using your reference number</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="next-steps">
                <h3>🚀 What's Next?</h3>
                <ol class="steps-list">
                    <li><strong>Save your reference number</strong> - Write it down or take a screenshot</li>
                    <li><strong>Upload your documents</strong> - Use the document upload portal with your reference number</li>
                    <li><strong>Apply to universities</strong> - Use the information you've organized to apply directly to each university</li>
                    <li><strong>Track your progress</strong> - Check back anytime to see your application status</li>
                    <li><strong>Prepare for applications</strong> - Each university has its own application process and deadlines</li>
                </ol>
            </div>
            
            <div class="important-note">
                <h4>⚠️ Important Reminder</h4>
                <p>EduBridgeSA uses the information you provide here to apply to universities on your behalf through their official systems. Please make sure your details and documents are correct before submission, as they will be used for your official university applications. Contact details: 0783236239.</p>
            </div>
            
            <div class="action-buttons">
                <a href="document_upload.php?ref=<?php echo urlencode($reference_number); ?>" class="btn btn-primary">
                    📄 Upload Documents
                </a>
                <a href="application_status.php?ref=<?php echo urlencode($reference_number); ?>" class="btn btn-success">
                    📊 Check Status
                </a>
                <a href="student-application.php" class="btn btn-warning">
                    📝 New Application
                </a>
            </div>
            
            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1;">
                <p style="color: #7f8c8d;">
                    Need help? Contact our support team or visit our FAQ section.<br>
                    <strong>Email:</strong> applications@edubridgesa.co.za | <strong>Phone:</strong> 0783236239
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Copy reference number to clipboard
        document.querySelector('.reference-number').addEventListener('click', function() {
            const referenceNumber = this.textContent;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(referenceNumber).then(function() {
                    alert('Reference number copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = referenceNumber;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Reference number copied to clipboard!');
            }
        });
        
        // Add tooltip to reference number
        document.querySelector('.reference-number').title = 'Click to copy to clipboard';
        document.querySelector('.reference-number').style.cursor = 'pointer';
    </script>
</body>
</html>