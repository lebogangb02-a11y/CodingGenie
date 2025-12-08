# EdubridgeSA Performance Issues - Quick Reference Checklist

## ✅ Critical Issues Found: 28 Total

---

## 1. N+1 QUERY PATTERNS (6 Issues)

### 🔴 CRITICAL: get-application-tracker-data.php
- **Lines:** 37-85
- **Problem:** 1 query fetches apps, then 3 queries per app in loop
- **Impact:** 301 queries instead of 4 (for 100 apps)
- **Fix:** Use batch queries with array mapping
- **Status:** ❌ NOT FIXED

### 🟠 HIGH: enhanced_bot_engine.php
- **Lines:** 100-130
- **Problem:** Loads ALL FAQs, loops through with calculations
- **Impact:** Memory exhaustion with large FAQ databases
- **Fix:** Use FULLTEXT index, batch process top N results
- **Status:** ❌ NOT FIXED

### 🟠 HIGH: application_status.php
- **Lines:** 120-127
- **Problem:** Multiple documents processed in loop
- **Impact:** Repeated array operations
- **Fix:** Use GROUP_CONCAT in single query
- **Status:** ❌ NOT FIXED

### Others to review: (3 more files)
- [ ] admin_messages.php - Line 421
- [ ] manage_students.php - Lines 111-114
- [ ] export_tools.php - Line 115

---

## 2. SELECT * USAGE (21+ Issues)

### Files with SELECT * to Replace:

| File | Lines | Priority | Fix |
|------|-------|----------|-----|
| knowledge_base_manager.php | 176 | 🟡 HIGH | Specify: id, question, answer, category, keywords |
| enhanced_bot_engine.php | 69, 100, 190, 223 | 🟡 HIGH | Specify: id, question, answer, keywords |
| upload-documents.php | 50, 63 | 🟡 HIGH | Specify relevant columns |
| upload_document.php | 161 | 🟡 HIGH | Specify: id, application_id, document_type, file_path |
| support-tickets.php | 39 | 🟡 HIGH | Specify: id, ticket_number, email, student_id, subject |
| student_login.php | 99 | 🟡 HIGH | Specify: id, email_address, reference_number, status |
| student-login.php | 89 | 🟡 HIGH | Specify: id, email_address, reference_number, status |
| student_profile.php | 34 | 🟡 HIGH | Specify relevant columns |
| student-apply.php | 104 | 🟡 HIGH | Specify: id, name, code |
| verify_progress_system.php | 131 | 🟡 HIGH | Specify relevant columns |
| simple_db_test.php | 62, 83 | 🟡 HIGH | Specify relevant columns |
| student-application.php | 63 | 🟡 HIGH | Specify: id, name, code |
| setup-student-account.php | 57 | 🟡 HIGH | Specify relevant columns |
| admin_activity_log.php | Various | 🟡 HIGH | Specify: admin_username, action, details, created_at |
| application_status.php | 91, 95 | 🟡 HIGH | Specify relevant columns |
| **+ 6+ more files** | Various | 🟡 HIGH | See PERFORMANCE_AUDIT_REPORT.md |

**Quick Fix Strategy:**
```bash
# Find all SELECT * statements
grep -r "SELECT \*" EdubridgeSA/*.php

# For each match, replace with specific columns
# Example: sed -i 's/SELECT \* FROM/SELECT id, name, email FROM/g' file.php
```

---

## 3. MISSING PAGINATION (4 Critical Issues)

### 🔴 CRITICAL: Queries Without LIMIT

| File | Line | Query | Fix |
|------|------|-------|-----|
| manage_students.php | 111-114 | SELECT DISTINCT grade/province/program/status | Add LIMIT 100 + Caching |
| export_tools.php | 115 | SELECT * FROM applications | Batch with LIMIT 1000, stream results |
| admin_messages.php | 421 | SELECT * FROM contact_enquiries | Add LIMIT 100 + pagination |
| enhanced_bot_engine.php | 100 | SELECT * FROM faq_knowledge_base | Add LIMIT 100 or index active ones |

**Pagination Template:**
```php
$pageSize = 100;
$offset = 0;

do {
    $query = "SELECT ... FROM table LIMIT ? OFFSET ?";
    $stmt->execute([$pageSize, $offset]);
    $rows = $stmt->fetchAll();
    
    // Process rows
    
    $offset += $pageSize;
} while (count($rows) === $pageSize);
```

---

## 4. MISSING DATABASE INDEXES (5 Issues)

### Required Indexes:

```sql
-- Priority 1: Foreign Keys
ALTER TABLE applications ADD INDEX idx_user_id (user_id);
ALTER TABLE chat_messages ADD INDEX idx_conversation_id (conversation_id);
ALTER TABLE application_documents ADD INDEX idx_application_id (application_id);

-- Priority 2: WHERE Clause Columns
ALTER TABLE applications ADD INDEX idx_email (email_address);
ALTER TABLE applications ADD INDEX idx_status (status);
ALTER TABLE applications ADD INDEX idx_created_at (created_at);
ALTER TABLE users ADD INDEX idx_email (email);

-- Priority 3: Composite Indexes
ALTER TABLE applications ADD INDEX idx_status_created (status, created_at DESC);
ALTER TABLE chat_messages ADD INDEX idx_conv_created (conversation_id, created_at DESC);

-- Priority 4: Full-Text Search
ALTER TABLE faq_knowledge_base ADD FULLTEXT INDEX idx_keywords (keywords, question);
```

**Verification:**
```sql
-- Check what indexes exist
SHOW INDEX FROM applications;
SHOW INDEX FROM chat_messages;
SHOW INDEX FROM users;

-- Check query performance
EXPLAIN SELECT * FROM applications WHERE email_address = 'test@example.com';
```

---

## 5. INEFFICIENT ARRAY OPERATIONS (3 Issues)

### Issue: array_intersect in loop
**File:** application_status.php, Lines 120-127
**Problem:** O(n²) operation
**Fix:** Use single database query with GROUP_CONCAT or COUNT

### Issue: in_array in lookup loop
**File:** admin_dashboard.php, Lines 81-82
**Problem:** O(n) per lookup × n items = O(n²)
**Fix:** Use array_flip() first for O(1) lookups

### Issue: array_merge in loop
**File:** manage_students.php, Lines 474-476
**Problem:** Expensive operation repeated per row
**Fix:** Build base params once, use array + operator

---

## 6. REPEATED INCLUDES (Multiple Files)

### Pattern Found:
```php
// Every file includes these:
require_once 'session_config.php';
require_once 'config.php';
require_once 'profile-utils.php';
require_once 'progress_utils.php';
```

### Solution: Create bootstrap.php
```php
// bootstrap.php - Load once
require_once 'session_config.php';
require_once 'config.php';
require_once 'profile-utils.php';
require_once 'progress_utils.php';
```

Then in all other files:
```php
require_once 'bootstrap.php';
```

---

## 🚀 QUICK FIX PRIORITY ORDER

### 🟥 Do First (High Impact, Quick):

- [ ] **Add LIMIT clauses** - 30 minutes
  - manage_students.php line 111-114
  - export_tools.php line 115
  - admin_messages.php line 421
  - enhanced_bot_engine.php line 100

- [ ] **Add database indexes** - 30 minutes
  ```sql
  ALTER TABLE applications ADD INDEX idx_user_id (user_id);
  ALTER TABLE applications ADD INDEX idx_email (email_address);
  ALTER TABLE applications ADD INDEX idx_status (status);
  ALTER TABLE applications ADD INDEX idx_created_at (created_at);
  ALTER TABLE chat_messages ADD INDEX idx_conversation_id (conversation_id);
  ALTER TABLE users ADD INDEX idx_email (email);
  ```

- [ ] **Create bootstrap.php** - 30 minutes
  - Consolidates 5+ repeated includes
  - Used by: student-dashboard.php and 15+ other files

### 🟨 Do Second (Major Performance Gain):

- [ ] **Fix N+1 in get-application-tracker-data.php** - 2 hours
  - 301 queries → 4 queries
  - 70% faster page load

- [ ] **Replace SELECT *** - 2 hours
  - Find: `SELECT \*`
  - Replace with specific columns
  - Affects 21+ locations

### 🟩 Do Third (Ongoing):

- [ ] Implement query caching for filters
- [ ] Add slow query logging
- [ ] Monitor with EXPLAIN queries
- [ ] Implement Redis caching layer

---

## 📊 EXPECTED PERFORMANCE GAINS

| Fix | Time Saved | Effort | Impact |
|-----|-----------|--------|--------|
| Add LIMIT clauses | 20-30% | ⭐ Easy | Prevents OOM |
| Add indexes | 50-70% | ⭐ Easy | WHERE clauses |
| Fix N+1 queries | 70% | ⭐⭐ Medium | Page loads |
| Replace SELECT * | 10-20% | ⭐⭐ Medium | Data transfer |
| Create bootstrap | 5-10% | ⭐ Easy | File I/O |
| **TOTAL** | **~80%** | **⭐⭐ Medium** | **Major** |

---

## 🔍 HOW TO VERIFY FIXES

### Before Optimization:
```php
$start = microtime(true);
// ... load page ...
$time = (microtime(true) - $start) * 1000;
echo "Page Load Time: {$time}ms";
echo "Queries: " . getQueryCount(); // Need to implement
echo "Memory: " . memory_get_peak_usage() / 1024 / 1024 . "MB";
```

### Performance Metrics to Track:
- [ ] Page load time: Target < 1 second
- [ ] Query count: Target < 10 queries
- [ ] Memory usage: Target < 10MB for 100 records
- [ ] Database response: Target < 50ms

---

## 📋 FILE-BY-FILE ACTION ITEMS

### HIGH PRIORITY (Do This Week):

- [ ] **get-application-tracker-data.php** - Fix N+1 queries
- [ ] **manage_students.php** - Add LIMIT to lines 111-114
- [ ] **export_tools.php** - Add pagination to line 115
- [ ] **bootstrap.php** - Create new file
- [ ] **Database** - Run 8 ALTER TABLE commands for indexes

### MEDIUM PRIORITY (Do Next Week):

- [ ] **enhanced_bot_engine.php** - 4 SELECT * fixes + LIMIT
- [ ] **upload-documents.php** - 2 SELECT * fixes
- [ ] **application_status.php** - 2 SELECT * fixes + optimize arrays
- [ ] **All other files** - Replace SELECT * (17+ locations)

### LOW PRIORITY (Ongoing):

- [ ] Query caching implementation
- [ ] Slow query log monitoring
- [ ] Performance regression testing
- [ ] Load testing with 10K+ records

---

## 💡 CODE REVIEW CHECKLIST FOR FUTURE PULL REQUESTS

When reviewing new code, check for:

- [ ] No `SELECT *` - Specify columns
- [ ] No queries in loops - Use batch queries
- [ ] All WHERE queries have indexes - Check EXPLAIN
- [ ] Pagination on lists > 50 items - Add LIMIT
- [ ] No N+1 patterns - Use JOINs or array mapping
- [ ] Database queries cached if repeated - Use file/Redis cache
- [ ] Proper error handling - Try/catch with logging
- [ ] Query count monitored - Log count in comments

---

## 📞 NEED HELP?

1. **Detailed Analysis:** See `PERFORMANCE_AUDIT_REPORT.md`
2. **Code Examples:** See `OPTIMIZATION_IMPLEMENTATION_GUIDE.md`
3. **Specific File Issues:** Check line numbers in the reports
4. **SQL Help:** See "4. MISSING DATABASE INDEXES" section above

---

**Created:** December 8, 2025  
**Status:** Ready for Implementation  
**Estimated Total Fix Time:** 8-10 hours  
**Expected Performance Improvement:** ~80%
