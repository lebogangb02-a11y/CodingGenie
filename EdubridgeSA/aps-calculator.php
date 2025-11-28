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
  <title>APS Calculator - EduBridgeSA</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {margin: 0;padding: 0;box-sizing: border-box;font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;}
    body {background: linear-gradient(135deg, #f0f4ff 0%, #e6f7f1 100%);color: #333;min-height: 100vh;padding: 20px;}
    .container {max-width: 1200px;margin: 0 auto;display: grid;grid-template-columns: 1fr 1fr;gap: 20px;}
    @media (max-width: 768px) {.container {grid-template-columns: 1fr;}}
    .card {background: white;border-radius: 15px;box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);padding: 25px;margin-bottom: 20px;transition: transform 0.3s ease;border: 1px solid rgba(0, 0, 0, 0.05);}
    .card:hover {transform: translateY(-5px);box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);}
    h1 {text-align: center;color: #1a5fb4;margin-bottom: 30px;font-size: 2.5rem;padding: 20px;text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);}
    h2 {color: #1a5fb4;margin-bottom: 20px;padding-bottom: 10px;border-bottom: 2px solid #f0f0f0;}
    h3 {color: #1a5fb4;margin: 15px 0 10px;}
    .subject {display: flex;justify-content: space-between;align-items: center;margin-bottom: 15px;padding: 12px;background: #f8f9fa;border-radius: 8px;transition: background-color 0.2s;}
    .subject:hover {background: #e9ecef;}
    .subject-name {font-weight: 600;color: #444;}
    select {padding: 8px 12px;border: 1px solid #ddd;border-radius: 6px;background: white;font-size: 16px;width: 80px;transition: all 0.2s;}
    select:focus {border-color: #1a5fb4;outline: none;box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.2);}
    .remove-btn {background: #dc3545;color: white;border: none;padding: 5px 10px;border-radius: 6px;font-size: 14px;cursor: pointer;margin-left: 10px;}
    .remove-btn:hover {background: #b02a37;}
    .btn-add {display: block;width: 100%;padding: 12px;background: #28a745;color: white;border: none;border-radius: 8px;font-size: 16px;font-weight: 600;cursor: pointer;margin-top: 10px;transition: background-color 0.2s;}
    .btn-add:hover {background: #218838;}
    .score-display {text-align: center;padding: 20px;background: linear-gradient(135deg, #1a5fb4 0%, #0f4a8f 100%);color: white;border-radius: 12px;margin: 20px 0;box-shadow: 0 5px 15px rgba(26, 95, 180, 0.3);}
    .aps-score {font-size: 4rem;font-weight: 700;line-height: 1;}
    .score-label {font-size: 1.2rem;opacity: 0.9;}
    .stats {display: flex;justify-content: space-around;margin: 20px 0;}
    .stat-box {text-align: center;padding: 15px;background: #f8f9fa;border-radius: 10px;flex: 1;margin: 0 10px;box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);}
    .stat-value {font-size: 2rem;font-weight: 700;color: #1a5fb4;}
    .stat-label {font-size: 0.9rem;color: #666;}
    .qualification {padding: 15px;margin: 12px 0;border-radius: 8px;background: #f8f9fa;display: flex;justify-content: space-between;align-items: center;transition: transform 0.2s;}
    .qualification:hover {transform: translateX(5px);}
    .qualification.met {border-left: 5px solid #32c788;background: #f0f9f4;}
    .qualification.not-met {border-left: 5px solid #dc3545;background: #fdf3f4;}
    .status {font-weight: 600;padding: 5px 12px;border-radius: 20px;font-size: 0.9rem;}
    .met .status {background: #d4f5e2;color: #218753;}
    .not-met .status {background: #f8d7da;color: #dc3545;}
    .requirement {font-size: 0.9rem;color: #666;margin-top: 5px;}
    .program-list {font-size: 0.95rem;color: #555;margin-top: 5px;}
    .instructions {background: #e9ecef;padding: 15px;border-radius: 8px;margin-top: 20px;font-size: 0.9rem;line-height: 1.5;}
    .footer {text-align: center;color: #666;margin-top: 30px;padding: 20px;font-size: 0.9rem;}
    .btn-reset {display: block;width: 100%;padding: 12px;background: #ff7b00;color: white;border: none;border-radius: 8px;font-size: 16px;font-weight: 600;cursor: pointer;margin-top: 20px;transition: background-color 0.2s;}
    .btn-reset:hover {background: #e66a00;}
    .header-logo {display: flex;align-items: center;justify-content: center;gap: 10px;margin-bottom: 10px;}
    .logo-img {height: 40px;width: 40px;}
  </style>
</head>
<body>
  <?php include 'includes/navigation.php'; ?>
  
  <div class="header-logo">
    <h1>APS Calculator</h1>
  </div>

  <div class="container">
    <div class="left-column">
      <div class="card">
        <h2>Your Subjects</h2>
        <div id="subjects-container"></div>
        <button class="btn-add" onclick="addSubject()">+ Add Subject</button>
        <button class="btn-reset" onclick="resetForm()">Reset All Selections</button>
      </div>

      <div class="card">
        <h2>Subject Requirements</h2>
        <p><strong>Mathematics:</strong> Minimum Level 4 for most programs</p>
        <p><strong>English:</strong> Minimum Level 4 for university admission</p>
        <p><strong>Life Orientation:</strong> Often required with minimum Level 4</p>
        <div class="instructions">
          <p><strong>How to use:</strong> Select your subject levels above to calculate your APS score. The calculator will automatically show which qualifications you're eligible for.</p>
          <p><strong>Note:</strong> Some programs may have specific subject requirements beyond the APS score.</p>
        </div>
      </div>
    </div>

    <div class="right-column">
      <div class="card">
        <div class="score-display">
          <div class="score-label">Your APS Score</div>
          <div class="aps-score" id="aps-score">0</div>
          <div class="score-label" id="score-category">Beginner</div>
        </div>
        <div class="stats">
          <div class="stat-box">
            <div class="stat-value" id="subjects-count">0</div>
            <div class="stat-label">Subjects</div>
          </div>
          <div class="stat-box">
            <div class="stat-value" id="average-score">0</div>
            <div class="stat-label">Average per Subject</div>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>Qualification Pathways</h2>
        <div class="qualification" id="medicine"><div><h3>Medicine & Health Sciences</h3><div class="requirement">Minimum APS: 38</div><div class="program-list">MBChB, Dentistry, Physiotherapy</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="engineering"><div><h3>Engineering</h3><div class="requirement">Minimum APS: 32</div><div class="program-list">BEng Civil, BEng Electrical, BSc Mechanical</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="commerce"><div><h3>Commerce & Business</h3><div class="requirement">Minimum APS: 28</div><div class="program-list">BCom, BCom Accounting, Business Science</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="science"><div><h3>Science & Technology</h3><div class="requirement">Minimum APS: 30</div><div class="program-list">BSc, Computer Science, Data Science</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="arts"><div><h3>Arts & Humanities</h3><div class="requirement">Minimum APS: 26</div><div class="program-list">BA, Social Sciences, Psychology</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="education"><div><h3>Education</h3><div class="requirement">Minimum APS: 24</div><div class="program-list">BEd, Education, Teaching</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="diploma"><div><h3>Diploma Programs</h3><div class="requirement">Minimum APS: 22</div><div class="program-list">Diploma in IT, Management, Engineering</div></div><div class="status">Requirements not met</div></div>
        <div class="qualification" id="certificate"><div><h3>Higher Certificates</h3><div class="requirement">Minimum APS: 18</div><div class="program-list">Higher Certificate in Business, IT, Foundation Programs</div></div><div class="status">Requirements not met</div></div>
      </div>
    </div>
  </div>

  <div class="footer">
    <p>APS Calculator & Qualification Checker | © 2025 EduBridgeSA - Bridging Dreams to Degrees</p>
  </div>

  <script>
    const subjectsDB = [
      "English Home Language","English First Additional Language","Afrikaans Home Language","Afrikaans First Additional Language","isiZulu Home Language","isiZulu First Additional Language",
      "Setswana Home Language","Setswana First Additional Language","Sesotho Home Language","Sesotho First Additional Language","Xitsonga Home Language","Xitsonga First Additional Language",
      "Sepedi Home Language","Sepedi First Additional Language","isiXhosa Home Language","isiXhosa First Additional Language","isiNdebele Home Language","isiNdebele First Additional Language",
      "Siswati Home Language","Siswati First Additional Language","Life Sciences","Physical Sciences","Mathematics","Mathematical Literacy","Geography","History","Accounting","Economics",
      "Business Studies","Tourism","Consumer Studies","Agricultural Sciences","Computer Applications Technology","Information Technology","Visual Arts","Dramatic Arts","Music","Life Orientation"
    ];

    function createSubject(subjectName = "") {
      const container = document.getElementById("subjects-container");
      const div = document.createElement("div");
      div.className = "subject";

      const options = subjectsDB.map(sub => `<option value="${sub}" ${sub === subjectName ? "selected" : ""}>${sub}</option>`).join("");

      div.innerHTML = `
        <select class="subject-select" onchange="calculateAPS()">
          <option value="">Select Subject</option>
          ${options}
        </select>
        <select class="level-select" onchange="calculateAPS()">
          <option value="0">Level</option>
          <option value="7">7</option>
          <option value="6">6</option>
          <option value="5">5</option>
          <option value="4">4</option>
          <option value="3">3</option>
          <option value="2">2</option>
          <option value="1">1</option>
        </select>
        <button class="remove-btn" onclick="removeSubject(this)">Remove</button>
      `;
      container.appendChild(div);
    }

    function addSubject() {
      createSubject();
    }

    function removeSubject(btn) {
      btn.parentElement.remove();
      calculateAPS();
    }

    function calculateAPS() {
      let totalScore = 0;
      let count = 0;

      document.querySelectorAll(".level-select").forEach(sel => {
        const value = parseInt(sel.value) || 0;
        if (value > 0) { totalScore += value; count++; }
      });

      document.getElementById("aps-score").textContent = totalScore;
      document.getElementById("subjects-count").textContent = count;
      document.getElementById("average-score").textContent = count > 0 ? (totalScore / count).toFixed(1) : 0;

      const scoreCategory = document.getElementById("score-category");
      if (totalScore >= 35) scoreCategory.textContent = "Excellent";
      else if (totalScore >= 28) scoreCategory.textContent = "Good";
      else if (totalScore >= 22) scoreCategory.textContent = "Average";
      else if (totalScore > 0) scoreCategory.textContent = "Below Average";
      else scoreCategory.textContent = "Beginner";

      checkQualification("medicine", 38, totalScore);
      checkQualification("engineering", 32, totalScore);
      checkQualification("commerce", 28, totalScore);
      checkQualification("science", 30, totalScore);
      checkQualification("arts", 26, totalScore);
      checkQualification("education", 24, totalScore);
      checkQualification("diploma", 22, totalScore);
      checkQualification("certificate", 18, totalScore);
    }

    function checkQualification(id, min, score) {
      const q = document.getElementById(id);
      const status = q.querySelector(".status");
      if (score >= min) {
        q.classList.remove("not-met");
        q.classList.add("met");
        status.textContent = "Requirements met";
      } else {
        q.classList.remove("met");
        q.classList.add("not-met");
        status.textContent = "Requirements not met";
      }
    }

    function resetForm() {
      document.getElementById("subjects-container").innerHTML = "";
      for (let i = 0; i < 6; i++) addSubject();
      calculateAPS();
    }

    window.onload = function() { resetForm(); };
  </script>
</body>
</html>