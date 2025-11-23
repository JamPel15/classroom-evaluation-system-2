<?php
require_once '../auth/session-check.php';
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator', 'grade_level_coordinator'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);

// Handle teacher actions
$action = $_GET['action'] ?? '';
$success_message = '';
$error_message = '';

// Toggle teacher status (activate/deactivate)
if ($_GET && isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    $teacher_id = $_GET['teacher_id'] ?? '';
    if (!empty($teacher_id)) {
        if ($teacher->toggleStatus($teacher_id)) {
            $success_message = "Teacher status updated successfully!";
        } else {
            $error_message = "Failed to update teacher status.";
        }
    }
    // Redirect to avoid POST/GET issues
    header("Location: teachers.php");
    exit();
}

// Update evaluation schedule and room
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
    $teacher_id = $_POST['teacher_id'] ?? '';
    $schedule = $_POST['evaluation_schedule'] ?? '';
    $room = $_POST['evaluation_room'] ?? '';
    
    if (!empty($teacher_id)) {
        // Update using a query to add/update schedule and room info
        $query = "UPDATE teachers SET evaluation_schedule = :schedule, evaluation_room = :room, updated_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':schedule', $schedule);
        $stmt->bindParam(':room', $room);
        $stmt->bindParam(':id', $teacher_id);
        
        if ($stmt->execute()) {
            $success_message = "Evaluation schedule and room updated successfully!";
        } else {
            $error_message = "Failed to update schedule and room.";
        }
    } else {
        $error_message = "Teacher ID is required.";
    }
}

// Handle teacher assignment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'assign_teacher') {
    $teacher_id = $_POST['teacher_id'];
    $subject = $_POST['subject'] ?? '';
    $grade_level = $_POST['grade_level'] ?? '';
    
    // Check if assignment already exists
    $check_query = "SELECT id FROM teacher_assignments WHERE evaluator_id = :evaluator_id AND teacher_id = :teacher_id";
    if (!empty($subject)) {
        $check_query .= " AND subject = :subject";
    } elseif (!empty($grade_level)) {
        $check_query .= " AND grade_level = :grade_level";
    }
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
    $check_stmt->bindParam(':teacher_id', $teacher_id);
    if (!empty($subject)) {
        $check_stmt->bindParam(':subject', $subject);
    } elseif (!empty($grade_level)) {
        $check_stmt->bindParam(':grade_level', $grade_level);
    }
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        $insert_query = "INSERT INTO teacher_assignments (evaluator_id, teacher_id, subject, grade_level, assigned_at) 
                        VALUES (:evaluator_id, :teacher_id, :subject, :grade_level, NOW())";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
        $insert_stmt->bindParam(':teacher_id', $teacher_id);
        $insert_stmt->bindParam(':subject', $subject);
        $insert_stmt->bindParam(':grade_level', $grade_level);
        
        if ($insert_stmt->execute()) {
            $success_message = "Teacher assigned successfully!";
        } else {
            $error_message = "Failed to assign teacher.";
        }
    } else {
        $error_message = "Teacher is already assigned to you.";
    }
}

// Handle teacher removal
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'remove_assignment') {
    $assignment_id = $_POST['assignment_id'];
    
    $delete_query = "DELETE FROM teacher_assignments WHERE id = :assignment_id AND evaluator_id = :evaluator_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':assignment_id', $assignment_id);
    $delete_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
    
    if ($delete_stmt->execute()) {
        $success_message = "Teacher assignment removed successfully!";
    } else {
        $error_message = "Failed to remove teacher assignment.";
    }
}

// Get teachers for current department
$teachers = $teacher->getByDepartment($_SESSION['department']);

// Get assigned teachers for current evaluator
$assigned_query = "SELECT ta.*, t.name as teacher_name, t.department 
                  FROM teacher_assignments ta 
                  JOIN teachers t ON ta.teacher_id = t.id 
                  WHERE ta.evaluator_id = :evaluator_id 
                  ORDER BY ta.subject, ta.grade_level, t.name";
$assigned_stmt = $db->prepare($assigned_query);
$assigned_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
$assigned_stmt->execute();
$assigned_teachers = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get evaluator's subjects or grade levels
$evaluator_specializations = [];
if (in_array($_SESSION['role'], ['subject_coordinator', 'chairperson'])) {
    $subjects_query = "SELECT subject FROM evaluator_subjects WHERE evaluator_id = :evaluator_id";
    $subjects_stmt = $db->prepare($subjects_query);
    $subjects_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
    $subjects_stmt->execute();
    $evaluator_specializations = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
} elseif ($_SESSION['role'] === 'grade_level_coordinator') {
    $grades_query = "SELECT grade_level FROM evaluator_grade_levels WHERE evaluator_id = :evaluator_id";
    $grades_stmt = $db->prepare($grades_query);
    $grades_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
    $grades_stmt->execute();
    $evaluator_specializations = $grades_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Get a single teacher for editing (AJAX)
if (isset($_GET['get_teacher']) && isset($_GET['id'])) {
    $teacher_data = $teacher->getById($_GET['id']);
    header('Content-Type: application/json');
    if ($teacher_data) {
        echo json_encode(['success' => true, 'teacher' => $teacher_data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Teacher not found']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
    <style>
        .teacher-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .teacher-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .teacher-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .teacher-photo-section {
            position: relative;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .teacher-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .default-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid white;
        }
        
        .default-photo i {
            font-size: 2.5rem;
            color: white;
        }
        
        .teacher-info {
            padding: 20px;
            text-align: center;
        }
        
        .teacher-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .teacher-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
        }

        .teacher-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .teacher-actions .btn {
            flex: 1;
            min-width: 80px;
            font-size: 0.75rem;
            padding: 5px 10px;
        }

        .modal-body .form-group {
            margin-bottom: 15px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .modal-lg {
            max-width: 600px;
        }

        .schedule-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .assignment-badge {
            background-color: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-top: 5px;
            display: inline-block;
        }

        .assign-form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .assigned-teachers-section {
            margin-top: 30px;
        }

        .assignment-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .assignment-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .assignment-body {
            padding: 15px;
        }

        .teacher-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .teacher-item {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .teacher-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Teachers - <?php echo $_SESSION['department']; ?></h3>
                <div>
                    <span class="badge bg-primary">
                        <i class="fas fa-users me-1"></i>
                        Total: <?php echo $teachers->rowCount(); ?> | 
                        Assigned: <?php echo count($assigned_teachers); ?>
                    </span>
                </div>
            </div>

            <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Assign Teacher Form -->
            <?php if (!empty($evaluator_specializations)): ?>
            <div class="assign-form-container">
                <h5><i class="fas fa-user-plus me-2"></i>Assign Teacher to Evaluate</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_teacher">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Teacher</label>
                                <select class="form-select" name="teacher_id" required>
                                    <option value="">Select Teacher</option>
                                    <?php 
                                    $teachers->execute(); // Re-execute the query since we used it before
                                    while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): 
                                        // Check if teacher is already assigned
                                        $is_assigned = false;
                                        foreach($assigned_teachers as $assigned) {
                                            if ($assigned['teacher_id'] == $teacher_row['id']) {
                                                $is_assigned = true;
                                                break;
                                            }
                                        }
                                        if (!$is_assigned):
                                    ?>
                                        <option value="<?php echo $teacher_row['id']; ?>">
                                            <?php echo htmlspecialchars($teacher_row['name']); ?>
                                        </option>
                                    <?php endif; endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php if (in_array($_SESSION['role'], ['subject_coordinator', 'chairperson'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <select class="form-select" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach($evaluator_specializations as $subject): ?>
                                        <option value="<?php echo htmlspecialchars($subject); ?>"><?php echo htmlspecialchars($subject); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php elseif ($_SESSION['role'] === 'grade_level_coordinator'): ?>
                            <div class="mb-3">
                                <label class="form-label">Grade Level</label>
                                <select class="form-select" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <?php foreach($evaluator_specializations as $grade): ?>
                                        <option value="<?php echo htmlspecialchars($grade); ?>">Grade <?php echo htmlspecialchars($grade); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Assignment Type</label>
                                <select class="form-select" name="assignment_type" required>
                                    <option value="general">General Evaluation</option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i>Assign Teacher
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Assigned Teachers Section -->
            <?php if (!empty($assigned_teachers)): ?>
            <div class="assigned-teachers-section">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>My Assigned Teachers</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Group assignments by subject/grade level
                        $grouped_assignments = [];
                        foreach ($assigned_teachers as $assignment) {
                            $key = $assignment['subject'] ?: 'Grade ' . $assignment['grade_level'];
                            $grouped_assignments[$key][] = $assignment;
                        }
                        ?>
                        
                        <?php foreach($grouped_assignments as $category => $assignments): ?>
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-book me-2"></i><?php echo htmlspecialchars($category); ?>
                                    <span class="badge bg-secondary ms-2"><?php echo count($assignments); ?> teachers</span>
                                </h6>
                            </div>
                            <div class="assignment-body">
                                <ul class="teacher-list">
                                    <?php foreach($assignments as $assignment): ?>
                                    <li class="teacher-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars($assignment['teacher_name']); ?></strong>
                                            <small class="text-muted ms-2"><?php echo htmlspecialchars($assignment['department']); ?></small>
                                        </div>
                                        <div>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_assignment">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Remove <?php echo htmlspecialchars($assignment['teacher_name']); ?> from your assignments?')">
                                                    <i class="fas fa-times"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- All Teachers in Department -->
            <div class="mt-4">
                <h5><i class="fas fa-users me-2"></i>All Teachers in Department</h5>
                <div class="teacher-cards-container">
                    <?php 
                    $teachers->execute(); // Re-execute the query
                    if($teachers->rowCount() > 0): ?>
                        <?php while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): 
                            // Check if teacher is assigned to current evaluator
                            $is_assigned = false;
                            $assignment_info = '';
                            foreach($assigned_teachers as $assigned) {
                                if ($assigned['teacher_id'] == $teacher_row['id']) {
                                    $is_assigned = true;
                                    $assignment_info = $assigned['subject'] ?: 'Grade ' . $assigned['grade_level'];
                                    break;
                                }
                            }
                        ?>
                        <div class="teacher-card">
                            <div class="teacher-photo-section">
                                <?php if(!empty($teacher_row['photo'])): ?>
                                    <img src="../uploads/teachers/<?php echo htmlspecialchars($teacher_row['photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($teacher_row['name']); ?>" 
                                         class="teacher-photo"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="default-photo" style="display: none;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="default-photo">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="teacher-info">
                                <div class="teacher-name"><?php echo htmlspecialchars($teacher_row['name']); ?></div>
                                
                                <div class="status-badge badge bg-<?php echo $teacher_row['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($teacher_row['status']); ?>
                                </div>

                                <?php if($is_assigned): ?>
                                <div class="assignment-badge">
                                    <i class="fas fa-check me-1"></i>Assigned for <?php echo htmlspecialchars($assignment_info); ?>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($teacher_row['evaluation_schedule']) || !empty($teacher_row['evaluation_room'])): ?>
                                <div class="schedule-info">
                                    <?php if(!empty($teacher_row['evaluation_schedule'])): ?>
                                        <div><i class="fas fa-calendar me-2"></i><?php echo htmlspecialchars($teacher_row['evaluation_schedule']); ?></div>
                                    <?php endif; ?>
                                    <?php if(!empty($teacher_row['evaluation_room'])): ?>
                                        <div><i class="fas fa-door-open me-2"></i><?php echo htmlspecialchars($teacher_row['evaluation_room']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <div class="teacher-actions">
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#scheduleModal" onclick="editSchedule(<?php echo $teacher_row['id']; ?>, '<?php echo htmlspecialchars($teacher_row['evaluation_schedule'] ?? ''); ?>', '<?php echo htmlspecialchars($teacher_row['evaluation_room'] ?? ''); ?>')">
                                        <i class="fas fa-calendar"></i> Schedule
                                    </button>
                                    <a href="?action=toggle_status&teacher_id=<?php echo $teacher_row['id']; ?>" class="btn btn-sm btn-outline-<?php echo $teacher_row['status'] == 'active' ? 'warning' : 'success'; ?>" onclick="return confirm('Are you sure?');">
                                        <i class="fas fa-<?php echo $teacher_row['status'] == 'active' ? 'ban' : 'check'; ?>"></i> <?php echo $teacher_row['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5>No Teachers Found</h5>
                            <p class="text-muted">No teachers are currently assigned to this department.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- Schedule and Room Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Evaluation Schedule & Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_schedule">
                        <input type="hidden" name="teacher_id" id="schedule_teacher_id">
                        
                        <div class="form-group">
                            <label class="form-label">Evaluation Schedule <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="evaluation_schedule" name="evaluation_schedule" required placeholder="Select date and time">
                            <small class="form-text text-muted">Date and time of the classroom observation/evaluation.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Classroom/Room <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="evaluation_room" name="evaluation_room" required placeholder="e.g., Room 101, Laboratory B, Building A - Room 303">
                            <small class="form-text text-muted">Location where the evaluation will take place.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Schedule & Room</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editSchedule(teacherId, schedule, room) {
            document.getElementById('schedule_teacher_id').value = teacherId;
            document.getElementById('evaluation_schedule').value = schedule;
            document.getElementById('evaluation_room').value = room;
        }
    </script>
</body>
</html>