<?php
// DNS Email Authentication Checker for EduBridgeSA
// This script checks SPF, DKIM, and DMARC records for email deliverability

$domain = 'edubridgesa.co.za';
$smtp_domain = 'smtp.hostinger.com';

echo "<h2>Email DNS Authentication Check for {$domain}</h2>\n";
echo "<p>Checking email authentication records to diagnose spam folder issues...</p>\n";

// Check SPF Record
echo "<h3>1. SPF (Sender Policy Framework) Record</h3>\n";
$spf_record = dns_get_record($domain, DNS_TXT);
$spf_found = false;

foreach ($spf_record as $record) {
    if (strpos($record['txt'], 'v=spf1') === 0) {
        echo "<p><strong>SPF Record Found:</strong> {$record['txt']}</p>\n";
        $spf_found = true;
        
        // Check if Hostinger is included
        if (strpos($record['txt'], 'hostinger') !== false || strpos($record['txt'], 'include:_spf.hostinger.com') !== false) {
            echo "<p style='color: green;'>✓ Hostinger SMTP is properly included in SPF record</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ Hostinger SMTP may not be explicitly included in SPF record</p>\n";
            echo "<p><strong>Recommendation:</strong> Add 'include:_spf.hostinger.com' to your SPF record</p>\n";
        }
        break;
    }
}

if (!$spf_found) {
    echo "<p style='color: red;'>✗ No SPF record found</p>\n";
    echo "<p><strong>Recommendation:</strong> Add SPF record: 'v=spf1 include:_spf.hostinger.com ~all'</p>\n";
}

// Check DMARC Record
echo "<h3>2. DMARC (Domain-based Message Authentication) Record</h3>\n";
$dmarc_record = dns_get_record('_dmarc.' . $domain, DNS_TXT);
$dmarc_found = false;

foreach ($dmarc_record as $record) {
    if (strpos($record['txt'], 'v=DMARC1') === 0) {
        echo "<p><strong>DMARC Record Found:</strong> {$record['txt']}</p>\n";
        $dmarc_found = true;
        break;
    }
}

if (!$dmarc_found) {
    echo "<p style='color: red;'>✗ No DMARC record found</p>\n";
    echo "<p><strong>Recommendation:</strong> Add DMARC record: 'v=DMARC1; p=quarantine; rua=mailto:dmarc@{$domain}'</p>\n";
}

// Check DKIM Record (common selectors)
echo "<h3>3. DKIM (DomainKeys Identified Mail) Record</h3>\n";
$dkim_selectors = ['default', 'mail', 'hostinger', 'selector1', 'selector2'];
$dkim_found = false;

foreach ($dkim_selectors as $selector) {
    $dkim_domain = $selector . '._domainkey.' . $domain;
    $dkim_record = dns_get_record($dkim_domain, DNS_TXT);
    
    foreach ($dkim_record as $record) {
        if (strpos($record['txt'], 'v=DKIM1') === 0) {
            echo "<p><strong>DKIM Record Found (selector: {$selector}):</strong> " . substr($record['txt'], 0, 100) . "...</p>\n";
            $dkim_found = true;
            break 2;
        }
    }
}

if (!$dkim_found) {
    echo "<p style='color: orange;'>⚠ No DKIM record found with common selectors</p>\n";
    echo "<p><strong>Note:</strong> DKIM may be configured with a different selector by Hostinger</p>\n";
}

// Check MX Records
echo "<h3>4. MX (Mail Exchange) Records</h3>\n";
$mx_records = dns_get_record($domain, DNS_MX);

if (!empty($mx_records)) {
    echo "<p><strong>MX Records Found:</strong></p>\n";
    foreach ($mx_records as $mx) {
        echo "<p>Priority: {$mx['pri']}, Mail Server: {$mx['target']}</p>\n";
    }
} else {
    echo "<p style='color: red;'>✗ No MX records found</p>\n";
}

// Recommendations
echo "<h3>5. Recommendations to Improve Email Deliverability</h3>\n";
echo "<ul>\n";
echo "<li><strong>Contact Hostinger Support:</strong> Ask them to verify SPF, DKIM, and DMARC configuration for your domain</li>\n";
echo "<li><strong>Warm up your sending reputation:</strong> Start with small volumes and gradually increase</li>\n";
echo "<li><strong>Monitor email authentication:</strong> Use tools like MXToolbox or Mail-Tester to verify setup</li>\n";
echo "<li><strong>Ask recipients to whitelist:</strong> Request users to add info@edubridgesa.co.za to their contacts</li>\n";
echo "<li><strong>Avoid spam triggers:</strong> Use professional language and avoid excessive formatting</li>\n";
echo "</ul>\n";

echo "<h3>6. Next Steps</h3>\n";
echo "<p>1. Contact your hosting provider (Hostinger) to ensure proper email authentication setup</p>\n";
echo "<p>2. Test your email setup at: <a href='https://www.mail-tester.com' target='_blank'>Mail-Tester.com</a></p>\n";
echo "<p>3. Monitor your domain reputation at: <a href='https://mxtoolbox.com' target='_blank'>MXToolbox.com</a></p>\n";

echo "<hr>\n";
echo "<p><em>Generated on: " . date('Y-m-d H:i:s T') . "</em></p>\n";
?>