<?php
require_once '../auth/session-check.php';

// Redirect based on role
if(in_array($_SESSION['role'], ['president', 'vice_president'])) {
    header("Location: ../leaders/dashboard.php");
    exit();
} elseif($_SESSION['role'] == 'edp') {
    header("Location: ../edp/dashboard.php");
    exit();
} elseif(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator', 'grade_level_coordinator'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';
require_once '../models/Evaluation.php';
require_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();

$teacher = new Teacher($db);
$evaluation = new Evaluation($db);
$user = new User($db);

// Get department teachers
$department_teachers = $teacher->getByDepartment($_SESSION['department']);
$stats = $evaluation->getAdminStats($_SESSION['user_id']);
$recent_evals = $evaluation->getRecentEvaluations($_SESSION['user_id'], 5);

// Get assigned coordinators (for deans/principals)
$assigned_coordinators = [];
if(in_array($_SESSION['role'], ['dean', 'principal'])) {
    $coordinators_query = "
        SELECT u.id, u.name, u.role, u.department 
        FROM evaluator_assignments ea 
        JOIN users u ON ea.evaluator_id = u.id 
        WHERE ea.supervisor_id = :supervisor_id 
        AND u.status = 'active'
        ORDER BY u.role, u.name
    ";
    $coordinators_stmt = $db->prepare($coordinators_query);
    $coordinators_stmt->bindParam(':supervisor_id', $_SESSION['user_id']);
    $coordinators_stmt->execute();
    $assigned_coordinators = $coordinators_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get supervisor info (for coordinators)
$supervisor_info = [];
if(in_array($_SESSION['role'], ['subject_coordinator', 'chairperson', 'grade_level_coordinator'])) {
    $supervisor_query = "
        SELECT u.name, u.role, u.department 
        FROM evaluator_assignments ea 
        JOIN users u ON ea.supervisor_id = u.id 
        WHERE ea.evaluator_id = :evaluator_id
    ";
    $supervisor_stmt = $db->prepare($supervisor_query);
    $supervisor_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
    $supervisor_stmt->execute();
    $supervisor_info = $supervisor_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get assigned teachers count
$assigned_teachers_count = 0;
if(in_array($_SESSION['role'], ['subject_coordinator', 'chairperson', 'grade_level_coordinator'])) {
    $teachers_count_query = "
        SELECT COUNT(*) as teacher_count 
        FROM teacher_assignments 
        WHERE evaluator_id = :evaluator_id
    ";
    $teachers_count_stmt = $db->prepare($teachers_count_query);
    $teachers_count_stmt->bindParam(':evaluator_id', $_SESSION['user_id']);
    $teachers_count_stmt->execute();
    $assigned_teachers_count = $teachers_count_stmt->fetch(PDO::FETCH_ASSOC)['teacher_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
    <style>
        .hierarchy-card {
            border-left: 4px solid #007bff;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        .coordinator-card {
            border-left: 4px solid #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
        }
        .supervisor-card {
            border-left: 4px solid #6f42c1;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8ebf5 100%);
        }
        .stat-card {
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Dashboard - <?php echo $_SESSION['department']; ?></h3>
                <span>Welcome, <?php echo $_SESSION['name']; ?> (<?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?>)</span>
            </div>
            
            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Organizational Hierarchy Info -->
            <div class="row mb-4">
                <?php if(in_array($_SESSION['role'], ['dean', 'principal'])): ?>
                <?php elseif(in_array($_SESSION['role'], ['subject_coordinator', 'chairperson', 'grade_level_coordinator'])): ?>
                    <!-- Coordinator Dashboard -->
                    <div class="col-12">
                        <div class="card coordinator-card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>Coordinator Overview
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Supervisor Information</h6>
                                        <?php if($supervisor_info): ?>
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <p class="mb-1">
                                                        <strong>Name:</strong> <?php echo htmlspecialchars($supervisor_info['name']); ?>
                                                    </p>
                                                    <p class="mb-1">
                                                        <strong>Role:</strong> <?php echo ucfirst(str_replace('_', ' ', $supervisor_info['role'])); ?>
                                                    </p>
                                                    <p class="mb-0">
                                                        <strong>Department:</strong> <?php echo htmlspecialchars($supervisor_info['department']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted">Not assigned to a supervisor yet.</p>
                                            <a href="../edp/users.php" class="btn btn-sm btn-outline-secondary">
                                                Contact EDP for Assignment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>My Responsibilities</h6>
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <p class="mb-2">
                                                    <i class="fas fa-chalkboard-teacher me-2 text-success"></i>
                                                    <strong>Assigned Teachers:</strong> <?php echo $assigned_teachers_count; ?>
                                                </p>
                                                <p class="mb-2">
                                                    <i class="fas fa-clipboard-check me-2 text-primary"></i>
                                                    <strong>Completed Evaluations:</strong> <?php echo $stats['completed_evaluations']; ?>
                                                </p>
                                                <p class="mb-0">
                                                    <i class="fas fa-robot me-2 text-info"></i>
                                                    <strong>AI Recommendations:</strong> <?php echo $stats['ai_recommendations']; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-6">
                    <div class="dashboard-stat stat-1 stat-card">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div class="number"><?php echo $department_teachers->rowCount(); ?></div>
                        <div>Department Teachers</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-stat stat-2 stat-card">
                        <i class="fas fa-clipboard-check"></i>
                        <div class="number"><?php echo $stats['completed_evaluations']; ?></div>
                        <div>Completed Evaluations</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Evaluations & Quick Actions -->
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Evaluations</h5>
                        </div>
                        <div class="card-body">
                            <?php if($recent_evals->rowCount() > 0): ?>
                                <div class="list-group">
                                    <?php while($eval = $recent_evals->fetch(PDO::FETCH_ASSOC)): 
                                        $teacher_data = $teacher->getById($eval['teacher_id']);
                                    ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($teacher_data['name']); ?></h6>
                                                <small class="text-muted"><?php echo date('M j, Y', strtotime($eval['observation_date'])); ?></small>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-<?php 
                                                    $rating = $eval['overall_avg'];
                                                    if($rating >= 4.6) echo 'success';
                                                    elseif($rating >= 3.6) echo 'primary';
                                                    elseif($rating >= 2.9) echo 'info';
                                                    elseif($rating >= 1.8) echo 'warning';
                                                    else echo 'danger';
                                                ?>"><?php echo number_format($rating, 1); ?></span>
                                                <a href="evaluation_view.php?id=<?php echo $eval['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <h5>No Evaluations Yet</h5>
                                    <p class="text-muted">Start by conducting your first classroom evaluation.</p>
                                    <a href="evaluation.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Start Evaluation
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="evaluation.php" class="btn btn-primary mb-2">
                                    <i class="fas fa-clipboard-check me-2"></i>New Evaluation
                                </a>
                                <a href="teachers.php" class="btn btn-outline-primary mb-2">
                                    <i class="fas fa-users me-2"></i>Manage Teachers
                                </a>
                                <?php if(in_array($_SESSION['role'], ['dean', 'principal'])): ?>
                                    <a href="assign_coordinators.php?supervisor_id=<?php echo $_SESSION['user_id']; ?>" class="btn btn-outline-success mb-2">
                                        <i class="fas fa-user-plus me-2"></i>Assign Coordinators
                                    </a>
                                <?php endif; ?>
                                <a href="assign_teachers.php?evaluator_id=<?php echo $_SESSION['user_id']; ?>" class="btn btn-outline-info mb-2">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Assign Teachers
                                </a>
                                <a href="reports.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>