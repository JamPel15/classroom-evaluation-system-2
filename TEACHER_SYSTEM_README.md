# Teacher Account System - Complete Guide

## Overview
The teacher account system allows teachers to:
- ✓ Have secure login credentials
- ✓ View their evaluation schedule and room location
- ✓ See all evaluations they have received
- ✓ View detailed evaluation reports with ratings and feedback
- ✓ Print evaluation reports

## System Architecture

### User Roles
- **EDP**: System administrator
- **President/Vice President**: Leaders who evaluate across all departments
- **Dean/Principal/Chairperson/Subject Coordinator**: Evaluators (evaluate within their department)
- **Teacher**: NEW - Teachers who view their evaluations

### Database Structure

#### Users Table (Extended)
- `id`: User ID
- `username`: Unique username for login
- `password`: Hashed password
- `name`: Full name
- `role`: User role (now includes 'teacher')
- `department`: Department
- `status`: active/inactive

#### Teachers Table (Enhanced)
- `id`: Teacher ID
- `user_id`: Link to Users table (NEW)
- `name`: Teacher name
- `department`: Department
- `photo`: Photo filename
- `status`: active/inactive
- `evaluation_schedule`: DateTime when evaluation is scheduled (NEW)
- `evaluation_room`: Room/location for evaluation (NEW)
- `created_at`: Created timestamp
- `updated_at`: Updated timestamp

#### Evaluations Table
- `id`: Evaluation ID
- `teacher_id`: Reference to teacher being evaluated
- `evaluator_id`: Reference to evaluator (user)
- `academic_year`: Year of evaluation
- `semester`: Semester
- `subject_observed`: Subject being taught
- `observation_date`: Date of observation
- `observation_time`: Time of observation
- `observation_type`: Type of evaluation
- And many more fields for ratings and feedback

#### Evaluation Details Table
- `evaluation_id`: Reference to evaluation
- `category`: communications, management, assessment
- `criterion_index`: Index of criterion
- `criterion_text`: Description of criterion
- `rating`: Numerical rating (1-5 typically)
- `comments`: Evaluator's comments

## File Structure

### New Teacher-Specific Files

```
teachers/
├── dashboard.php           - Teacher's main dashboard
│   ├── Shows evaluation schedule and room
│   ├── Lists all evaluations received
│   └── Links to view individual evaluations
└── view-evaluation.php     - Detailed evaluation viewer
    ├── Shows all ratings by category
    ├── Displays evaluator comments
    ├── Shows overall scores
    └── Printable format

generate_teacher_accounts.php  - Creates teacher accounts
teacher-system-status.php      - System verification page
```

### Modified Files

```
login.php                  - Added 'Teacher' role to dropdown
index.php                  - Added teacher redirect logic
auth/login-process.php     - Added teacher_id to session
config/database.php        - (No changes needed)
models/Teacher.php         - (Working with new user_id column)
models/User.php            - (Working with teacher role)
```

## Installation & Setup

### Step 1: Database Migration
The system automatically added these columns when you ran the migration:
```sql
ALTER TABLE teachers ADD COLUMN user_id INT DEFAULT NULL;
ALTER TABLE teachers ADD COLUMN evaluation_schedule DATETIME DEFAULT NULL;
ALTER TABLE teachers ADD COLUMN evaluation_room VARCHAR(255) DEFAULT NULL;
```

### Step 2: Generate Teacher Accounts
Teacher accounts were created automatically with these credentials:
- **Username Format**: First initial + Last name + Teacher ID
  - Example: `kbarrera1` (Kenneth Barrera, ID 1)
- **Password**: Auto-generated format `Teacher@XXXX[2-letter code]`
- **Role**: teacher
- **Department**: Same as teacher's assigned department
- **Status**: active

### Step 3: Verify Installation
Visit: `http://your-domain/teacher-system-status.php`

This page shows:
- ✓ All teacher accounts created
- ✓ Database columns properly configured
- ✓ All pages and files in place
- ✓ Login configuration correct

## User Workflows

### For Teachers

#### 1. Login
```
1. Navigate to login.php
2. Enter username (e.g., kbarrera1)
3. Select "Teacher" from Role dropdown
4. Enter password
5. Click Login
```

#### 2. Dashboard
- View evaluation schedule (date, time, location)
- See list of all evaluations received
- Status: Completed or Pending
- Link to view each evaluation

#### 3. View Evaluation
- Click "View Evaluation" button
- See all ratings organized by category:
  - Communications (5 criteria)
  - Management/Course Design (12 criteria)
  - Assessment/Testing (6 criteria)
- View evaluator comments for each criterion
- See overall evaluation score
- Print button to generate PDF

### For Evaluators (Teachers Management)

#### 1. Assign Evaluation Schedule
```
1. Go to Evaluators > Teachers Management
2. Find teacher in list
3. Click "Schedule" button
4. Enter:
   - Date and time of evaluation
   - Room/location where evaluation will occur
5. Save
```

#### 2. Complete Evaluation
```
1. Go to Evaluators > Evaluation
2. Select teacher to evaluate
3. Fill out evaluation form (all 3 parts)
4. Provide ratings for each criterion
5. Add optional comments
6. Submit evaluation
```

#### 3. Evaluation Appears in Teacher Portal
- Automatically synced to teacher's dashboard
- Teacher can view within 24 hours

## Key Features

### 1. Secure Authentication
- Teacher accounts use hashed passwords
- Role-based access control
- Session management

### 2. Evaluation Tracking
- Teachers can see complete evaluation history
- Each evaluation includes:
  - Evaluator name and role
  - Date of evaluation
  - Subject observed
  - Detailed ratings
  - Evaluator comments
  - Overall score

### 3. Schedule Management
- Teachers see when evaluation is scheduled
- Room location provided
- Can plan accordingly

### 4. Print Functionality
- Professional-formatted PDF
- All ratings and feedback included
- Can be saved for records

### 5. Data Security
- Teachers only see their own evaluations
- Cannot modify or delete evaluations
- Read-only access to evaluation data

## Account Management

### Reset Teacher Password
If a teacher forgets their password, regenerate accounts:
```bash
php generate_teacher_accounts.php
```

**Note**: This will show new passwords for all teachers. Existing accounts are not recreated (checked by username).

### Update Evaluation Schedule
1. Go to Evaluators > Teachers Management
2. Find teacher
3. Click "Schedule" button
4. Update date, time, and room
5. Click Save

### Deactivate Teacher Account
1. Go to Evaluators > Teachers Management
2. Click "Deactivate" button
3. Teacher can no longer login
4. Reactivate by clicking "Activate" button

## Troubleshooting

### Teacher Can't Login
- Check username format (e.g., kbarrera1)
- Verify "Teacher" role is selected
- Confirm account is active (not deactivated)
- Check database connection

### Evaluation Not Appearing
- Ensure evaluation is marked as "completed"
- Check teacher_id matches correctly
- Verify teacher hasn't been deactivated
- Wait 5 minutes for cache to refresh

### Schedule/Room Not Visible
- Evaluator must click "Schedule" button to assign
- Date/time must be set (not NULL)
- Room field must have a value

### Print Not Working
- Use modern browser (Chrome, Firefox, Edge)
- Ensure JavaScript is enabled
- Try Print → Save as PDF

## Example Credentials

Here are sample teacher accounts that were created:

| Teacher Name | Username | Department | Status |
|---|---|---|---|
| KENNETH BARRERA | kbarrera1 | CAS | Active |
| SHAGNE LIM | slim2 | CAS | Active |
| Dr. Robert Lim | dlim3 | CTE | Active |
| Prof. Anna Reyes | preyes4 | CTE | Active |
| Reyniemor Anciano | ranciano5 | CCJE | Active |

(27 total teacher accounts created - use these usernames to login)

## System Permissions

### Teacher Can:
- ✓ View their own evaluations
- ✓ View their evaluation schedule
- ✓ View room location
- ✓ Print evaluations
- ✓ See evaluator comments and ratings
- ✓ Logout

### Teacher Cannot:
- ✗ Edit evaluations
- ✗ Delete evaluations
- ✗ See other teachers' evaluations
- ✗ Create new evaluations
- ✗ Manage evaluators
- ✗ Access EDP functions

## Technical Specifications

- **Language**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Authentication**: Session-based
- **Password Security**: PASSWORD_DEFAULT hashing
- **Frontend**: Bootstrap 5, Font Awesome icons
- **Responsive**: Mobile-friendly design

## Support & Maintenance

### Regular Tasks
- Monitor teacher account status
- Archive old evaluations (if needed)
- Backup database monthly

### System Updates
- Update Bootstrap/Font Awesome as needed
- Keep PHP dependencies current
- Monitor error logs

### Security
- Change database passwords quarterly
- Audit login attempts monthly
- Remove inactive accounts annually

## API/Integration Points

Teachers dashboard calls these endpoints:
- `../config/database.php` - Database connection
- `../auth/logout.php` - Session logout
- `dashboard.php` - Load schedule and evaluations
- `view-evaluation.php?eval_id=X` - View specific evaluation

## Future Enhancements

Potential additions:
- Email notifications for new evaluations
- Export evaluation history as PDF
- Discussion/comments section
- Goal tracking and improvement plans
- Anonymous evaluation feedback system
- Performance analytics for teachers

---

**Last Updated**: November 2025
**System Version**: 1.0
**Status**: Fully Operational
