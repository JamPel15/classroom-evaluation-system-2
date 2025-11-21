# EDP - Teacher Account Management Guide

## Overview

As an **EDP (Educational Data Processing) Administrator**, you can now create and manage teacher accounts directly from the system interface. Teachers can then login and view their evaluations and schedules.

---

## Access Teacher Account Management

### Step 1: Login as EDP
```
1. Go to login.php
2. Username: [EDP username]
3. Password: [EDP password]
4. Role: Select "EDP"
5. Click Login
```

### Step 2: Navigate to Teacher Accounts
```
From Dashboard or Menu:
→ Sidebar Menu
→ "Manage Teachers" (NEW FEATURE)
```

Or direct URL: `edp/teachers_manage.php`

---

## Create Teacher Accounts

### Method 1: Automatic Account Creation (Recommended)

**For Teachers WITHOUT Accounts:**

1. Click the **"Create Account"** tab
2. See list of all teachers without accounts
3. For each teacher, click **"Create Account"** button
4. System auto-generates:
   - Username (e.g., `kbarrera1`)
   - Password (e.g., `Teacher@1078MC`)
5. **Copy and share credentials with teacher**

**Credentials Format:**
- Username: First initial + Last name + Teacher ID
- Password: `Teacher@XXXX[2-letter code]`
- Department: Auto-filled from teacher record
- Role: Automatically set to "teacher"

---

## Manage Existing Teacher Accounts

### View Teachers with Accounts

Click the **"Teachers with Accounts"** tab to see:
- Teacher name
- Department
- Username
- Account status (Active/Inactive)

### Deactivate Teacher Account

1. Go to "Teachers with Accounts" tab
2. Find the teacher
3. Click **"Deactivate"** button
4. Teacher cannot login anymore
5. All previous evaluations still visible (read-only)

### Reactivate Teacher Account

1. Go to "Teachers with Accounts" tab
2. Find the teacher
3. Click **"Activate"** button
4. Teacher can login again

---

## Dashboard Overview

The "Manage Teacher Accounts" page shows:

```
┌─────────────────────────────────────────────┐
│ MANAGE TEACHER ACCOUNTS                    │
├─────────────────────────────────────────────┤
│                                             │
│ With Accounts: 20 | Need Accounts: 7      │
│                                             │
│ [Tab] Teachers with Accounts      (20)    │
│ [Tab] Create Account              (7)     │
│                                             │
│ ─────────────────────────────────────────  │
│ CREATE ACCOUNT TAB                         │
│ ─────────────────────────────────────────  │
│                                             │
│ Teacher 1                                   │
│ Dept: CAS                                   │
│ ⚠️ No Account                               │
│ [Create Account]                            │
│                                             │
│ Teacher 2                                   │
│ Dept: CTE                                   │
│ ⚠️ No Account                               │
│ [Create Account]                            │
│                                             │
└─────────────────────────────────────────────┘
```

---

## Important Features

### ✓ Automatic Account Features
- Username auto-generated from teacher name
- Password securely generated
- No duplicates allowed
- Department automatically assigned
- Teacher role automatically set

### ✓ Account Status Management
- View which teachers have accounts
- Quick activate/deactivate toggles
- Status display shows current state

### ✓ Security
- Passwords are hashed in database
- Each teacher gets unique credentials
- Cannot create duplicate accounts
- Accounts can be deactivated without deleting

---

## Common Tasks

### Task 1: Create Accounts for All New Teachers

```
1. Login as EDP
2. Go to "Manage Teachers"
3. Click "Create Account" tab
4. See all teachers without accounts
5. For each teacher:
   - Click "Create Account" button
   - Copy username and password
   - Email/share with teacher
6. Done! Teachers can now login
```

### Task 2: Deactivate a Teacher

```
1. Go to "Teachers with Accounts" tab
2. Find the teacher
3. Click "Deactivate" button
4. Confirm
5. Teacher status changes to "Inactive"
6. Teacher cannot login
```

### Task 3: Reactivate a Teacher

```
1. Go to "Teachers with Accounts" tab
2. Find the teacher
3. Click "Activate" button
4. Confirm
5. Teacher status changes to "Active"
6. Teacher can login again
```

### Task 4: Check Account Status

```
1. Go to "Manage Teachers"
2. Check "Teachers with Accounts" tab
3. See list with usernames and status
4. Green badge = Active
5. Red badge = Inactive
```

---

## Teacher Workflow After Account Creation

Once you create a teacher account:

1. **Teacher receives credentials**
   - Username: e.g., `kbarrera1`
   - Password: e.g., `Teacher@1078MC`

2. **Teacher logs in**
   - Goes to `login.php`
   - Selects "Teacher" role
   - Enters username and password

3. **Teacher sees dashboard**
   - Evaluation schedule (if assigned)
   - Room location (if assigned)
   - List of evaluations

4. **Teacher views evaluations**
   - Clicks "View Evaluation"
   - Sees ratings and comments
   - Can print if needed

---

## Integrating with Evaluators

### Workflow Overview

```
1. EDP Creates Teacher Account
        ↓
2. Teacher Logs In
        ↓
3. Evaluator Schedules Evaluation
   (Date, Time, Room)
        ↓
4. Teacher Sees Schedule in Dashboard
        ↓
5. Evaluator Submits Evaluation
        ↓
6. Teacher Sees Evaluation in Dashboard
        ↓
7. Teacher Prints Evaluation (optional)
```

### Evaluator's Role

After you create teacher accounts, evaluators can:

1. Go to **Evaluators > Teachers**
2. Find teacher
3. Click **"Schedule"** button
4. Set evaluation date/time/room
5. Teacher automatically sees it!

---

## Password Management

### If Teacher Forgets Password

**Option 1: Reset via Account Recreation**
```
1. Go to "Teachers with Accounts" tab
2. Click "Deactivate" for that teacher
3. Go to "Create Account" tab
4. Click "Create Account" for that teacher again
5. New password generated
6. Share new credentials
```

**Option 2: Manual Account Reset**
```
Contact system administrator to:
- Reset password directly in database
- Or deactivate/reactivate account
```

### Password Format
```
Generated Format: Teacher@XXXX[Letters]
Example: Teacher@3746WZ

Components:
- Teacher@ = prefix
- XXXX = 4 random digits
- [Letters] = 2 random uppercase letters
```

---

## Troubleshooting

### Problem: Teacher already has account
**Solution**: Go to "Teachers with Accounts" tab and deactivate, then reactivate if needed

### Problem: "Username already exists"
**Solution**: This shouldn't happen with new accounts. Contact admin if it persists.

### Problem: Teacher cannot login
**Solutions**:
1. Check username spelling (case-sensitive)
2. Verify teacher role selected in login
3. Check account status is "Active"
4. Reset password by deactivating/reactivating

### Problem: Cannot create account
**Solutions**:
1. Ensure teacher exists in system
2. Check teacher record has valid name
3. Verify no duplicate account exists
4. Check database connection

---

## Best Practices

### ✓ DO
- Create accounts in batches (e.g., at semester start)
- Keep list of teacher credentials secure
- Deactivate accounts for teachers who leave
- Regularly review active accounts
- Share credentials via secure method

### ✗ DON'T
- Share passwords via email (not secure)
- Forget to deactivate accounts for departed teachers
- Create accounts without teacher knowledge
- Reuse passwords across accounts
- Leave teachers without access when needed

---

## Reports & Monitoring

### Check Account Statistics
```
On "Manage Teachers" page header:
"With Accounts: 20 | Need Accounts: 7"

This shows:
- Total teachers with accounts
- Total teachers without accounts
```

### Monitor Active Accounts
```
"Teachers with Accounts" tab shows:
- All teacher names
- Usernames
- Status (Active/Inactive)
```

---

## Integration Points

### This feature integrates with:

1. **Teacher Login** (login.php)
   - Teachers use created accounts

2. **Teacher Dashboard** (teachers/dashboard.php)
   - Teachers see their evaluations

3. **Evaluators** (evaluators/teachers.php)
   - Can schedule evaluations

4. **Users Management** (edp/users.php)
   - Track all system accounts

---

## System Requirements

- EDP user role
- Database connection active
- Teachers already exist in system
- Unique usernames generation possible

---

## Support

### For More Information:
- See: `TEACHER_SYSTEM_README.md` - Complete guide
- See: `START_HERE.md` - Quick reference
- See: `IMPLEMENTATION_SUMMARY.md` - What's new

### Quick Links:
- Teacher Account Management: `/edp/teachers_manage.php`
- Evaluator Teachers: `/evaluators/teachers.php`
- Teacher Dashboard: `/teachers/dashboard.php`
- Login Page: `/login.php`

---

## Summary

**As EDP, you can now:**

✓ Create teacher login accounts with one click
✓ View all teachers and their account status
✓ Activate and deactivate accounts
✓ Auto-generate secure usernames and passwords
✓ Manage account credentials
✓ Monitor account statistics
✓ Integrate with evaluator scheduling
✓ Enable teachers to view their evaluations

**Teachers can then:**

✓ Login with generated credentials
✓ See their evaluation schedule
✓ Know their room location
✓ View all their evaluations
✓ Read evaluator feedback
✓ Print evaluation reports

---

**System Status: ✅ READY TO USE**

EDP can now manage all teacher accounts from the admin interface!
