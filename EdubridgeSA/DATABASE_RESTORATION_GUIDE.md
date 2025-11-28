# Database Restoration Guide - EduBridge SA

## Overview
This guide provides comprehensive instructions for restoring deleted table-related files and managing the database schema for the EduBridge SA application system.

## Restored Files

### 1. Core Table Creation Files
- **`create_users_table.sql`** - Complete users table structure with authentication, verification, and session management
- **`create_applications_table.sql`** - Comprehensive applications table with all form fields and document management
- **`database_setup.sql`** - Complete database initialization script for fresh installations
- **`database_migration.sql`** - Schema update script for existing databases
- **`database_backup.sql`** - Backup procedures and verification queries

### 2. Table Structures Restored

#### Users Table (`users`)
```sql
- id (Primary Key)
- email (Unique, Authentication)
- password_hash (Secure password storage)
- first_name, last_name (Personal info)
- phone, date_of_birth, id_number (Contact/Identity)
- student_id (Unique student identifier)
- status (pending/active/suspended/inactive)
- verification_token, verified_at (Email verification)
- created_at, updated_at, last_login (Timestamps)
- Indexes: email, student_id, status, verification_token
```

#### Applications Table (`applications`)
```sql
- application_id (Primary Key)
- Personal Information (name, ID, DOB, gender, etc.)
- Contact Information (address, phone, email, etc.)
- Emergency Contact (name, relationship, phone, email)
- Academic Information (graduation year, APS, school, etc.)
- Program Information (choices, study mode, motivation)
- Additional Information (disability, previous tertiary)
- Document Paths (ID, matric, payment proof)
- Consent Fields (terms, privacy, marketing)
- System Fields (dates, status, timestamps)
- Indexes: email, id_number, application_date, status
```

#### Supporting Tables
- **`application_documents`** - Document upload management
- **`user_sessions`** - Advanced session tracking (optional)
- **`password_reset_tokens`** - Password reset functionality (optional)
- **`admin_users`** - Administrative user management (optional)
- **`migrations`** - Database change tracking

## Installation Instructions

### For Fresh Database Setup
1. **Run the complete setup script:**
   ```sql
   mysql -u u839420047_Edubridge -p u839420047_applications < database_setup.sql
   ```

2. **Verify installation:**
   ```sql
   SHOW TABLES;
   DESCRIBE users;
   DESCRIBE applications;
   ```

### For Existing Database Updates
1. **Create backup first:**
   ```bash
   mysqldump -u u839420047_Edubridge -p u839420047_applications > backup_before_migration.sql
   ```

2. **Run migration script:**
   ```sql
   mysql -u u839420047_Edubridge -p u839420047_applications < database_migration.sql
   ```

3. **Verify migration:**
   ```sql
   SELECT * FROM migrations;
   ```

### Individual Table Creation
If you need to create tables individually:

1. **Users table only:**
   ```sql
   mysql -u u839420047_Edubridge -p u839420047_applications < create_users_table.sql
   ```

2. **Applications table only:**
   ```sql
   mysql -u u839420047_Edubridge -p u839420047_applications < create_applications_table.sql
   ```

## File Dependencies

### Files that depend on these tables:
- `student-login.php` - Requires `users` table
- `create-profile.php` - Requires `users` table
- `verify-email.php` - Requires `users` table
- `resend-verification.php` - Requires `users` table
- `apply.php` - Requires `applications` table
- `handleApplicationSubmit.php` - Requires `applications` table
- `manage_admins.php` - Requires `admin_users` table (if created)

### Configuration files:
- `config.php` - Database connection settings
- `session_config.php` - Session management (works with `users` table)

## Security Considerations

### Default Admin Account
- **Username:** admin
- **Email:** admin@edubridgesa.co.za
- **Default Password:** admin123
- **⚠️ CRITICAL:** Change this password immediately after setup!

### Database Security
- All passwords are hashed using PHP's `password_hash()`
- Email verification tokens are cryptographically secure
- Session management includes regeneration and timeout
- Indexes are optimized for performance and security

## Backup and Recovery

### Regular Backup Schedule
```bash
# Daily backup (keep 7 days)
mysqldump -u u839420047_Edubridge -p u839420047_applications > daily_backup_$(date +%Y%m%d).sql

# Weekly compressed backup (keep 4 weeks)
mysqldump -u u839420047_Edubridge -p u839420047_applications | gzip > weekly_backup_$(date +%Y%m%d).sql.gz

# Monthly backup (keep 12 months)
mysqldump -u u839420047_Edubridge -p u839420047_applications > monthly_backup_$(date +%Y%m%d).sql
```

### Recovery Process
```bash
# Restore from backup
mysql -u u839420047_Edubridge -p u839420047_applications < backup_file.sql

# Restore compressed backup
gunzip < backup_file.sql.gz | mysql -u u839420047_Edubridge -p u839420047_applications
```

## Troubleshooting

### Common Issues

1. **"Table doesn't exist" errors:**
   - Run `database_setup.sql` for fresh installation
   - Run `database_migration.sql` for existing databases

2. **"Column doesn't exist" errors:**
   - Run the migration script to add missing columns
   - Check if you're using the latest table structure

3. **Foreign key constraint errors:**
   - Ensure parent tables exist before creating child tables
   - Check the order of table creation in setup script

4. **Permission errors:**
   - Verify database user has CREATE, ALTER, INSERT, UPDATE, DELETE privileges
   - Check database connection settings in `config.php`

### Verification Queries
```sql
-- Check all tables exist
SHOW TABLES;

-- Check users table structure
DESCRIBE users;

-- Check applications table structure  
DESCRIBE applications;

-- Check data integrity
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM applications;

-- Check indexes
SHOW INDEX FROM users;
SHOW INDEX FROM applications;
```

## Migration History

### Version 1.0 (Current)
- ✅ Restored `create_users_table.sql` with complete structure
- ✅ Verified `create_applications_table.sql` (already existed)
- ✅ Created `database_setup.sql` for fresh installations
- ✅ Created `database_migration.sql` for schema updates
- ✅ Created backup and recovery procedures
- ✅ Added supporting tables for advanced functionality

## Next Steps

1. **Upload files to production server**
2. **Run appropriate setup/migration script**
3. **Change default admin password**
4. **Test all functionality**
5. **Set up regular backup schedule**
6. **Monitor database performance**

## Support

For issues with database restoration:
1. Check this guide first
2. Verify database connection settings
3. Ensure proper file permissions
4. Check server error logs
5. Test with a fresh database if needed

---

**Last Updated:** 2024-01-24  
**Version:** 1.0  
**Status:** All table-related files restored and functional