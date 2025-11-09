<?php
require_once '../auth/session-check.php';
if($_SESSION['role'] != 'superadmin') {
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

// Get superadmin statistics
$stats = $evaluation->getSuperAdminStats();
$recent_evaluations = $evaluation->getRecentEvaluationsSuperAdmin(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - AI Classroom Evaluation</title>
    <?php include '../includes/header.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Super Admin Dashboard</h3>
                <span>Welcome, <?php echo $_SESSION['name']; ?> (Super Admin)</span>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="dashboard-stat stat-1">
                        <i class="fas fa-clipboard-check"></i>
                        <div class="number"><?php echo $stats['total_evaluations']; ?></div>
                        <div>Total Evaluations</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-stat stat-2">
                        <i class="fas fa-chart-line"></i>
                        <div class="number"><?php echo $stats['avg_rating']; ?></div>
                        <div>Average Rating</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-stat stat-3">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <div class="number"><?php echo $stats['total_teachers']; ?></div>
                        <div>Active Teachers</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="dashboard-stat stat-4">
                        <i class="fas fa-robot"></i>
                        <div class="number"><?php echo $stats['ai_recommendations']; ?></div>
                        <div>AI Recommendations</div>
                    </div>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Department Performance Overview</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="departmentChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Statistics</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Departments:</strong>
                                <span class="float-end"><?php echo $stats['total_departments']; ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Recent Evaluations (30 days):</strong>
                                <span class="float-end"><?php echo $stats['recent_evaluations']; ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>System Users:</strong>
                                <span class="float-end"><?php echo $user->getTotalUsers(); ?></span>
                            </div>
                            <div>
                                <strong>Active Sessions:</strong>
                                <span class="float-end"><?php echo $user->getActiveSessions(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions & Recent Evaluations -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
                                <a href="reports.php" class="btn btn-outline-primary">
                                    <i class="fas fa-chart-bar me-2"></i>View System Reports
                                </a>
                                <a href="../admin/teachers.php" class="btn btn-outline-primary">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>View All Teachers
                                </a>
                                <a href="system-settings.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-cog me-2"></i>System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Evaluations</h5>
                        </div>
                        <div class="card-body">
                            <?php if($recent_evaluations->rowCount() > 0): ?>
                            <div class="list-group">
                                <?php while($eval = $recent_evaluations->fetch(PDO::FETCH_ASSOC)): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($eval['teacher_name']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($eval['department']); ?> | 
                                                <?php echo date('M j, Y', strtotime($eval['observation_date'])); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php 
                                                echo $eval['overall_avg'] >= 4.6 ? 'success' : 
                                                    ($eval['overall_avg'] >= 3.6 ? 'primary' : 
                                                    ($eval['overall_avg'] >= 2.9 ? 'info' : 'warning')); ?>">
                                                <?php echo number_format($eval['overall_avg'], 1); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center py-3">
                                <i class="fas fa-clipboard-list fa-2x mb-3"></i><br>
                                No evaluations in the system yet.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Performance Table -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Department Performance Details</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Evaluations</th>
                                    <th>Average Rating</th>
                                    <th>Teachers</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats['department_stats'] as $dept): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($dept['department']); ?></strong>
                                    </td>
                                    <td><?php echo $dept['eval_count']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $dept['avg_rating'] >= 4.0 ? 'success' : 
                                                ($dept['avg_rating'] >= 3.0 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($dept['avg_rating'], 1); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $teacher_count = $teacher->getCountByDepartment($dept['department']);
                                        echo $teacher_count;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $dept['eval_count'] > 0 ? 'success' : 'secondary'; ?>">
                                            <?php echo $dept['eval_count'] > 0 ? 'Active' : 'No Data'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>

    <script>
        // Department Performance Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $labels = [];
                    foreach($stats['department_stats'] as $dept) {
                        $labels[] = "'" . $dept['department'] . "'";
                    }
                    echo implode(', ', $labels);
                ?>],
                datasets: [{
                    label: 'Number of Evaluations',
                    data: [<?php 
                        $evalData = [];
                        foreach($stats['department_stats'] as $dept) {
                            $evalData[] = $dept['eval_count'];
                        }
                        echo implode(', ', $evalData);
                    ?>],
                    backgroundColor: 'rgba(52, 152, 219, 0.8)',
                    borderColor: 'rgb(52, 152, 219)',
                    borderWidth: 1
                }, {
                    label: 'Average Rating',
                    data: [<?php 
                        $ratingData = [];
                        foreach($stats['department_stats'] as $dept) {
                            $ratingData[] = $dept['avg_rating'];
                        }
                        echo implode(', ', $ratingData);
                    ?>],
                    type: 'line',
                    borderColor: 'rgb(231, 76, 60)',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Evaluations'
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Average Rating'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>