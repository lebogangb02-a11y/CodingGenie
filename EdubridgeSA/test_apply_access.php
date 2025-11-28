<?php
session_start();
require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Testing Student Apply Access</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:24px;line-height:1.45;color:#1f2937}
    code,pre{background:#f3f4f6;border-radius:6px;padding:8px 12px}
    .ok{color:#16a34a}.bad{color:#dc2626}.warn{color:#d97706}
    .btn{display:inline-block;margin:6px 6px 6px 0;padding:8px 12px;border-radius:8px;border:1px solid #d1d5db;background:#fff;cursor:pointer}
    .btn:hover{background:#f9fafb}
    .row{margin:10px 0}
  </style>
</head>
<body>
  <h1>Testing Student Apply Access</h1>
  <pre><?php
  echo "Session Status: ".session_status()."\n";
  echo "Session ID: ".session_id()."\n";
  echo "Student Logged In: ".(isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] ? 'YES' : 'NO')."\n";
  if (!empty($_SESSION['student_logged_in'])) {
      echo "Student ID: ".($_SESSION['student_id'] ?? 'NOT SET')."\n";
      echo "Student Name: ".($_SESSION['student_name'] ?? 'NOT SET')."\n";
      echo "Application Status: ".($_SESSION['application_status'] ?? 'NOT SET')."\n";
  } else {
      echo "❌ USER NOT LOGGED IN - This is likely the problem!\n";
  }

  echo "\n--- Headers Sent Test ---\n";
  if (headers_sent($filename, $linenum)) {
      echo "❌ Headers already sent in $filename on line $linenum\n";
  } else {
      echo "✅ Headers not sent yet - redirects should work\n";
  }

  echo "\n--- File Existence Test ---\n";
  $files = ['apply.php','student-apply.php','config.php','auth.php'];
  foreach ($files as $file) {
      echo $file.': '.(file_exists($file) ? '✅ EXISTS' : '❌ MISSING')."\n";
  }
  ?></pre>

  <div class="row">
    <button class="btn" onclick="testDirectNavigationApply()">JS Test → apply.php</button>
    <button class="btn" onclick="testDirectNavigationStudentApply()">JS Test → student-apply.php</button>
    <button class="btn" onclick="simpleRedirect('apply.php')">Simple Redirect → apply.php</button>
    <button class="btn" onclick="simpleRedirect('student-apply.php')">Simple Redirect → student-apply.php</button>
    <a class="btn" href="apply.php" target="_blank" rel="noopener">Open apply.php in new tab</a>
    <a class="btn" href="student-apply.php" target="_blank" rel="noopener">Open student-apply.php in new tab</a>
  </div>

  <div class="row">
    <button class="btn" onclick="checkFetch('apply.php')">Fetch Check → apply.php</button>
    <button class="btn" onclick="checkFetch('student-apply.php')">Fetch Check → student-apply.php</button>
  </div>

  <pre id="log"></pre>

  <script>
    const logEl = document.getElementById('log');
    function log(msg){ logEl.textContent += msg + "\n"; console.log(msg); }

    function simpleRedirect(url){
      log('Simple redirect to ' + url);
      window.location.href = url;
    }

    // Direct navigation test sequences
    function testDirectNavigation(url){
      log('=== DIRECT NAVIGATION TEST for ' + url + ' ===');
      log('Test 1: window.location.href');
      window.location.href = url;
      setTimeout(() => {
        log('Test 1 did not complete in 3s, Test 2: location.assign');
        window.location.assign(url);
      }, 3000);
      setTimeout(() => {
        log('Test 2 did not complete in 3s, Test 3: location.replace');
        window.location.replace(url);
      }, 6000);
    }
    function testDirectNavigationApply(){ testDirectNavigation('apply.php'); }
    function testDirectNavigationStudentApply(){ testDirectNavigation('student-apply.php'); }

    // Fetch-based redirect visibility
    async function checkFetch(url){
      log('--- Fetching ' + url + ' ---');
      try {
        const resp = await fetch(url, { redirect: 'follow', credentials: 'include' });
        log('Status: ' + resp.status + ', redirected: ' + resp.redirected + ', finalURL: ' + resp.url);
        const text = await resp.text();
        log('Body snippet: ' + text.slice(0, 180).replace(/\n/g,' '));
      } catch (e) {
        log('Fetch failed: ' + (e && e.message ? e.message : e));
      }
    }
  </script>
</body>
</html>