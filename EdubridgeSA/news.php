<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>News & Announcements - EduBridgeSA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/mobile.css">
</head>
<body class="legacy-news">
  <?php 
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    include 'includes/navigation.php'; 
  ?>

  <main class="page-content">
    <section class="hero-section">
      <div class="hero-content">
        <h1>News & Announcements</h1>
        <p>Latest updates from EduBridgeSA</p>
      </div>
    </section>

    <section class="standard-section">
      <div class="news-grid">
        <div class="standard-card">
          <h3>News Item 1</h3>
          <p>Content here...</p>
        </div>
        <div class="standard-card">
          <h3>News Item 2</h3>
          <p>Content here...</p>
        </div>
      </div>
    </section>
  </main>

  <script>
    // Mobile menu toggle (shared behavior)
    document.addEventListener('DOMContentLoaded', function() {
      const mobileMenu = document.getElementById('mobile-menu');
      const navLinks = document.getElementById('nav-links');
      if (mobileMenu && navLinks) {
        mobileMenu.addEventListener('click', function(e) {
          e.stopPropagation();
          navLinks.classList.toggle('active');
        });
        document.querySelectorAll('.nav-links a').forEach(link => {
          link.addEventListener('click', () => navLinks.classList.remove('active'));
        });
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