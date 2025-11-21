# AI Classroom Evaluation System - Database Documentation

## Overview
The AI Classroom Evaluation System uses a comprehensive relational database to manage user roles, teachers, evaluations, and criteria.

## Database Name
- **Database**: `ai_classroom_eval`
- **Character Set**: utf8mb4 (supports emojis and special characters)

## Complete Table Structure

### 1. USERS TABLE
Stores all system users (admin, evaluators, teachers, leaders)

**Fields:**
- `id` - Primary key
- `username` - Unique login username
- `password` - Bcrypt hashed password
- `name` - Full name
- `role` - User type (enum: edp, dean, principal, chairperson, subject_coordinator, president, vice_president, teacher)
- `department` - Department assignment (NULL for president/vice_president)
- `status` - active/inactive
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- Unique on `username`
- Index on `role`, `department`, `status`

**Sample Roles:**
- **EDP**: Educational Data Personnel - creates user accounts
- **Dean**: Evaluates teachers in their department
- **Principal**: Administrative leader
- **Chairperson**: Department head, can evaluate teachers
- **Subject Coordinator**: Coordinates subject-specific evaluations
- **President**: Institution head
- **Vice President**: Academic leadership
- **Teacher**: Faculty members being evaluated

---

### 2. TEACHERS TABLE
Links teachers to the users system and stores teacher-specific data

**Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users table (role='teacher')
- `name` - Teacher's name
- `department` - Department assignment
- `email` - Email address
- `phone` - Contact number
- `photo_path` - Path to teacher's photo
- `evaluation_schedule` - Scheduled evaluation date/time
- `evaluation_room` - Room/location for evaluation
- `status` - active/inactive
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- Foreign key on `user_id`
- Index on `department`, `status`

---

### 3. EVALUATIONS TABLE (Core Table)
Main table storing evaluation records and results

**Fields:**
- `id` - Primary key
- `teacher_id` - Foreign key to teachers (evaluated teacher)
- `evaluator_id` - Foreign key to users (person conducting evaluation)
- `academic_year` - e.g., "2023-2024"
- `semester` - "1st" or "2nd"
- `subject_observed` - Subject/course being evaluated
- `observation_time` - Time slot of observation
- `observation_date` - Date of observation
- `observation_type` - "Formal" or "Informal"
- `seat_plan` - Boolean: seat plan presented (0/1)
- `course_syllabi` - Boolean: course syllabi presented (0/1)
- `others_requirements` - Boolean: other requirements presented (0/1)
- `others_specify` - Text description of other requirements
- **Averages** (calculated and stored):
  - `communications_avg` - Decimal(3,2)
  - `management_avg` - Decimal(3,2)
  - `assessment_avg` - Decimal(3,2)
  - `overall_avg` - Decimal(3,2)
- **Qualitative Data:**
  - `strengths` - Teacher's strengths observed
  - `improvement_areas` - Areas for improvement
  - `recommendations` - Specific recommendations
  - `agreement` - Additional notes
- **Signatures:**
  - `rater_signature` - Evaluator's name
  - `rater_date` - Date signed by evaluator
  - `faculty_signature` - Teacher's name
  - `faculty_date` - Date acknowledged by teacher
- `status` - "draft" (in progress) or "completed" (submitted)
- `created_at`, `updated_at` - Timestamps

**Indexes:**
- Foreign keys on `teacher_id`, `evaluator_id`
- Index on `status`, `created_at`

**Query to find all completed evaluations for a teacher:**
```sql
SELECT e.*, u.name as evaluator_name, u.role as evaluator_role
FROM evaluations e
JOIN users u ON e.evaluator_id = u.id
WHERE e.teacher_id = ? AND e.status = 'completed'
ORDER BY e.created_at DESC;
```

---

### 4. EVALUATION_DETAILS TABLE
Stores individual ratings for each criterion

**Fields:**
- `id` - Primary key
- `evaluation_id` - Foreign key to evaluations
- `category` - "communications", "management", or "assessment"
- `criterion_index` - Index of the criterion (0-5 depending on category)
- `criterion_text` - Text description of the criterion
- `rating` - Numeric rating (1-5)
- `comments` - Evaluator's comments on this specific criterion
- `created_at` - Timestamp

**Indexes:**
- Foreign key on `evaluation_id`
- Index on `category`

**Rating Scale:**
- 5: Excellent (Greatly exceeds standards)
- 4: Very Satisfactory (More than meets standards)
- 3: Satisfactory (Meets standards)
- 2: Below Satisfactory (Falls below standards)
- 1: Needs Improvement (Barely meets expectations)

**Categories and Items:**
- **Communications** (5 items)
  - 0: Uses an audible voice
  - 1: Speaks fluently
  - 2: Facilitates a dynamic discussion
  - 3: Uses engaging non-verbal cues
  - 4: Uses appropriate language level

- **Management** (12 items)
  - 0-11: Various teaching/lesson management indicators

- **Assessment** (6 items)
  - 0-5: Student learning assessment indicators

---

### 5. EVALUATION_CRITERIA TABLE
Predefined criteria used in evaluations

**Fields:**
- `id` - Primary key
- `category` - "communications", "management", or "assessment"
- `criterion_index` - Index (0-5)
- `criterion_text` - Full criterion description
- `description` - Additional context
- `created_at` - Timestamp

**Purpose:**
- Reference table for all evaluation criteria
- Ensures consistency across all evaluations

---

### 6. AI_RECOMMENDATIONS TABLE
Stores AI-generated recommendations for evaluations

**Fields:**
- `id` - Primary key
- `evaluation_id` - Foreign key to evaluations
- `recommendation_text` - AI-generated recommendations
- `generated_at` - Timestamp

---

### 7. AUDIT_LOGS TABLE
Tracks user actions for security and audit purposes

**Fields:**
- `id` - Primary key
- `user_id` - Foreign key to users
- `action` - Action type (e.g., "LOGIN", "EVALUATION_SUBMITTED")
- `description` - Action description
- `ip_address` - User's IP address
- `created_at` - Timestamp

**Indexes:**
- Foreign key on `user_id`
- Index on `action`, `created_at`

---

## Database Setup Instructions

### Step 1: Create Database
```sql
CREATE DATABASE `ai_classroom_eval` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 2: Import Schema and Sample Data
1. Open phpMyAdmin or your MySQL client
2. Select the `ai_classroom_eval` database
3. Import the file: `database_complete.sql`

### Step 3: Update Password Hashes
Run the database setup helper to generate bcrypt hashes:
```bash
php database_setup_helper.php
```

Then update the users table with the actual password hashes.

### Step 4: Verify Connection
Update `config/database.php` if needed:
```php
private $host = "localhost";
private $db_name = "ai_classroom_eval";
private $username = "root";
private $password = "";
```

---

## Sample Data Included

### Users (10 users):
1. **edp_user** - EDP Admin (creates accounts)
2. **dean_ccs** - Dean of Computer Science
3. **principal** - School Principal
4. **chairperson_ccs** - CS Department Chairperson
5. **coordinator_ccs** - CS Subject Coordinator
6. **president** - Institution President
7. **vp_academics** - VP for Academics
8. **teacher_john** - John Smith (CS Teacher)
9. **teacher_mary** - Mary Johnson (CS Teacher)
10. **teacher_robert** - Robert Brown (Engineering Teacher)

### Teachers (3 teachers):
- John Smith (CS, user_id=8)
- Mary Johnson (CS, user_id=9)
- Robert Brown (Engineering, user_id=10)

### Evaluations (2 completed):
- John Smith evaluated by Chairperson (overall_avg: 4.2)
- Mary Johnson evaluated by Coordinator (overall_avg: 4.5)

### Evaluation Criteria (23 total):
- Communications: 5 criteria
- Management: 12 criteria
- Assessment: 6 criteria

---

## Key Relationships

```
users (1) ──── (many) teachers
  │
  └──── (many) evaluations (as evaluator_id)
         │
         └──── (1) teachers (as teacher_id)
         │
         └──── (many) evaluation_details
         │
         └──── (many) ai_recommendations
         │
         └──── (many) audit_logs
```

---

## Important Queries

### Get All Evaluations for a Teacher
```sql
SELECT e.*, u.name as evaluator_name, u.role
FROM evaluations e
JOIN users u ON e.evaluator_id = u.id
WHERE e.teacher_id = ? AND e.status = 'completed'
ORDER BY e.created_at DESC;
```

### Get Average Ratings by Department
```sql
SELECT t.department, COUNT(e.id) as total_evals, 
       AVG(e.overall_avg) as avg_rating
FROM evaluations e
JOIN teachers t ON e.teacher_id = t.id
WHERE e.status = 'completed'
GROUP BY t.department;
```

### Get Recent Evaluations (Last 30 days)
```sql
SELECT e.*, t.name as teacher_name, u.name as evaluator_name
FROM evaluations e
JOIN teachers t ON e.teacher_id = t.id
JOIN users u ON e.evaluator_id = u.id
WHERE e.status = 'completed'
  AND e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY e.created_at DESC;
```

### Find Draft Evaluations (Not Submitted)
```sql
SELECT e.*, t.name as teacher_name, u.name as evaluator_name
FROM evaluations e
JOIN teachers t ON e.teacher_id = t.id
JOIN users u ON e.evaluator_id = u.id
WHERE e.status = 'draft'
ORDER BY e.created_at DESC;
```

---

## Default Test Credentials

| Role | Username | Password | Department |
|------|----------|----------|-----------|
| EDP | edp_user | edp123 | Admin |
| Dean | dean_ccs | dean123 | CS |
| Teacher | teacher_john | teacher123 | CS |
| Chairperson | chairperson_ccs | chair123 | CS |

---

## Important Notes

1. **Teacher-User Relationship**: Every teacher must have a corresponding user record with role='teacher'
2. **Evaluation Status**: Must be either 'draft' or 'completed'
3. **Average Calculation**: Performed by `Evaluation::calculateAverages()` model method
4. **Password Security**: All passwords must be bcrypt hashed using PHP's `password_hash()` function
5. **Character Encoding**: UTF8MB4 ensures support for special characters and emojis
6. **Timestamps**: Automatically managed by CURRENT_TIMESTAMP and ON UPDATE CURRENT_TIMESTAMP

---

## Troubleshooting

### Teacher Can't See Evaluations
- Verify `evaluations.teacher_id` matches the teacher's `teachers.id`
- Check that evaluation `status = 'completed'`
- Verify `teachers.user_id` matches the logged-in user

### Password Not Working
- Ensure passwords are hashed with bcrypt in PHP: `password_hash($password, PASSWORD_BCRYPT)`
- Never store plain text passwords

### Missing Averages
- Run `Evaluation::calculateAverages($evaluation_id)` after creating evaluation details
- Check that `evaluation_criteria` table has all expected records

---

## For More Information
See the application code in:
- `/models/Evaluation.php` - Evaluation model and queries
- `/models/Teacher.php` - Teacher model
- `/models/User.php` - User authentication
- `/controllers/EvaluationController.php` - Evaluation business logic
