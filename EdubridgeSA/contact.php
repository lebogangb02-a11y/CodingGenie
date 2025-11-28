<?php
require_once 'config.php';

// Handle form messages
$message = '';
$messageType = '';
if (isset($_SESSION['contact_message'])) {
    $message = $_SESSION['contact_message'];
    $messageType = $_SESSION['contact_message_type'] ?? 'info';
    unset($_SESSION['contact_message'], $_SESSION['contact_message_type']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - EduBridgeSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a5fb4;
            --secondary: #2e7d32;
            --light: #f5f7fa;
            --dark: #1a237e;
            --accent: #ff9800;
            --success: #4caf50;
            --error: #f44336;
            --warning: #ff9800;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
        }

        /* Top Bar */
        .top-bar {
            background-color: var(--primary);
            color: white;
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }

        .top-bar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 1rem;
        }

        .contact-info {
            display: flex;
            gap: 1.5rem;
        }

        .contact-info a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Navigation */
        .navigation {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--dark);
        }

        .logo img {
            height: 50px;
            margin-right: 1rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover, .nav-links a.active {
            color: var(--primary);
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .page-header p {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .contact-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .contact-info-section {
            background: var(--primary);
            color: white;
            padding: 2rem;
            border-radius: 10px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            background: var(--accent);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .contact-item i {
            font-size: 1.5rem;
            color: var(--accent);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .nav-links {
                display: none;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-container">
            <div class="contact-info">
                <a href="mailto:info@edubridgesa.co.za">
                    <i class="fas fa-envelope"></i>
                    info@edubridgesa.co.za
                </a>
                <a href="tel:+27783236239">
                    <i class="fas fa-phone"></i>
                    078 323 6239
                </a>
            </div>
        </div>
    </div>

    <?php 
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    include 'includes/navigation.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Contact Us</h1>
            <p>Get in touch with our team for any questions about applications, admissions, or our services. We're here to help you succeed!</p>
        </div>

        <!-- Contact Container -->
        <div class="contact-container">
            <!-- Contact Form -->
            <div class="contact-form">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark);">Send us a Message</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                        <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form id="contactForm" method="POST" action="contact-handler.php">
                    <div class="form-group">
                        <label for="fullName">Full Name *</label>
                        <input type="text" id="fullName" name="fullName" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <select id="subject" name="subject" required>
                            <option value="">Select a subject</option>
                            <option value="Application Inquiry">Application Inquiry</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="General Information">General Information</option>
                            <option value="Scholarship Information">Scholarship Information</option>
                            <option value="University Information">University Information</option>
                            <option value="TVET Information">TVET Information</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" placeholder="Please describe your inquiry in detail..." required></textarea>
                    </div>

                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i>
                        Send Message
                    </button>
                </form>
            </div>

            <!-- Contact Information -->
            <div class="contact-info-section">
                <h2 style="margin-bottom: 1.5rem;">Get in Touch</h2>
                
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>Address</h4>
                        <p>10033 MATLAPANA SECTION<br>DINOKANA 2868<br>ZEERUST, South Africa</p>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <h4>Phone</h4>
                        <p>078 323 6239</p>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>Email</h4>
                        <p>info@edubridgesa.co.za</p>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h4>Office Hours</h4>
                        <p>Monday - Friday: 8:00 AM - 5:00 PM<br>Saturday: 9:00 AM - 1:00 PM<br>Sunday: Closed</p>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem;">Follow Us</h4>
                    <div style="display: flex; gap: 1rem;">
                        <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-facebook"></i></a>
                        <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-instagram"></i></a>
                        <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: #1a1a1a; color: white; padding: 3rem 1rem; text-align: center; margin-top: 3rem;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="margin-bottom: 2rem;">
                <img src="images/logo.png.jpg" alt="EduBridgeSA Logo" style="height: 60px; margin-bottom: 1rem;">
                <p>Bridging the gap between dreams and academic success in South Africa</p>
            </div>
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #333;">
                <p>&copy; 2025 EduBridgeSA. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            // Create FormData object
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('contact-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                    
                    // Insert alert at the top of the form
                    const form = document.getElementById('contactForm');
                    form.insertBefore(alertDiv, form.firstChild);
                    
                    // Reset form
                    form.reset();
                    
                    // Scroll to top of form
                    form.scrollIntoView({ behavior: 'smooth' });
                } else {
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-error';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                    
                    // Insert alert at the top of the form
                    const form = document.getElementById('contactForm');
                    form.insertBefore(alertDiv, form.firstChild);
                    
                    // Scroll to top of form
                    form.scrollIntoView({ behavior: 'smooth' });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> An error occurred. Please try again.';
                
                const form = document.getElementById('contactForm');
                form.insertBefore(alertDiv, form.firstChild);
                form.scrollIntoView({ behavior: 'smooth' });
            })
            .finally(() => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                // Remove any existing alerts after 5 seconds
                setTimeout(() => {
                    const alerts = document.querySelectorAll('.alert');
                    alerts.forEach(alert => alert.remove());
                }, 5000);
            });
        });
    </script>
</body>
</html>