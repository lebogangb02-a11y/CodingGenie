# University Application Form Handler

This package contains PHP scripts to handle university application form submissions on your Hostinger website.

## Files Included

- `apply-handler.php` - Main form processing script
- `config.php` - Configuration file with database and email settings
- `install-phpmailer.php` - Script to install PHPMailer library

## Setup Instructions

### 1. Upload Files to Hostinger

Upload all files to your Hostinger website. Make sure to place them in the appropriate directory where your form will submit data.

### 2. Install PHPMailer

Run the `install-phpmailer.php` script by visiting it in your browser:
```
https://yourdomain.com/install-phpmailer.php
```

This will download and install the PHPMailer library needed for email functionality.

### 3. Update Configuration

Edit the `config.php` file with your actual database credentials:

```php
// Database configuration
define('DB_HOST', 'localhost'); // Usually correct for Hostinger
define('DB_USERNAME', 'your_db_username'); // Update this
define('DB_PASSWORD', 'your_db_password'); // Update this
define('DB_NAME', 'your_db_name'); // Update this
```

The email settings are already configured with your Hostinger email account:
- Email: Applications@edubridgesa.co.za
- SMTP Server: smtp.hostinger.com
- Port: 465
- Security: SSL

### 4. Update Database Table

You have two options for setting up the database table:

#### Option A: Update Existing Table (Recommended)
If you already have an `applications` table, run the `update_applications_table.sql` script in phpMyAdmin:

1. Open phpMyAdmin
2. Select your database: `u839420047_edubridge_stud`
3. Go to the SQL tab
4. Copy and paste the contents of `update_applications_table.sql`
5. Click "Go" to execute

#### Option B: Create New Table
If you want to start fresh, run the `modified_applications_table.sql` script:

1. Open phpMyAdmin
2. Select your database: `u839420047_edubridge_stud`
3. Go to the SQL tab
4. Copy and paste the contents of `modified_applications_table.sql`
5. Click "Go" to execute

**Note:** The new table structure includes:
- All original fields (`id`, `student_id`, `fullname`, `email`, `phone`, `school`, `aps`, `university`, `course`, `created_at`)
- Additional fields for comprehensive application data
- Generated columns that maintain compatibility with existing code
- Proper indexes for better performance

### 5. Create Upload Directory

Create a directory named `uploads` in the same location as your scripts and make sure it has write permissions:

```
chmod 755 uploads
```

### 6. Connect Your Form

Update your HTML form to submit to the handler:

```html
<form action="apply-handler.php" method="post" enctype="multipart/form-data">
    <!-- Your form fields here -->
</form>
```

### 7. Security Considerations

- Move `config.php` outside the web root if possible
- Set appropriate file permissions
- Consider implementing CSRF protection
- Regularly update the PHPMailer library

## Email Configuration

The application is configured to use Hostinger's email service:

- **SMTP Server:** smtp.hostinger.com
- **Port:** 465 (SSL)
- **Email:** Applications@edubridgesa.co.za
- **Authentication:** Enabled

### Email Features
- Automatic email notifications to administrators when applications are submitted
- Uses PHPMailer for reliable email delivery
- Fallback to PHP's built-in mail() function if PHPMailer is unavailable
- Includes applicant details and application ID in notifications

## Testing

Use `test-email.php` to verify your configuration:

1. Navigate to `http://yourdomain.com/test-email.php`
2. Check that all configuration settings are correct
3. Verify PHPMailer is properly installed
4. Confirm upload directory permissions
5. **Remove `test-email.php` before going live**

## Deployment

1. Upload all files to your web server
2. Update the database configuration in `config.php` if needed
3. Create or update the database table using one of the SQL files
4. Ensure the `uploads/` directory has write permissions (755 or 777)
5. Verify PHPMailer is installed in the correct directory
6. Test the application by submitting a sample application
7. Test email functionality using `test-email.php`
8. Remove `test-email.php` after testing

## Troubleshooting

If you encounter issues with email sending:

1. Check your Hostinger email credentials
2. Verify PHPMailer is installed correctly
3. Check server error logs
4. Try the fallback email method

For database connection issues:

1. Verify your database credentials
2. Check if the database and table exist
3. Ensure your Hostinger plan includes database access