# 🔄 EduBridgeSA Application System Reset Instructions

## ⚠️ CRITICAL WARNING
**This process will permanently delete all application data. This action is IRREVERSIBLE!**

## 📋 What Will Be Reset

### Database Records
- All student applications
- Application status history
- Document verification records
- Application-related session data

### Files
- ID document copies
- Matric certificates
- Additional documents
- Proof of residence files

### What Will NOT Be Affected
- Database table structures
- User accounts (students/admins)
- System configuration
- Directory structures

## 🛡️ Safety Requirements

### 1. MANDATORY Database Backup
```bash
# MySQL backup command
mysqldump -u [username] -p [database_name] > backup_$(date +%Y%m%d_%H%M%S).sql

# Example:
mysqldump -u root -p edubridgesa > backup_20250128_143000.sql
```

### 2. RECOMMENDED File Backup
```bash
# Backup uploads directory
cp -r uploads/ uploads_backup_$(date +%Y%m%d_%H%M%S)/

# Or create a tar archive
tar -czf uploads_backup_$(date +%Y%m%d_%H%M%S).tar.gz uploads/
```

### 3. Admin Access Required
- Only users with admin privileges can execute the reset
- Must be logged in as an administrator

## 🚀 Execution Methods

### Method 1: Full System Reset (Recommended)
1. Navigate to: `reset_application_system.php`
2. Follow the pre-reset checklist
3. Complete all confirmation checkboxes
4. Click "RESET APPLICATION SYSTEM"

### Method 2: Files Only
1. Navigate to: `cleanup_uploaded_files.php`
2. Review current file status
3. Click "Delete All Files"

### Method 3: Database Only (Manual)
```sql
-- Execute these SQL commands in order
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM application_status_history;
DELETE FROM application_documents;
DELETE FROM applications;

ALTER TABLE application_status_history AUTO_INCREMENT = 1;
ALTER TABLE application_documents AUTO_INCREMENT = 1;
ALTER TABLE applications AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
```

## 📊 Pre-Reset System Check

### Check Current Data
```sql
-- Count applications
SELECT COUNT(*) as total_applications FROM applications;

-- Count documents
SELECT COUNT(*) as total_documents FROM application_documents;

-- Count status records
SELECT COUNT(*) as total_status_records FROM application_status_history;
```

### Check File System
```bash
# Count files in uploads
find uploads/ -type f | wc -l

# Check disk usage
du -sh uploads/
```

## ✅ Post-Reset Verification

### 1. Database Verification
```sql
-- Verify tables are empty
SELECT COUNT(*) FROM applications;        -- Should return 0
SELECT COUNT(*) FROM application_documents; -- Should return 0
SELECT COUNT(*) FROM application_status_history; -- Should return 0

-- Verify table structure is intact
DESCRIBE applications;
DESCRIBE application_documents;
DESCRIBE application_status_history;
```

### 2. File System Verification
```bash
# Check uploads directories are empty
ls -la uploads/
ls -la uploads/id_documents/
ls -la uploads/matric_certificates/
ls -la uploads/additional_documents/
```

### 3. Application Testing
1. Navigate to `apply_test.php`
2. Submit a test application
3. Verify it processes correctly
4. Check files upload properly
5. Confirm email notifications work

## 🔧 Troubleshooting

### Common Issues

#### Permission Errors
```bash
# Fix file permissions
chmod 755 uploads/
chmod 755 uploads/*/
chmod 644 uploads/*.*
```

#### Database Connection Issues
- Verify database credentials in `config.php`
- Check database server is running
- Ensure user has DELETE privileges

#### Incomplete Reset
- Check error logs: `error.log`
- Review reset log output
- Manually verify remaining data

### Recovery Options

#### Restore from Backup
```bash
# Restore database
mysql -u [username] -p [database_name] < backup_file.sql

# Restore files
cp -r uploads_backup_*/ uploads/
```

#### Partial Recovery
- Individual table restoration possible
- Selective file restoration from backup
- Contact technical support if needed

## 📞 Support Information

### Log Files to Check
- `error.log` - System errors
- `simple_form_debug.log` - Form submission logs
- Reset script output - Displayed during execution

### Contact Information
- Technical Support: [Your support contact]
- Emergency Contact: [Emergency contact]
- Documentation: This file and inline help

## 🎯 Best Practices

### Before Reset
1. ✅ Create complete backup
2. ✅ Notify stakeholders
3. ✅ Schedule during low-usage period
4. ✅ Test backup restoration process
5. ✅ Document current system state

### During Reset
1. ✅ Monitor progress logs
2. ✅ Don't interrupt the process
3. ✅ Keep backup files safe
4. ✅ Note any error messages

### After Reset
1. ✅ Verify system functionality
2. ✅ Test application workflow
3. ✅ Update documentation
4. ✅ Inform users system is ready
5. ✅ Monitor for issues

## 📝 Reset Checklist

### Pre-Reset
- [ ] Database backup created and verified
- [ ] File backup created (recommended)
- [ ] Admin access confirmed
- [ ] Stakeholders notified
- [ ] Maintenance window scheduled

### During Reset
- [ ] Reset script executed successfully
- [ ] No errors in log output
- [ ] All confirmations completed
- [ ] Process completed without interruption

### Post-Reset
- [ ] Database tables verified empty
- [ ] File directories verified empty
- [ ] Table structures intact
- [ ] Test application submitted successfully
- [ ] Email notifications working
- [ ] System ready for production use

---

**Remember: This reset is irreversible. Always backup your data first!**