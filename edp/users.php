<?php
$departments = [
    'CTE' => '(CTE) College of Teacher Education',
    'CAS' => '(CAS) College of Arts and Sciences',
    'CCJE' => '(CCJE) College of Criminal Justice Education',
    'CBM' => '(CBM) College of Business Management',
    'CCIS' => '(CCIS) College of Computing and Information Sciences',
    'CTHM' => '(CTHM) College of Tourism and Hospitality Management',
    'ELEM' => '(ELEM) Elementary School)',
    'JHS' => '(JHS) Junior High School)',
    'SHS' => '(SHS) Senior High School'
];
$selected_department = isset($_GET['department']) ? $_GET['department'] : '';
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

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'create':
                $role = $_POST['role'];
                $department = '';
                
                // Only require department/category for these roles
                if (in_array($role, ['dean', 'principal', 'subject_coordinator', 'chairperson', 'grade_level_coordinator'])) {
                    $department = $_POST['department'] ?? '';
                }
                // Teachers may also have a department
                if ($role === 'teacher') {
                    $department = $_POST['department'] ?? '';
                }
                
                $departments = [
                    'CTE' => 'College of Teacher Education',
                    'CAS' => 'College of Arts and Sciences',
                    'CCJE' => 'College of Criminal Justice Education',
                    'CBM' => 'College of Business Management',
                    'CCIS' => 'College of Computing and Information Sciences',
                    'CTHM' => 'College of Tourism and Hospitality Management',
                    'BASIC_ED' => 'BASIC ED (Nursery, Kindergarten, Elementary, Junior High School)',
                    'SHS' => 'Senior High School (SHS)'
                ];
                // If BASIC ED is selected, always store as 'BASIC ED' in the database
                if ($department === 'BASIC_ED') {
                    $department = 'BASIC ED';
                }
                
                $data = [
                    'username' => $_POST['username'],
                    'password' => $_POST['password'],
                    'name' => $_POST['name'],
                    'role' => $role,
                    'department' => $department
                ];
                
                $createResult = $user->create($data);
                
                // If creating a subject coordinator, chairperson, or grade level coordinator, save their subjects/grade levels
                if (in_array($role, ['subject_coordinator', 'chairperson', 'grade_level_coordinator']) && $createResult === true) {
                    // Get the newly created user ID
                    $query = "SELECT id FROM users WHERE username = :username";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $_POST['username']);
                    $stmt->execute();
                    $new_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($role === 'grade_level_coordinator' && isset($_POST['grade_levels'])) {
                        // Save grade levels
                        foreach ($_POST['grade_levels'] as $grade_level) {
                            $grade_query = "INSERT INTO evaluator_grade_levels (evaluator_id, grade_level, created_at) 
                                           VALUES (:evaluator_id, :grade_level, NOW())";
                            $grade_stmt = $db->prepare($grade_query);
                            $grade_stmt->bindParam(':evaluator_id', $new_user['id']);
                            $grade_stmt->bindParam(':grade_level', $grade_level);
                            $grade_stmt->execute();
                        }
                    } elseif (in_array($role, ['subject_coordinator', 'chairperson']) && isset($_POST['subjects'])) {
                        // Save subjects
                        foreach ($_POST['subjects'] as $subject) {
                            $subject_query = "INSERT INTO evaluator_subjects (evaluator_id, subject, created_at) 
                                             VALUES (:evaluator_id, :subject, NOW())";
                            $subject_stmt = $db->prepare($subject_query);
                            $subject_stmt->bindParam(':evaluator_id', $new_user['id']);
                            $subject_stmt->bindParam(':subject', $subject);
                            $subject_stmt->execute();
                        }
                    }
                    
                    // Assign to Dean/Principal if specified
                    if (isset($_POST['supervisor_id']) && !empty($_POST['supervisor_id'])) {
                        $supervisor_query = "INSERT INTO evaluator_assignments (evaluator_id, supervisor_id, assigned_at) 
                                           VALUES (:evaluator_id, :supervisor_id, NOW())";
                        $supervisor_stmt = $db->prepare($supervisor_query);
                        $supervisor_stmt->bindParam(':evaluator_id', $new_user['id']);
                        $supervisor_stmt->bindParam(':supervisor_id', $_POST['supervisor_id']);
                        $supervisor_stmt->execute();
                    }
                }
                
                // If creating a teacher account, also create/update the teacher record
                if ($role === 'teacher' && $createResult === true) {
                    // Get the newly created user ID
                    $query = "SELECT id FROM users WHERE username = :username";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $_POST['username']);
                    $stmt->execute();
                    $new_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if teacher exists, if not create
                    $check_teacher_query = "SELECT id FROM teachers WHERE name = :name AND department = :department";
                    $check_teacher_stmt = $db->prepare($check_teacher_query);
                    $check_teacher_stmt->bindParam(':name', $_POST['name']);
                    $check_teacher_stmt->bindParam(':department', $department);
                    $check_teacher_stmt->execute();
                    
                    if ($check_teacher_stmt->rowCount() > 0) {
                        // Update existing teacher with user_id
                        $teacher_row = $check_teacher_stmt->fetch(PDO::FETCH_ASSOC);
                        $update_query = "UPDATE teachers SET user_id = :user_id WHERE id = :teacher_id";
                        $update_stmt = $db->prepare($update_query);
                        $update_stmt->bindParam(':user_id', $new_user['id']);
                        $update_stmt->bindParam(':teacher_id', $teacher_row['id']);
                        $update_stmt->execute();
                    } else {
                        // Create new teacher record
                        $insert_query = "INSERT INTO teachers (name, department, user_id, status) VALUES (:name, :department, :user_id, 'active')";
                        $insert_stmt = $db->prepare($insert_query);
                        $insert_stmt->bindParam(':name', $_POST['name']);
                        $insert_stmt->bindParam(':department', $department);
                        $insert_stmt->bindParam(':user_id', $new_user['id']);
                        $insert_stmt->execute();
                    }
                }
                
                if($createResult === true) {
                    $_SESSION['success'] = ucfirst(str_replace('_',' ',$role)) . " account created successfully.";
                } elseif($createResult === 'exists') {
                    $_SESSION['error'] = "Username already exists. Please choose a different username.";
                } else {
                    $_SESSION['error'] = "Failed to create " . str_replace('_',' ',$role) . " account.";
                }
                break;

            case 'deactivate':
                if($user->updateStatus($_POST['user_id'], 'inactive')) {
                    $_SESSION['success'] = "Account deactivated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to deactivate account.";
                }
                break;

            case 'activate':
                if($user->updateStatus($_POST['user_id'], 'active')) {
                    $_SESSION['success'] = "Account activated successfully.";
                } else {
                    $_SESSION['error'] = "Failed to activate account.";
                }
                break;
        }
        header("Location: users.php");
        exit();
    }
}

// Get list of evaluators (all roles except EDP)
$roles = ['president', 'vice_president', 'dean', 'principal', 'subject_coordinator', 'chairperson', 'grade_level_coordinator'];
$evaluators = [];
foreach ($roles as $role) {
    if ($selected_department) {
        $evaluators[$role] = $user->getUsersByRoleAndDepartment($role, $selected_department, 'active');
    } else {
        $evaluators[$role] = $user->getUsersByRole($role, 'active');
    }
}

// Get subjects for evaluators
function getEvaluatorSubjects($db, $evaluator_id) {
    $query = "SELECT subject FROM evaluator_subjects WHERE evaluator_id = :evaluator_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evaluator_id', $evaluator_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Get grade levels for evaluators
function getEvaluatorGradeLevels($db, $evaluator_id) {
    $query = "SELECT grade_level FROM evaluator_grade_levels WHERE evaluator_id = :evaluator_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evaluator_id', $evaluator_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Get supervisor for evaluators
function getEvaluatorSupervisor($db, $evaluator_id) {
    $query = "SELECT u.name, u.role FROM evaluator_assignments ea 
              JOIN users u ON ea.supervisor_id = u.id 
              WHERE ea.evaluator_id = :evaluator_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':evaluator_id', $evaluator_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get assigned coordinators for supervisors
function getAssignedCoordinators($db, $supervisor_id) {
    $query = "SELECT u.id, u.name, u.role, u.department FROM evaluator_assignments ea 
              JOIN users u ON ea.evaluator_id = u.id 
              WHERE ea.supervisor_id = :supervisor_id 
              ORDER BY u.role, u.name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':supervisor_id', $supervisor_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Deans - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .subjects-container, .grade-levels-container, .supervisor-container {
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
        .assign-btn {
            margin-left: 10px;
        }
        .grade-badge {
            background-color: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .supervisor-badge {
            background-color: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Create User Accounts</h3>
                <div>
                    <button class="btn btn-primary m-3" data-bs-toggle="modal" data-bs-target="#addLeadershipModal">
                        <i class="fas fa-plus me-2"></i>Add President/VP
                    </button>
                    <button class="btn btn-success m-3" data-bs-toggle="modal" data-bs-target="#addEvaluatorModal">
                        <i class="fas fa-plus me-2"></i>Add Evaluators
                    </button>
                    <button class="btn btn-warning m-3" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus me-2"></i>Add Teacher Account
                    </button>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="get" class="mb-3 d-flex align-items-center">
                <label class="me-2 mb-0">Department:</label>
                <select name="department" class="form-select w-auto me-2" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php if($selected_department == $key) echo 'selected'; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Leadership Section (President & Vice President) -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-crown me-2"></i>President & Vice President</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                $leadership_roles = ['president', 'vice_president'];
                                foreach ($leadership_roles as $role) {
                                    while($row = $evaluators[$role]->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['role'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_evaluator.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $row['status'] == 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Supervisors Section (Deans & Principals) -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Supervisors (Deans & Principals)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Assigned Coordinators</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                $supervisor_roles = ['dean', 'principal'];
                                foreach ($supervisor_roles as $role) {
                                    while($row = $evaluators[$role]->fetch(PDO::FETCH_ASSOC)):
                                        $assigned_coordinators = getAssignedCoordinators($db, $row['id']);
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['role'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <?php if (!empty($assigned_coordinators)): ?>
                                            <?php foreach($assigned_coordinators as $coordinator): ?>
                                                <span class="supervisor-badge"><?php echo htmlspecialchars($coordinator['name']); ?> (<?php echo ucfirst(str_replace('_', ' ', $coordinator['role'])); ?>)</span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No coordinators assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_evaluator.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <a href="assign_coordinators.php?supervisor_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary assign-btn">Assign Coordinators</a>
                                        <a href="assign_teachers.php?evaluator_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success assign-btn">Assign Teachers</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $row['status'] == 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Coordinators Section -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Coordinators</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Subjects/Grade Levels</th>
                                    <th>Supervisor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                $coordinator_roles = ['subject_coordinator', 'chairperson', 'grade_level_coordinator'];
                                foreach ($coordinator_roles as $role) {
                                    while($row = $evaluators[$role]->fetch(PDO::FETCH_ASSOC)):
                                        if (in_array($row['role'], ['subject_coordinator', 'chairperson'])) {
                                            $items = getEvaluatorSubjects($db, $row['id']);
                                            $items_type = 'subjects';
                                        } elseif ($row['role'] === 'grade_level_coordinator') {
                                            $items = getEvaluatorGradeLevels($db, $row['id']);
                                            $items_type = 'grade_levels';
                                        } else {
                                            $items = [];
                                            $items_type = '';
                                        }
                                        $supervisor = getEvaluatorSupervisor($db, $row['id']);
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['role'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <?php if (!empty($items)): ?>
                                            <?php if ($items_type === 'subjects'): ?>
                                                <?php echo implode(', ', $items); ?>
                                            <?php elseif ($items_type === 'grade_levels'): ?>
                                                <?php foreach($items as $grade): ?>
                                                    <span class="grade-badge">Grade <?php echo $grade; ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No <?php echo $items_type ?: 'items'; ?> assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($supervisor): ?>
                                            <span class="supervisor-badge"><?php echo htmlspecialchars($supervisor['name']); ?> (<?php echo ucfirst(str_replace('_', ' ', $supervisor['role'])); ?>)</span>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_evaluator.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                                        <a href="assign_teachers.php?evaluator_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary assign-btn">Assign Teachers</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $row['status'] == 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Teachers Section -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Teacher Accounts</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get teachers with user accounts
                    $teacher_query = "SELECT t.*, u.username, u.status FROM teachers t 
                                    LEFT JOIN users u ON t.user_id = u.id 
                                    WHERE u.role = 'teacher' OR (u.role = 'teacher' AND u.id IS NOT NULL)
                                    ORDER BY t.name ASC";
                    $teacher_result = $db->query($teacher_query);
                    ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                while($row = $teacher_result->fetch(PDO::FETCH_ASSOC)):
                                    if(!empty($row['username'])):
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                            <input type="hidden" name="action" value="<?php echo $row['status'] == 'active' ? 'deactivate' : 'activate'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['status'] == 'active' ? 'warning' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $row['status'] == 'active' ? 'user-slash' : 'user-check'; ?>"></i>
                                                <?php echo $row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Leadership Modal -->
    <div class="modal fade" id="addLeadershipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Leadership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="president">President</option>
                                <option value="vice_president">Vice President</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Evaluator Modal -->
    <div class="modal fade" id="addEvaluatorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Evaluator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="evaluatorForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role" id="roleSelect" required>
                                        <option value="">Select Role</option>
                                        <option value="dean">Dean</option>
                                        <option value="principal">Principal</option>
                                        <option value="subject_coordinator">Subject Coordinator</option>
                                        <option value="chairperson">Chairperson</option>
                                        <option value="grade_level_coordinator">Grade Level Coordinator</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <select class="form-select" name="department" id="departmentSelect" required>
                                        <option value="">Select Department</option>
                                        <?php
                                        $departments = [
                                            'CTE' => 'College of Teacher Education',
                                            'BSED' => 'Bachelor of Secondary Education',
                                            'CAS' => 'College of Arts and Sciences',
                                            'CCJE' => 'College of Criminal Justice Education',
                                            'CBM' => 'College of Business Management',
                                            'CCIS' => 'College of Computing and Information Sciences',
                                            'CTHM' => 'College of Tourism and Hospitality Management',
                                            'ELEM' => 'Elementary',
                                            'JHS' => 'Junior High School',
                                            'SHS' => 'Senior High School (SHS)'
                                        ];
                                        foreach($departments as $key => $value):
                                        ?>
                                        <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Supervisor Selection (for Coordinators) -->
                                <div class="mb-3" id="supervisorContainer" style="display: none;">
                                    <label class="form-label">Assign to Supervisor</label>
                                    <select class="form-select" name="supervisor_id" id="supervisorSelect">
                                        <option value="">Select Supervisor (Optional)</option>
                                        <?php
                                        // Get all deans and principals
                                        $supervisors_query = "SELECT id, name, role, department FROM users WHERE role IN ('dean', 'principal') AND status = 'active' ORDER BY role, name";
                                        $supervisors_result = $db->query($supervisors_query);
                                        while($supervisor = $supervisors_result->fetch(PDO::FETCH_ASSOC)):
                                        ?>
                                        <option value="<?php echo $supervisor['id']; ?>">
                                            <?php echo htmlspecialchars($supervisor['name']); ?> (<?php echo ucfirst(str_replace('_', ' ', $supervisor['role'])); ?> - <?php echo htmlspecialchars($supervisor['department']); ?>)
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subjects Selection (for Subject Coordinators and Chairpersons) -->
                        <div class="mb-3" id="subjectsContainer" style="display: none;">
                            <label class="form-label">Subjects/Courses</label>
                            <div class="subjects-list" id="subjectsList">
                                <!-- Subjects will be populated dynamically based on department -->
                            </div>
                        </div>
                        
                        <!-- Grade Levels Selection (for Grade Level Coordinators) -->
                        <div class="mb-3" id="gradeLevelsContainer" style="display: none;">
                            <label class="form-label">Grade Levels</label>
                            <div class="grade-levels-list" id="gradeLevelsList">
                                <!-- Grade levels will be populated dynamically -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Teacher Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="role" value="teacher">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required placeholder="Enter teacher's full name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required placeholder="Enter username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required placeholder="Enter password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department" required>
                                <option value="">Select Department</option>
                                <?php
                                $dept_list = [
                                    'CTE' => '(CTE) College of Teacher Education',
                                    'CAS' => '(CAS) College of Arts and Sciences',
                                    'CCJE' => '(CCJE) College of Criminal Justice Education',
                                    'CBM' => '(CBM) College of Business Management',
                                    'CCIS' => '(CCIS) College of Computing and Information Sciences',
                                    'CTHM' => '(CTHM) College of Tourism and Hospitality Management',
                                    'ELEM' => '(ELEM) Elementary School',
                                    'JHS' => '(JHS) Junior High School',
                                    'SHS' => '(SHS) Senior High School'
                                ];
                                foreach($dept_list as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Teacher Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
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
            const supervisorContainer = document.getElementById('supervisorContainer');
            const subjectsContainer = document.getElementById('subjectsContainer');
            const gradeLevelsContainer = document.getElementById('gradeLevelsContainer');
            const subjectsList = document.getElementById('subjectsList');
            const gradeLevelsList = document.getElementById('gradeLevelsList');

            function toggleSpecializations() {
                const role = roleSelect.value;
                const department = departmentSelect.value;
                
                // Hide all containers first
                supervisorContainer.style.display = 'none';
                subjectsContainer.style.display = 'none';
                gradeLevelsContainer.style.display = 'none';
                
                // Show supervisor selection for coordinators
                if (role === 'subject_coordinator' || role === 'chairperson' || role === 'grade_level_coordinator') {
                    supervisorContainer.style.display = 'block';
                }
                
                // Show subject/grade level selection
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
                    const subjectDiv = document.createElement('div');
                    subjectDiv.className = 'form-check subject-item';
                    subjectDiv.innerHTML = `
                        <input class="form-check-input subject-checkbox" type="checkbox" name="subjects[]" value="${subject}" id="subject_${subject.replace(/\s+/g, '_')}">
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
                    const gradeDiv = document.createElement('div');
                    gradeDiv.className = 'form-check grade-item';
                    gradeDiv.innerHTML = `
                        <input class="form-check-input grade-checkbox" type="checkbox" name="grade_levels[]" value="${grade}" id="grade_${grade}">
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