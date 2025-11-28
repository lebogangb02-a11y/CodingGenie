<?php
session_start();
require_once 'config.php';

// Start session and check if user is logged in
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Get user data from session
$username = $_SESSION['student_name'] ?? 'Student';
$email = $_SESSION['student_email'] ?? '';
$reference_number = $_SESSION['reference_number'] ?? 'APP2025000000';
$application_status = $_SESSION['application_status'] ?? 'draft';
$student_id = $_SESSION['student_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Debug - EduBridgeSA</title>
    <style>
        :root {
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #3b82f6;
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        
        .debug-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .debug-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .debug-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .test-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-success {
            background: #ecfdf5;
            border-left: 4px solid var(--success);
        }
        
        .test-warning {
            background: #fffbeb;
            border-left: 4px solid var(--warning);
        }
        
        .test-error {
            background: #fef2f2;
            border-left: 4px solid var(--error);
        }
        
        .test-info {
            background: #eff6ff;
            border-left: 4px solid var(--info);
        }
        
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-success { background: var(--success); }
        .status-warning { background: var(--warning); }
        .status-error { background: var(--error); }
        .status-info { background: var(--info); }
        
        .test-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 20px 0;
        }
        
        .test-button {
            padding: 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            text-align: center;
        }
        
        .btn-primary { background: #3b82f6; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--error); color: white; }
        
        .log-output {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        
        .event-log {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔧 Dashboard Debug Console - FIXED VERSION</h1>
        
        <!-- Session & Authentication Debug -->
        <div class="debug-section">
            <h2 class="debug-title">🔐 Session & Authentication</h2>
            <div id="session-tests">
                <div class="test-item test-success">
                    <span class="status-indicator status-success"></span>
                    <span>Session Started: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="test-item test-success">
                    <span class="status-indicator status-success"></span>
                    <span>User Logged In: <?php echo isset($_SESSION['student_logged_in']) ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="test-item test-success">
                    <span class="status-indicator status-success"></span>
                    <span>Student ID: <?php echo htmlspecialchars($student_id); ?></span>
                </div>
                <div class="test-item test-success">
                    <span class="status-indicator status-success"></span>
                    <span>Username: <?php echo htmlspecialchars($username); ?></span>
                </div>
            </div>
        </div>

        <!-- File Access Debug -->
        <div class="debug-section">
            <h2 class="debug-title">📁 File Access Tests</h2>
            <div id="file-tests">
                <div class="test-item test-info">
                    <span class="status-indicator status-info"></span>
                    <span>Testing file accessibility...</span>
                </div>
            </div>
        </div>

        <!-- Button & Navigation Debug -->
        <div class="debug-section">
            <h2 class="debug-title">🖱️ Button & Navigation Tests</h2>
            
            <div class="test-buttons">
                <button class="test-button btn-primary" onclick="testNormalLink()">
                    🔗 Test Normal Link
                </button>
                <button class="test-button btn-secondary" onclick="testProgrammaticNav()">
                    ⚡ Test Programmatic Nav
                </button>
                <button class="test-button btn-success" onclick="testFetchRequest()">
                    📡 Test Fetch Request
                </button>
                <button class="test-button btn-danger" onclick="testWindowOpen()">
                    🪟 Test Window Open
                </button>
            </div>
            
            <div id="button-tests">
                <div class="test-item test-info">
                    <span class="status-indicator status-info"></span>
                    <span>Click buttons above to test navigation methods</span>
                </div>
            </div>
            
            <!-- Apply Now Button Test -->
            <div style="margin: 20px 0; padding: 15px; background: #f0f9ff; border-radius: 8px;">
                <h3>🎯 Apply Now Button (Current Implementation)</h3>
                <a href="student-apply.php" class="btn-primary" id="applyNowDebug" 
                   style="display: inline-block; padding: 12px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0;">
                    <i class="fas fa-rocket"></i> Apply Now (Debug Version)
                </a>
                <div id="applyNowEvents" class="event-log">
                    Button event log will appear here...
                </div>
            </div>
        </div>

        <!-- CSS & JavaScript Debug -->
        <div class="debug-section">
            <h2 class="debug-title">🎨 CSS & JavaScript Analysis</h2>
            <div id="css-tests">
                <div class="test-item test-info">
                    <span class="status-indicator status-info"></span>
                    <span>Analyzing CSS and JavaScript...</span>
                </div>
            </div>
        </div>

        <!-- Event Log -->
        <div class="debug-section">
            <h2 class="debug-title">📋 Event Log</h2>
            <div class="log-output" id="eventLog">
                Debug session started at: <span id="currentTime"></span>
            </div>
        </div>
    </div>

    <script>
        let eventCount = 0;
        
        // Initialize debug session
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('currentTime').textContent = new Date().toLocaleString();
            logEvent('Debug session initialized');
            
            // Run initial tests
            testFileAccess();
            analyzeCSSJS();
            setupApplyNowDebug();
        });
        
        function logEvent(message, type = 'info') {
            eventCount++;
            const log = document.getElementById('eventLog');
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#ef4444' : type === 'success' ? '#10b981' : '#3b82f6';
            log.innerHTML += `\n[${timestamp}] <span style="color: ${color}">${message}</span>`;
            log.scrollTop = log.scrollHeight;
        }
        
        function updateTestResult(elementId, message, type = 'info') {
            const element = document.getElementById(elementId);
            const typeClass = `test-${type}`;
            const statusColor = type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'error' ? 'error' : 'info';
            
            element.innerHTML = `
                <div class="test-item ${typeClass}">
                    <span class="status-indicator status-${statusColor}"></span>
                    <span>${message}</span>
                </div>
            `;
        }
        
        // Test File Access
        async function testFileAccess() {
            const files = ['student-apply.php', 'simple_apply.php', 'student-dashboard.php'];
            const container = document.getElementById('file-tests');
            container.innerHTML = '';
            
            for (const file of files) {
                try {
                    const response = await fetch(file);
                    const status = response.status;
                    const message = status === 200 ? 'Accessible' : 
                                   status === 404 ? 'Not Found' : 
                                   status === 403 ? 'Forbidden' : 
                                   status === 500 ? 'Server Error' : `Status: ${status}`;
                    
                    const type = status === 200 ? 'success' : 'error';
                    
                    container.innerHTML += `
                        <div class="test-item test-${type}">
                            <span class="status-indicator status-${type}"></span>
                            <span>${file}: ${message} (${status})</span>
                        </div>
                    `;
                    
                    logEvent(`File ${file}: ${message}`, type);
                } catch (error) {
                    container.innerHTML += `
                        <div class="test-item test-error">
                            <span class="status-indicator status-error"></span>
                            <span>${file}: Network Error - ${error.message}</span>
                        </div>
                    `;
                    logEvent(`File ${file}: Network Error - ${error.message}`, 'error');
                }
            }
        }
        
        // Navigation Tests
        function testNormalLink() {
            logEvent('Testing normal link navigation...');
            window.location.href = 'student-apply.php';
        }
        
        function testProgrammaticNav() {
            logEvent('Testing programmatic navigation...');
            setTimeout(() => {
                window.location.assign('student-apply.php');
            }, 1000);
        }
        
        async function testFetchRequest() {
            logEvent('Testing fetch request to student-apply.php...');
            try {
                const response = await fetch('student-apply.php');
                updateTestResult('button-tests', 
                    `Fetch successful: ${response.status} ${response.statusText}`, 
                    response.ok ? 'success' : 'error');
                logEvent(`Fetch result: ${response.status} ${response.statusText}`, 
                        response.ok ? 'success' : 'error');
            } catch (error) {
                updateTestResult('button-tests', `Fetch failed: ${error.message}`, 'error');
                logEvent(`Fetch failed: ${error.message}`, 'error');
            }
        }
        
        function testWindowOpen() {
            logEvent('Testing window.open() method...');
            const newWindow = window.open('student-apply.php', '_blank');
            if (newWindow) {
                updateTestResult('button-tests', 'Window opened successfully', 'success');
                logEvent('Window opened successfully', 'success');
            } else {
                updateTestResult('button-tests', 'Popup blocked by browser', 'warning');
                logEvent('Popup blocked by browser', 'warning');
            }
        }
        
        // CSS & JS Analysis - FIXED VERSION
        function analyzeCSSJS() {
            const applyButton = document.querySelector('a[href="student-apply.php"]');
            const container = document.getElementById('css-tests');
            container.innerHTML = '';
            
            if (!applyButton) {
                container.innerHTML = `
                    <div class="test-item test-error">
                        <span class="status-indicator status-error"></span>
                        <span>Apply Now button not found in DOM</span>
                    </div>
                `;
                logEvent('Apply Now button not found in DOM', 'error');
                return;
            }
            
            const computedStyle = window.getComputedStyle(applyButton);
            const issues = [];
            
            // Check CSS properties
            if (computedStyle.pointerEvents === 'none') {
                issues.push('pointer-events: none');
            }
            if (computedStyle.cursor === 'default') {
                issues.push('cursor: default (not pointer)');
            }
            if (parseFloat(computedStyle.opacity) < 0.1) {
                issues.push('opacity too low');
            }
            if (computedStyle.visibility === 'hidden') {
                issues.push('visibility: hidden');
            }
            if (computedStyle.display === 'none') {
                issues.push('display: none');
            }
            if (parseInt(computedStyle.zIndex) < 0) {
                issues.push('negative z-index');
            }
            
            // Check positioning - FIXED: use getBoundingClientRect()
            const rect = applyButton.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0) {
                issues.push('zero dimensions');
            }
            
            if (issues.length === 0) {
                container.innerHTML = `
                    <div class="test-item test-success">
                        <span class="status-indicator status-success"></span>
                        <span>No CSS issues detected</span>
                    </div>
                    <div class="test-item test-info">
                        <span class="status-indicator status-info"></span>
                        <span>Button is visible and clickable via CSS</span>
                    </div>
                `;
                logEvent('CSS analysis: No issues found', 'success');
            } else {
                container.innerHTML = `
                    <div class="test-item test-error">
                        <span class="status-indicator status-error"></span>
                        <span>CSS issues detected: ${issues.join(', ')}</span>
                    </div>
                `;
                logEvent(`CSS issues: ${issues.join(', ')}`, 'error');
            }
        }
        
        // Apply Now Button Debug
        function setupApplyNowDebug() {
            const applyButton = document.getElementById('applyNowDebug');
            const eventLog = document.getElementById('applyNowEvents');
            
            if (!applyButton) {
                logEvent('Debug Apply Now button not found', 'error');
                return;
            }
            
            // Track all possible events
            const events = ['click', 'mousedown', 'mouseup', 'touchstart', 'touchend'];
            
            events.forEach(eventType => {
                applyButton.addEventListener(eventType, function(e) {
                    const logEntry = `
                        <div style="margin: 5px 0; padding: 5px; background: #e2e8f0; border-radius: 3px;">
                            <strong>${eventType}</strong>: 
                            defaultPrevented: ${e.defaultPrevented}, 
                            bubbles: ${e.bubbles}
                        </div>
                    `;
                    eventLog.innerHTML += logEntry;
                    
                    if (eventType === 'click') {
                        logEvent(`Apply Now button clicked - defaultPrevented: ${e.defaultPrevented}`, 
                                e.defaultPrevented ? 'error' : 'success');
                    }
                });
            });
            
            // Add a clean click handler that definitely works
            applyButton.addEventListener('click', function(e) {
                setTimeout(() => {
                    if (!e.defaultPrevented) {
                        logEvent('Navigation should proceed normally', 'success');
                    } else {
                        logEvent('Navigation was prevented by another handler', 'error');
                        // Force navigation as fallback
                        window.location.href = this.href;
                    }
                }, 100);
            }, { once: true });
        }
    </script>
</body>
</html>