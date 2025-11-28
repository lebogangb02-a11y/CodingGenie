<?php
require_once 'config.php';

// Check if there's a success message and application ID
$applicationId = $_SESSION['application_id'] ?? null;
$applicantName = $_SESSION['applicant_name'] ?? 'Applicant';
$applicantEmail = $_SESSION['applicant_email'] ?? '';

// Clear session data after displaying
unset($_SESSION['application_id'], $_SESSION['applicant_name'], $_SESSION['applicant_email']);

// If no application ID, redirect to apply page
if (!$applicationId) {
    header('Location: apply.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted Successfully - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Color Palette */
            --primary-navy: #0D1B2A;
            --accent-gold: #F4B400;
            --secondary-blue: #1976D2;
            --background-light: #F9FAFB;
            --text-primary: #333333;
            --text-secondary: #666666;
            --white: #ffffff;
            --success-green: #27ae60;
            --error-red: #e74c3c;
            --border-light: #e9ecef;
            
            /* Typography */
            --font-family: 'Poppins', sans-serif;
            --font-weight-light: 300;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;
            
            /* Shadows */
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.15);
            
            /* Border Radius */
            --radius-small: 8px;
            --radius-medium: 12px;
            --radius-large: 16px;
            
            /* Transitions */
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            font-weight: var(--font-weight-normal);
            background: linear-gradient(135deg, var(--background-light) 0%, #e8f4f8 100%);
            min-height: 100vh;
            padding: 20px;
            color: var(--text-primary);
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 600px;
            width: 100%;
            background: var(--white);
            border-radius: var(--radius-medium);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            text-align: center;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: linear-gradient(135deg, var(--success-green) 0%, #2ecc71 100%);
            color: var(--white);
            padding: 40px 30px;
            position: relative;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounceIn 0.8s ease-out 0.3s both;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: var(--font-weight-bold);
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            font-weight: var(--font-weight-normal);
            opacity: 0.9;
        }

        .content {
            padding: 40px 30px;
        }

        .application-details {
            background: var(--background-light);
            border-radius: var(--radius-small);
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid var(--accent-gold);
        }

        .application-details h3 {
            color: var(--primary-navy);
            font-weight: var(--font-weight-semibold);
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: var(--font-weight-medium);
            color: var(--text-secondary);
        }

        .detail-value {
            font-weight: var(--font-weight-semibold);
            color: var(--primary-navy);
        }

        .next-steps {
            background: linear-gradient(135deg, var(--primary-navy) 0%, #1a2332 100%);
            color: var(--white);
            padding: 25px;
            margin: 30px 0;
            border-radius: var(--radius-small);
        }

        .next-steps h3 {
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .next-steps ul {
            list-style: none;
            text-align: left;
        }

        .next-steps li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }

        .next-steps li::before {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--accent-gold);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--radius-small);
            font-family: var(--font-family);
            font-weight: var(--font-weight-medium);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition-normal);
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-gold) 0%, #f39c12 100%);
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--primary-navy);
            border: 2px solid var(--primary-navy);
        }

        .btn-secondary:hover {
            background: var(--primary-navy);
            color: var(--white);
        }

        .footer {
            background: var(--background-light);
            padding: 20px 30px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Thank You!</h1>
            <p>Your application has been submitted successfully</p>
        </div>

        <div class="content">
            <p>Dear <strong><?php echo htmlspecialchars($applicantName); ?></strong>,</p>
            <p>We have received your application and it is now being processed by our admissions team.</p>

            <div class="application-details">
                <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                <div class="detail-item">
                    <span class="detail-label">Application ID:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($applicationId); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Submission Date:</span>
                    <span class="detail-value"><?php echo date('F j, Y \a\t g:i A'); ?></span>
                </div>
                <?php if ($applicantEmail): ?>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($applicantEmail); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="next-steps">
                <h3><i class="fas fa-list-check"></i> What Happens Next?</h3>
                <ul>
                    <li>You will receive a confirmation email within 24 hours</li>
                    <li>Our admissions team will review your application</li>
                    <li>We will contact you within 5-7 business days</li>
                    <li>You can track your application status using your Application ID</li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="apply.php" class="btn btn-secondary">
                    <i class="fas fa-plus"></i>
                    Submit Another Application
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i>
                    Return to Homepage
                </a>
            </div>
        </div>

        <div class="footer">
            <p><strong>Important:</strong> Please save your Application ID (<?php echo htmlspecialchars($applicationId); ?>) for future reference.</p>
            <p>If you have any questions, please contact us at admissions@edubridgesa.ac.za</p>
        </div>
    </div>
</body>
</html>