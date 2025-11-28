<?php
// notification_functions.php - Notification System Functions

/**
 * Create notification for new student application
 */
function createApplicationNotification($pdo, $applicationId, $applicationData) {
    // ... same function code as above ...
}

/**
 * Create notification for new contact enquiry
 */
function createEnquiryNotification($pdo, $enquiryId, $enquiryData) {
    // ... same function code as above ...
}

/**
 * Get unread notification count for admin
 */
function getUnreadNotificationCount($pdo, $username) {
    // ... same function code as above ...
}

/**
 * Create system-wide notification
 */
function createSystemNotification($pdo, $title, $content, $type = 'info', $audience = 'all', $createdBy = 'system') {
    // ... same function code as above ...
}
?>