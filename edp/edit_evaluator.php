<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'edp') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';
require_once '../models/User.php';
$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if (!isset($_GET['id'])) {
    header('Location: evaluator_manage.php');
    exit();
}
$id = $_GET['id'];
$evaluator = $user->getById($id);

// Get evaluator's subjects or grade levels
$current_subjects = [];
$current_grade_levels = [];

if (in_array($evaluator['role'], ['subject_coordinator', 'chairperson'])) {
    $subjects_query = "SELECT subject FROM evaluator_subjects WHERE evaluator_id = :evaluator_id";
    $subjects_stmt = $db->prepare($subjects_query);
    $subjects_stmt->bindParam(':evaluator_id', $id);
    $subjects_stmt->execute();
    $current_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} elseif ($evaluator['role'] === 'grade_level_coordinator') {
    $grades_query = "SELECT grade_level FROM evaluator_grade_levels WHERE evaluator_id = :evaluator_id";
    $grades_stmt = $db->prepare($grades_query);
    $grades_stmt->bindParam(':evaluator_id', $id);
    $grades_stmt->execute();
    $current_grade_levels = $grades_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'username' => $_POST['username'],
        'role' => $_POST['role'],
        'department' => $_POST['department'],
        'password' => $_POST['password'] ?? ''
    ];
    
    // Update user
    $user->update($id, $data);
    
    // Update subjects for subject coordinators and chairpersons
    if (in_array($_POST['role'], ['subject_coordinator', 'chairperson']) && isset($_POST['subjects'])) {
        // Delete existing subjects
        $delete_query = "DELETE FROM evaluator_subjects WHERE evaluator_id = :evaluator_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':evaluator_id', $id);
        $delete_stmt->execute();
        
        // Insert new subjects
        foreach ($_POST['subjects'] as $subject) {
            $insert_query = "INSERT INTO evaluator_subjects (evaluator_id, subject, created_at) 
                            VALUES (:evaluator_id, :subject, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':evaluator_id', $id);
            $insert_stmt->bindParam(':subject', $subject);
            $insert_stmt->execute();
        }
    }
    // Update grade levels for grade level coordinators
    elseif ($_POST['role'] === 'grade_level_coordinator' && isset($_POST['grade_levels'])) {
        // Delete existing grade levels
        $delete_query = "DELETE FROM evaluator_grade_levels WHERE evaluator_id = :evaluator_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':evaluator_id', $id);
        $delete_stmt->execute();
        
        // Insert new grade levels
        foreach ($_POST['grade_levels'] as $grade_level) {
            $insert_query = "INSERT INTO evaluator_grade_levels (evaluator_id, grade_level, created_at) 
                            VALUES (:evaluator_id, :grade_level, NOW())";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':evaluator_id', $id);
            $insert_stmt->bindParam(':grade_level', $grade_level);
            $insert_stmt->execute();
        }
    }
    
    header('Location: evaluator_manage.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Evaluator</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .subjects-container, .grade-levels-container {
            display: none;
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .subject-checkbox, .grade-checkbox {
            margin-right: 10px;
        }
        .subject-item, .grade-item {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <h3>Edit Evaluator</h3>
            <form method="POST" id="editEvaluatorForm">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($evaluator['name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($evaluator['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" id="roleSelect" required>
                        <option value="">Select Role</option>
                        <?php $roles = ['president' => 'President', 'vice_president' => 'Vice President', 'dean' => 'Dean', 'principal' => 'Principal', 'subject_coordinator' => 'Subject Coordinator', 'chairperson' => 'Chairperson', 'grade_level_coordinator' => 'Grade Level Coordinator'];
                        foreach($roles as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php if($evaluator['role'] == $key) echo 'selected'; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select class="form-select" name="department" id="departmentSelect">
                        <option value="">Select Department/Category</option>
                        <?php $departments = [
                            'CTE' => 'College of Teacher Education',
                            'CAS' => 'College of Arts and Sciences',
                            'CCJE' => 'College of Criminal Justice Education',
                            'CBM' => 'College of Business Management',
                            'CCIS' => 'College of Computing and Information Sciences',
                            'CTHM' => 'College of Tourism and Hospitality Management',
                            'BASIC ED' => 'BASIC ED (Nursery, Kindergarten, Elementary, Junior High School)',
                            'SHS' => 'Senior High School (SHS)'
                        ];
                        foreach($departments as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php if($evaluator['department'] == $key) echo 'selected'; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Subjects Selection (for Subject Coordinators and Chairpersons) -->
                <div class="mb-3" id="subjectsContainer" style="display: none;">
                    <label class="form-label">Subjects/Courses</label>
                    <div class="subjects-list" id="subjectsList">
                        <!-- Subjects will be populated dynamically -->
                    </div>
                </div>
                
                <!-- Grade Levels Selection (for Grade Level Coordinators) -->
                <div class="mb-3" id="gradeLevelsContainer" style="display: none;">
                    <label class="form-label">Grade Levels</label>
                    <div class="grade-levels-list" id="gradeLevelsList">
                        <!-- Grade levels will be populated dynamically -->
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password (leave blank to keep current)</label>
                    <input type="password" class="form-control" name="password">
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        // Subject data by department
        const departmentSubjects = {
            'CTE': ['Mathematics Education', 'Science Education', 'English Education', 'Filipino Education', 'Social Studies Education'],
            'BSED': ['Professional Education', 'Specialization Courses', 'Thesis Writing'],
            'CAS': ['English', 'Filipino', 'Mathematics', 'Science', 'Social Sciences', 'Physical Education'],
            'CCJE': ['Criminal Law', 'Criminology', 'Forensic Science', 'Law Enforcement Administration'],
            'CBM': ['Accounting', 'Business Management', 'Marketing', 'Finance', 'Entrepreneurship'],
            'CCIS': ['Computer Programming', 'Database Management', 'Web Development', 'Networking', 'Software Engineering'],
            'CTHM': ['Tourism Management', 'Hospitality Management', 'Culinary Arts', 'Event Management'],
            'ELEM': ['English', 'Mathematics', 'Science', 'Filipino', 'Araling Panlipunan', 'MAPEH'],
            'JHS': ['English', 'Mathematics', 'Science', 'Filipino', 'Araling Panlipunan', 'MAPEH', 'TLE'],
            'SHS': ['Core Subjects', 'Applied Track Subjects', 'Specialized Track Subjects']
        };

        // Grade levels
        const gradeLevels = ['7', '8', '9', '10', '11', '12'];

        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('roleSelect');
            const departmentSelect = document.getElementById('departmentSelect');
            const subjectsContainer = document.getElementById('subjectsContainer');
            const gradeLevelsContainer = document.getElementById('gradeLevelsContainer');
            const subjectsList = document.getElementById('subjectsList');
            const gradeLevelsList = document.getElementById('gradeLevelsList');
            const currentSubjects = <?php echo json_encode($current_subjects); ?>;
            const currentGradeLevels = <?php echo json_encode($current_grade_levels); ?>;

            function toggleSpecializations() {
                const role = roleSelect.value;
                const department = departmentSelect.value;
                
                // Hide both containers first
                subjectsContainer.style.display = 'none';
                gradeLevelsContainer.style.display = 'none';
                
                if (role === 'subject_coordinator' || role === 'chairperson') {
                    if (department) {
                        subjectsContainer.style.display = 'block';
                        populateSubjects(department);
                    }
                } else if (role === 'grade_level_coordinator') {
                    gradeLevelsContainer.style.display = 'block';
                    populateGradeLevels();
                }
            }

            function populateSubjects(department) {
                const subjects = departmentSubjects[department] || [];
                subjectsList.innerHTML = '';
                
                subjects.forEach(subject => {
                    const isChecked = currentSubjects.includes(subject);
                    const subjectDiv = document.createElement('div');
                    subjectDiv.className = 'form-check subject-item';
                    subjectDiv.innerHTML = `
                        <input class="form-check-input subject-checkbox" type="checkbox" name="subjects[]" value="${subject}" id="subject_${subject.replace(/\s+/g, '_')}" ${isChecked ? 'checked' : ''}>
                        <label class="form-check-label" for="subject_${subject.replace(/\s+/g, '_')}">
                            ${subject}
                        </label>
                    `;
                    subjectsList.appendChild(subjectDiv);
                });
            }

            function populateGradeLevels() {
                gradeLevelsList.innerHTML = '';
                
                gradeLevels.forEach(grade => {
                    const isChecked = currentGradeLevels.includes(grade);
                    const gradeDiv = document.createElement('div');
                    gradeDiv.className = 'form-check grade-item';
                    gradeDiv.innerHTML = `
                        <input class="form-check-input grade-checkbox" type="checkbox" name="grade_levels[]" value="${grade}" id="grade_${grade}" ${isChecked ? 'checked' : ''}>
                        <label class="form-check-label" for="grade_${grade}">
                            Grade ${grade}
                        </label>
                    `;
                    gradeLevelsList.appendChild(gradeDiv);
                });
            }

            roleSelect.addEventListener('change', toggleSpecializations);
            departmentSelect.addEventListener('change', toggleSpecializations);
            
            // Initialize on page load
            toggleSpecializations();
        });
    </script>
</body>
</html>