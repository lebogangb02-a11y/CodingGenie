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
  <title>About Us - EduBridgeSA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/mobile.css">
  <style>
    /* Fonts & Colors */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

    :root {
      --primary: #1a5fb4;
      --secondary: #2e7d32;
      --light: #f5f7fa;
      --dark: #1a237e;
      --accent: #ff9800;
    }

    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(to right, #f9f9f9, #eef6ff);
      color: #333;
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

    .logo span {
      font-weight: 600;
      font-size: 1.2rem;
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

    .btn {
      display: inline-block;
      background: var(--accent);
      color: white;
      padding: 12px 30px;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    @media (max-width: 768px) {
      .nav-links {
        display: none;
      }
      .logo img {
        height: 40px;
      }
    }

    header {
      background: linear-gradient(90deg, #0052cc, #007bff);
      color: #fff;
      text-align: center;
      padding: 3rem 1rem;
      animation: fadeInDown 1s ease-out;
    }

    header h1 {
      font-size: 2.8rem;
      margin: 0;
    }

    header p {
      font-size: 1.2rem;
      margin-top: 10px;
    }

    section {
      padding: 3rem 10%;
      animation: fadeIn 1.5s ease-in-out;
    }

    h2 {
      color: #0052cc;
      font-size: 2rem;
      margin-bottom: 1rem;
      position: relative;
    }

    h2::after {
      content: "";
      display: block;
      width: 60px;
      height: 4px;
      background: #007bff;
      margin-top: 5px;
      border-radius: 5px;
    }

    .card {
      background: #fff;
      border-radius: 15px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }

    .card:hover {
      transform: translateY(-8px);
    }

    .services {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
    }

    .service-box {
      background: #eef6ff;
      padding: 1.5rem;
      border-radius: 12px;
      text-align: center;
      transition: transform 0.3s ease, background 0.3s ease;
    }

    .service-box:hover {
      transform: scale(1.05);
      background: #dceeff;
    }

    footer {
      background: #0052cc;
      color: #fff;
      text-align: center;
      padding: 2rem 1rem;
      margin-top: 2rem;
      animation: fadeInUp 1s ease-in-out;
    }

    footer p {
      margin: 5px 0;
    }

    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-50px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(50px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <?php 
  // Start session if not already started
  if (session_status() == PHP_SESSION_NONE) {
      session_start();
  }
  include 'includes/navigation.php'; 
  ?>

  <header>
    <h1>About EduBridgeSA</h1>
    <p>Bridging the gap between learners and higher education opportunities</p>
  </header>

  <section>
    <div class="card">
      <h2>Who We Are</h2>
      <p>EduBridgeSA is a youth-led Non-Profit Organisation based in Dinokana village, Zeerust. We were founded by three dedicated students — two from the University of South Africa (UNISA) and one from North West University (NWU). Our vision is to make higher education accessible by guiding learners through applications, bursary opportunities, and academic support.</p>
    </div>

    <div class="card">
      <h2>Our Story</h2>
      <p>EduBridgeSA began when three students saw the challenges learners in rural areas face when applying to universities and funding schemes. With first-hand experience of these struggles, we decided to build a bridge between learners and the academic world — ensuring no learner is left behind due to lack of information or support.</p>
    </div>

    <div class="card">
      <h2>Our Services</h2>
      <div class="services">
        <div class="service-box">
          <h3>University Applications</h3>
          <p>Helping Grade 12 learners apply to multiple universities with ease.</p>
        </div>
        <div class="service-box">
          <h3>APS Calculations</h3>
          <p>We assist learners in calculating their APS scores to qualify for courses.</p>
        </div>
        <div class="service-box">
          <h3>Bursary Guidance</h3>
          <p>Connecting learners to bursary and funding opportunities like NSFAS.</p>
        </div>
        <div class="service-box">
          <h3>Academic Resources</h3>
          <p>Providing study materials, guidance, and career advice for learners.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Help & Support Section -->
  <section class="section" style="padding: 3rem 1rem;">
    <div class="container" style="max-width: 1100px; margin: 0 auto;">
      <div class="section-title" style="text-align: center; margin-bottom: 2rem;">
        <h2>Need Help?</h2>
        <p>Reach out to us directly or visit our Support Center.</p>
      </div>

      <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem;">
        <!-- Contact Card -->
        <div id="contact" class="card" style="background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 1.5rem;">
          <h3 style="margin-bottom: 0.5rem;">Contact Us</h3>
          <p style="color: #555; margin-bottom: 1rem;">Questions about applications, admissions, or services? Send us a message.</p>
          <a class="btn" href="contact.php" style="display:inline-block;">Go to Contact</a>
        </div>

        <!-- Support Card -->
        <div id="support" class="card" style="background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 1.5rem;">
          <h3 style="margin-bottom: 0.5rem;">Support Center</h3>
          <p style="color: #555; margin-bottom: 1rem;">Get help with login, application steps, and common issues.</p>
          <a class="btn btn-outline" href="support.php" style="display:inline-block;">Visit Support</a>
        </div>
      </div>
    </div>
  </section>

  <footer>
    <h2>Contact Us</h2>
    <p><strong>Phone:</strong> 078 323 6239</p>
    <p><strong>Email Enquiries:</strong> info@edubridge.co.za</p>
    <p><strong>Applications:</strong> applications@edubridgesa.co.za</p>
    <p><strong>Location:</strong> Dinokana Village, Zeerust</p>
    <p>© 2025 EduBridgeSA - All Rights Reserved</p>
  </footer>

</body>
</html>
