<?php
require_once '../auth/session-check.php';

// DEBUG: Show session role (development only). Uncomment the block below to enable.
if (isset($_SESSION['role'])) {
     echo '<h2>DEBUG: $_SESSION["role"] = ' . htmlspecialchars($_SESSION['role']) . '</h2>';
 } else {
     echo '<h2>DEBUG: $_SESSION["role"] is NOT SET</h2>';
 }
// Note: do not call exit here to avoid making the rest of the script unreachable.

// Fix: Convert role to lowercase for consistent comparison
$userRole = strtolower($_SESSION['role']);
$allowedRoles = ['president', 'vice_president', 'vice president']; // Add variations if needed

if(!in_array($userRole, $allowedRoles)) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();
$teacher = new Teacher($db);

// Only active teachers
$teachers = $teacher->getAllTeachers('active');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaders Dashboard - Manage Teachers</title>
    <?php include '../includes/header.php'; ?>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Leaders Dashboard</h3>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo ucfirst(str_replace('_',' ',$_SESSION['role'])); ?>)</span>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Active Teachers</h5>
                    <input type="text" id="teacherSearch" class="form-control w-auto" placeholder="Search teachers...">
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; while($row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td>
                                        <a href="../evaluators/evaluation.php?teacher_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Evaluate</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('teacherSearch').addEventListener('keyup', function() {
        let searchText = this.value.toLowerCase();
        let tableRows = document.querySelectorAll('tbody tr');

        tableRows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });
    </script>
</body>
</html>