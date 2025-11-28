<?php
require_once 'config.php';

// Handle form messages
$message = '';
$messageType = '';
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message'];
    $messageType = $_SESSION['form_message_type'] ?? 'info';
    unset($_SESSION['form_message'], $_SESSION['form_message_type']);
}

$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_errors']);

// Initialize form data from session if available
$formData = $_SESSION['application_form_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduBridgeSA - BRIDGING DREAMS TO DEGREES</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/mobile.css">
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
        <a href="tel:0783236239">
          <i class="fas fa-phone"></i>
          078 323 6239
        </a>
      </div>
      <div class="social-links">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
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

  <!-- Hero Section -->
  <section class="hero">
    <h1>BRIDGING DREAMS TO DEGREES</h1>
    <p>Your pathway to academic excellence in South Africa's top institutions</p>
    <div style="margin-top: 2rem;">
      <a href="application-access.php" class="btn">Start Your Application</a>
      <a href="#" class="btn btn-outline">Learn More</a>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features">
    <div class="section-title">
      <h2>Why Choose EduBridgeSA?</h2>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-img" style="background-image: url('images/jakob-rosen-CTd5_C7p__8-unsplash.jpg');"></div>
        <div class="feature-content">
          <h3>University Applications</h3>
          <p>Expert guidance for applications to all major South African universities and TVET colleges.</p>
        </div>
      </div>
      <div class="feature-card">
        <div class="feature-img" style="background-image: url('images/daniel-korpai-pKRNxEguRgM-unsplash.jpg');"></div>
        <div class="feature-content">
          <h3>Career Guidance</h3>
          <p>Personalized career counseling to help you choose the right path for your future.</p>
        </div>
      </div>
      <div class="feature-card">
        <div class="feature-img" style="background-image: url('images/pang-yuhao-_kd5cxwZOK4-unsplash.jpg');"></div>
        <div class="feature-content">
          <h3>Scholarship Assistance</h3>
          <p>Help finding and applying for scholarships and financial aid opportunities.</p>
        </div>
      </div>
    </div>
  </section>

<!-- CTA Section -->
  <section class="cta">
    <h2>Ready to Start Your Journey?</h2>
    <p>Join thousands of students who have successfully navigated their educational journey with EduBridgeSA.</p>
    <a href="application-access.php" class="btn">Apply Now</a>
    <a href="#" class="btn btn-outline">Contact Us</a>
  </section>

  <!-- Footer -->
  <footer style="background: #1a1a1a; color: white; padding: 3rem 1rem; text-align: center;">
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
    // Mobile menu toggle
    document.addEventListener('DOMContentLoaded', function() {
      const mobileMenu = document.getElementById('mobile-menu');
      const navLinks = document.getElementById('nav-links');
      
      if (mobileMenu && navLinks) {
        mobileMenu.addEventListener('click', function(e) {
          e.stopPropagation();
          navLinks.classList.toggle('active');
        });

        // Close menu when clicking on a link
        document.querySelectorAll('.nav-links a').forEach(link => {
          link.addEventListener('click', () => {
            navLinks.classList.remove('active');
          });
        });

        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
          if (!navLinks.contains(e.target) && e.target !== mobileMenu) {
            navLinks.classList.remove('active');
          }
        });
      }
    });
  </script>
</body>
</html>