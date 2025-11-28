<?php
// Simple Resources hub combining Universities, TVET, and APS Calculator
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resources - EduBridgeSA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/mobile.css">
</head>
<body>
  <?php include 'includes/navigation.php'; ?>

  <!-- Page Header -->
  <section class="hero" style="padding: 4rem 1rem; background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('images/students-graduating.jpg') center/cover;">
    <div class="hero-content">
      <h1>Resources</h1>
      <p>Explore universities, TVET colleges, and calculate your APS — all in one place.</p>
      <div class="hero-buttons">
        <a href="#universities" class="btn"><i class="fas fa-university"></i> Universities</a>
        <a href="#tvet" class="btn"><i class="fas fa-graduation-cap"></i> TVET</a>
        <a href="#aps" class="btn btn-outline"><i class="fas fa-calculator"></i> APS Calculator</a>
      </div>
    </div>
  </section>

  <main class="container" style="max-width: 1100px; margin: 0 auto; padding: 2rem 1rem;">
    <!-- Universities Section -->
    <section id="universities" class="section" style="margin-bottom: 3rem;">
      <div class="section-title" style="margin-bottom: 1rem;">
        <h2><i class="fas fa-university"></i> Universities</h2>
        <p>Browse information and application guidance for South African universities.</p>
      </div>
      <a class="btn" href="universities.php">Open Universities Page</a>
    </section>

    <!-- TVET Section -->
    <section id="tvet" class="section" style="margin-bottom: 3rem;">
      <div class="section-title" style="margin-bottom: 1rem;">
        <h2><i class="fas fa-graduation-cap"></i> TVET Colleges</h2>
        <p>Explore TVET programmes, requirements, and application steps.</p>
      </div>
      <a class="btn" href="tvet.php">Open TVET Page</a>
    </section>

    <!-- APS Calculator Section -->
    <section id="aps" class="section" style="margin-bottom: 3rem;">
      <div class="section-title" style="margin-bottom: 1rem;">
        <h2><i class="fas fa-calculator"></i> APS Calculator</h2>
        <p>Calculate your Admission Point Score to check eligibility for programmes.</p>
      </div>
      <a class="btn btn-outline" href="aps-calculator.php">Open APS Calculator</a>
    </section>
  </main>

  <footer style="background: #1a1a1a; color: white; padding: 3rem 1rem; text-align: center;">
    <div style="max-width: 1200px; margin: 0 auto;">
      <p>© <?php echo date('Y'); ?> EduBridgeSA • Resources</p>
    </div>
  </footer>
</body>
</html>