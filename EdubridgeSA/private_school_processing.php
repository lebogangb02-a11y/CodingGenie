<?php
// private_school_processing.php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Private School Verification - EduBridge</title>
    <style>
        body{font-family:system-ui,Arial,sans-serif;margin:2rem;}
        .card{max-width:800px;margin:auto;border:1px solid #ddd;border-radius:8px;padding:1.5rem;}
        h2{margin-top:0}
        ul{line-height:1.8}
        a.button{display:inline-block;background:#198754;color:#fff;text-decoration:none;padding:.6rem 1rem;border-radius:6px}
        a.button:hover{background:#157347}
    </style>
</head>
<body>
    <div class="card">
        <h2>Additional Verification Required</h2>
        <p>Private school students need to submit:</p>
        <ul>
            <li>School accreditation certificate</li>
            <li>Principal verification letter</li>
            <li>Curriculum details</li>
        </ul>
        <p>
            <a class="button" href="document_upload.php?type=private_school">Upload Documents</a>
        </p>
    </div>
</body>
</html>