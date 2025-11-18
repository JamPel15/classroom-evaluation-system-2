<?php
require_once '../auth/session-check.php';
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator'])) {
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

// Get teachers for current department
$teachers = $teacher->getByDepartment($_SESSION['department']);

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
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Teachers - <?php echo $_SESSION['department']; ?></h3>
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

            <div class="teacher-cards-container">
                <?php if($teachers->rowCount() > 0): ?>
                    <?php $counter = 1; ?>
                    <?php while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
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