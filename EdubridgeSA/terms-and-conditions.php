<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-navy: #0D1B2A;
            --accent-gold: #F4B400;
            --secondary-blue: #1976D2;
            --background-light: #F9FAFB;
            --text-primary: #333333;
            --text-secondary: #666666;
            --white: #ffffff;
            --border-light: #e9ecef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--background-light) 0%, #e8f4f8 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--primary-navy);
            color: var(--white);
            padding: 40px 30px;
            text-align: center;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            background: var(--white);
            padding: 40px;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .section {
            margin-bottom: 30px;
        }

        .section h2 {
            color: var(--primary-navy);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--accent-gold);
            padding-bottom: 5px;
        }

        .section h3 {
            color: var(--secondary-blue);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 20px 0 10px 0;
        }

        .section p {
            margin-bottom: 15px;
            text-align: justify;
        }

        .section ul, .section ol {
            margin: 15px 0;
            padding-left: 30px;
        }

        .section li {
            margin-bottom: 8px;
        }

        .highlight {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .highlight h4 {
            color: #856404;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--secondary-blue);
            color: var(--white);
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s ease;
            margin-top: 30px;
        }

        .back-btn:hover {
            background: #1565C0;
        }

        .last-updated {
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Terms and Conditions</h1>
            <p>EduBridge SA University Application Platform</p>
        </div>

        <div class="content">
            <div class="section">
                <h2>1. Introduction and Acceptance</h2>
                <p>Welcome to EduBridge SA, your trusted partner in higher education applications. By accessing and using our platform, you agree to be bound by these Terms and Conditions. Please read them carefully before proceeding with your application.</p>
                <p>These terms govern your use of our services, including but not limited to application submission, document uploads, and communication through our platform.</p>
            </div>

            <div class="section">
                <h2>2. Application Process and Requirements</h2>
                <h3>2.1 Eligibility</h3>
                <p>To use our services, you must:</p>
                <ul>
                    <li>Be at least 18 years old or have parental/guardian consent</li>
                    <li>Provide accurate and complete information</li>
                    <li>Meet the minimum academic requirements for your chosen programs</li>
                    <li>Have the legal right to study in South Africa</li>
                </ul>

                <h3>2.2 Application Submission</h3>
                <p>All applications must be submitted through our official platform. We do not accept applications via email, postal mail, or other means unless specifically stated.</p>
                <p>Application deadlines are strictly enforced. Late applications may not be considered.</p>
            </div>

            <div class="section">
                <h2>3. Protection of Personal Information Act (POPIA) Compliance</h2>
                
                <div class="highlight">
                    <h4><i class="fas fa-shield-alt"></i> Your Privacy Rights Under POPIA</h4>
                    <p>EduBridge SA is committed to protecting your personal information in accordance with the Protection of Personal Information Act (POPIA) of South Africa.</p>
                </div>

                <h3>3.1 Information We Collect</h3>
                <p>We collect and process the following personal information:</p>
                <ul>
                    <li><strong>Identity Information:</strong> Full name, ID number, date of birth, nationality</li>
                    <li><strong>Contact Information:</strong> Physical address, email address, phone numbers</li>
                    <li><strong>Academic Information:</strong> Educational qualifications, transcripts, certificates</li>
                    <li><strong>Financial Information:</strong> Funding sources, financial aid applications</li>
                    <li><strong>Supporting Documents:</strong> ID copies, academic records, motivation letters</li>
                    <li><strong>Emergency Contact Information:</strong> Next of kin details for safety purposes</li>
                </ul>

                <h3>3.2 Purpose of Processing</h3>
                <p>Your personal information is processed for the following purposes:</p>
                <ol>
                    <li>Processing and evaluating university applications</li>
                    <li>Communicating with you regarding your application status</li>
                    <li>Facilitating placement at appropriate institutions</li>
                    <li>Providing ongoing support and guidance</li>
                    <li>Compliance with legal and regulatory requirements</li>
                    <li>Statistical analysis and service improvement</li>
                </ol>

                <h3>3.3 Your Rights Under POPIA</h3>
                <p>You have the following rights regarding your personal information:</p>
                <ul>
                    <li><strong>Right to Access:</strong> Request copies of your personal information</li>
                    <li><strong>Right to Correction:</strong> Request correction of inaccurate information</li>
                    <li><strong>Right to Deletion:</strong> Request deletion of your information (subject to legal requirements)</li>
                    <li><strong>Right to Object:</strong> Object to processing for direct marketing purposes</li>
                    <li><strong>Right to Portability:</strong> Request transfer of your data to another service provider</li>
                </ul>

                <h3>3.4 Information Sharing</h3>
                <p>We may share your information with:</p>
                <ul>
                    <li>Universities and educational institutions for application processing</li>
                    <li>Government agencies as required by law</li>
                    <li>Service providers who assist in our operations (under strict confidentiality agreements)</li>
                    <li>Legal authorities when required by court order or legal process</li>
                </ul>

                <h3>3.5 Data Security</h3>
                <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
            </div>

            <div class="section">
                <h2>4. Document Upload and Verification</h2>
                <h3>4.1 Required Documents</h3>
                <p>You must upload clear, legible copies of all required documents. Accepted formats include PDF, JPG, and PNG files.</p>
                
                <h3>4.2 Document Authenticity</h3>
                <p>All uploaded documents must be authentic and unaltered. Submission of false or fraudulent documents will result in immediate disqualification and may lead to legal action.</p>

                <h3>4.3 Document Retention</h3>
                <p>Uploaded documents are retained for the duration of the application process and for a period of 7 years thereafter for record-keeping purposes.</p>
            </div>

            <div class="section">
                <h2>5. Communication and Notifications</h2>
                <p>By using our services, you consent to receive communications from us via:</p>
                <ul>
                    <li>Email notifications about application status</li>
                    <li>SMS updates for urgent matters</li>
                    <li>Platform notifications within your dashboard</li>
                    <li>Postal mail for official documents</li>
                </ul>
            </div>

            <div class="section">
                <h2>6. Limitation of Liability</h2>
                <p>EduBridge SA acts as an intermediary between applicants and educational institutions. We do not guarantee admission to any institution and are not responsible for admission decisions made by universities.</p>
                <p>Our liability is limited to the extent permitted by South African law.</p>
            </div>

            <div class="section">
                <h2>7. Intellectual Property</h2>
                <p>All content on this platform, including text, graphics, logos, and software, is the property of EduBridge SA and is protected by copyright and other intellectual property laws.</p>
            </div>

            <div class="section">
                <h2>8. Termination</h2>
                <p>We reserve the right to terminate or suspend access to our services at any time for violation of these terms or for any other reason deemed necessary.</p>
            </div>

            <div class="section">
                <h2>9. Governing Law</h2>
                <p>These terms are governed by the laws of South Africa. Any disputes will be resolved in the courts of South Africa.</p>
            </div>

            <div class="section">
                <h2>10. Contact Information</h2>
                <p>For questions about these terms or to exercise your POPIA rights, contact us at:</p>
                <ul>
                    <li><strong>Email:</strong> privacy@edubridge.co.za</li>
                    <li><strong>Phone:</strong> +27 11 123 4567</li>
                    <li><strong>Address:</strong> 123 Education Street, Johannesburg, 2000</li>
                </ul>
            </div>

            <div class="section">
                <h2>11. Changes to Terms</h2>
                <p>We reserve the right to modify these terms at any time. Changes will be posted on this page with an updated effective date. Continued use of our services constitutes acceptance of the modified terms.</p>
            </div>

            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Application
            </a>

            <div class="last-updated">
                <p>Last Updated: <?php echo date('F j, Y'); ?></p>
                <p>Effective Date: <?php echo date('F j, Y'); ?></p>
            </div>
        </div>
    </div>
</body>
</html>