# Dual Login Authentication Guide

## Overview
The EduBridge SA student login system now supports two authentication methods:
1. **Application Reference Number** - For students who have submitted applications
2. **Password** - For registered users with verified accounts

## How to Use

### Method 1: Application Reference Number Login
This method is for students who have already submitted an application through the system.

**Requirements:**
- Valid email address used during application
- Application reference number (provided after application submission)

**Steps:**
1. Go to the student login page
2. Ensure "Application Reference" is selected (default option)
3. Enter your email address
4. Enter your application reference number
5. Click "Login"

### Method 2: Password Login
This method is for users who have created a registered account with a password.

**Requirements:**
- Valid email address
- Account password
- Verified email address
- Active account status

**Steps:**
1. Go to the student login page
2. Click on "Password" to switch authentication methods
3. Enter your email address
4. Enter your password
5. Use the eye icon to toggle password visibility if needed
6. Click "Login"

## Technical Implementation

### Frontend Features
- **Toggle Interface**: Radio buttons to switch between authentication methods
- **Dynamic Form Fields**: Form fields change based on selected method
- **Password Visibility**: Toggle button to show/hide password
- **Form Validation**: Different validation rules for each method
- **Responsive Design**: Works on all device sizes

### Backend Features
- **Dual Authentication Logic**: Handles both reference number and password verification
- **Database Integration**: Queries both `applications` and `users` tables
- **Session Management**: Sets appropriate session variables based on auth method
- **Security Features**: Rate limiting, input validation, and secure password handling
- **Error Handling**: Specific error messages for each authentication method

### Database Tables Used
- **applications**: For reference number authentication
  - Columns: `email_address`, `reference_number`, `full_name`, `surname`, `status`
- **users**: For password authentication
  - Columns: `email`, `password_hash`, `name`, `status`, `email_verified`

## Security Features

### Rate Limiting
- Maximum 5 login attempts per IP address within 15 minutes
- Applies to both authentication methods

### Input Validation
- Email format validation
- Required field validation based on selected method
- Protection against invalid cached values

### Password Security
- Passwords are hashed using PHP's `password_hash()` function
- Password verification uses `password_verify()` for secure comparison
- Password visibility toggle for user convenience

### Session Security
- Different session variables set based on authentication method
- Session regeneration on successful login
- Proper session cleanup on logout

## Error Messages

### Reference Number Authentication
- "Please enter your email address."
- "Please enter your application reference number."
- "Invalid email or reference number. Please check your details and try again."

### Password Authentication
- "Please enter your email address."
- "Please enter your password."
- "Invalid email or password. Please check your details and try again."

### General Errors
- "Please enter a valid email address."
- "Too many login attempts. Please try again in 15 minutes."
- "A system error occurred. Please try again later."

## Testing

### Test File
Use `test_dual_login.php` to verify:
- Database table structure
- Sample data availability
- Authentication method functionality
- Form implementation status

### Manual Testing
1. Test reference number login with valid application data
2. Test password login with valid user account
3. Test form toggle functionality
4. Test validation error messages
5. Test rate limiting behavior

## Troubleshooting

### Common Issues

**Reference Number Login Not Working:**
- Verify the applications table exists and has data
- Check that email_address and reference_number match exactly
- Ensure application status is not 'deleted'

**Password Login Not Working:**
- Verify the users table exists
- Check that user account is active and email is verified
- Ensure password was hashed correctly during registration

**Form Toggle Not Working:**
- Check that JavaScript is enabled in browser
- Verify all form elements have correct IDs
- Check browser console for JavaScript errors

**Database Connection Issues:**
- Verify config.php database settings
- Check that PDO connection is established
- Ensure required database tables exist

## Future Enhancements

### Potential Improvements
- **Remember Me**: Option to stay logged in
- **Two-Factor Authentication**: Additional security layer
- **Social Login**: Login with Google/Facebook
- **Password Reset**: Self-service password recovery
- **Account Linking**: Link application and user accounts

### Migration Path
- Existing applications continue to work with reference number login
- New users can register for password-based accounts
- Future: Option to upgrade reference-based login to password-based account

## Support

For technical support or questions about the dual login system:
- Email: applications@edubridgesa.co.za
- Check the test page: `test_dual_login.php`
- Review error logs for detailed debugging information

---
*Last updated: January 2025*
*EduBridge SA - Student Application System*