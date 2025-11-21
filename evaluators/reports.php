<?php
require_once '../auth/session-check.php';
if(!in_array($_SESSION['role'], ['dean', 'principal', 'chairperson', 'subject_coordinator'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../models/Evaluation.php';
require_once '../models/Teacher.php';

$database = new Database();
$db = $database->getConnection();

$evaluation = new Evaluation($db);
$teacher = new Teacher($db);

// Get filter parameters
$academic_year = $_GET['academic_year'] ?? '2023-2024';
$semester = $_GET['semester'] ?? '';
$teacher_id = $_GET['teacher_id'] ?? '';

// Get evaluations for reporting
$evaluations = $evaluation->getEvaluationsForReport($_SESSION['user_id'], $academic_year, $semester, $teacher_id);
$teachers = $teacher->getByDepartment($_SESSION['department']);

// Calculate statistics
$stats = $evaluation->getDepartmentStats($_SESSION['department'], $academic_year, $semester);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo $_SESSION['department']; ?></title>
    <?php include '../includes/header.php'; ?>
    <style>
        .classroom-report {
            background: white;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .report-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .report-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-subtitle {
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .report-info {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        .report-table th {
            background: #34495e;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #ddd;
        }
        .report-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .report-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .rating-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .rating-excellent { background: #28a745; color: white; }
        .rating-very-satisfactory { background: #17a2b8; color: white; }
        .rating-satisfactory { background: #ffc107; color: black; }
        .rating-below-satisfactory { background: #fd7e14; color: white; }
        .rating-needs-improvement { background: #dc3545; color: white; }
        
        .observation-notes {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .observation-notes ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        .observation-notes li {
            margin-bottom: 3px;
        }
        
        .print-only {
            display: none;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .classroom-report {
                border: none;
                box-shadow: none;
            }
            .report-header {
                background: #2c3e50 !important;
                print-color-adjust: exact;
            }
            .report-table th {
                background: #34495e !important;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Evaluation Reports - <?php echo $_SESSION['department']; ?></h3>
                <div class="no-print">
                    <button class="btn btn-success me-2" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Export PDF
                    </button>
                    <button class="btn btn-primary me-2" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button class="btn btn-info" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4 no-print">
                <div class="card-header">
                    <h5 class="mb-0">Report Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="academic_year" class="form-label">Academic Year</label>
                            <select class="form-select" id="academic_year" name="academic_year">
                                <option value="2023-2024" <?php echo $academic_year == '2023-2024' ? 'selected' : ''; ?>>2023-2024</option>
                                <option value="2022-2023" <?php echo $academic_year == '2022-2023' ? 'selected' : ''; ?>>2022-2023</option>
                                <option value="2021-2022" <?php echo $academic_year == '2021-2022' ? 'selected' : ''; ?>>2021-2022</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="semester" class="form-label">Semester</label>
                            <select class="form-select" id="semester" name="semester">
                                <option value="">All Semesters</option>
                                <option value="1st" <?php echo $semester == '1st' ? 'selected' : ''; ?>>1st Semester</option>
                                <option value="2nd" <?php echo $semester == '2nd' ? 'selected' : ''; ?>>2nd Semester</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="teacher_id" class="form-label">Teacher</label>
                            <select class="form-select" id="teacher_id" name="teacher_id">
                                <option value="">All Teachers</option>
                                <?php while($teacher_row = $teachers->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $teacher_row['id']; ?>" 
                                    <?php echo $teacher_id == $teacher_row['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher_row['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Classroom Observation Report -->
            <div class="classroom-report">
                <!-- Report Header -->
                <div class="report-header">
                    <div class="report-title">SAINT MICHAEL COLLEGE OF CARAGA</div>
                    <div class="report-subtitle">Butuan City, Caraga Region</div>
                    <div class="report-subtitle">Tel. Nos. (085) 343-2237 / (085) 285-3113</div>
                    <div class="report-subtitle">www.smcccaraga.edu.ph</div>
                </div>
                
                <!-- Report Info -->
                <div class="report-info">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>CLASSROOM OBSERVATION REPORT</strong><br>
                            <strong>College/Department:</strong> <?php echo $_SESSION['department']; ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Academic Year:</strong> <?php echo htmlspecialchars($academic_year); ?><br>
                            <strong>Semester:</strong> <?php echo $semester ? htmlspecialchars($semester) : 'All'; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Report Table -->
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th width="12%">Date</th>
                                <th width="20%">Name of Teacher Observed</th>
                                <th width="18%">Subject/Class Schedule</th>
                                <th width="40%">Remarks/Observations</th>
                                <th width="10%">Ratings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($evaluations->rowCount() > 0): ?>
                                <?php while($eval = $evaluations->fetch(PDO::FETCH_ASSOC)): ?>
                                <?php
                                // Get rating text and class
                                $rating_text = 'Needs Improvement';
                                $rating_class = 'rating-needs-improvement';
                                
                                if($eval['overall_avg'] >= 4.6) {
                                    $rating_text = 'Excellent';
                                    $rating_class = 'rating-excellent';
                                } elseif($eval['overall_avg'] >= 3.6) {
                                    $rating_text = 'Very Satisfactory';
                                    $rating_class = 'rating-very-satisfactory';
                                } elseif($eval['overall_avg'] >= 2.9) {
                                    $rating_text = 'Satisfactory';
                                    $rating_class = 'rating-satisfactory';
                                } elseif($eval['overall_avg'] >= 1.8) {
                                    $rating_text = 'Below Satisfactory';
                                    $rating_class = 'rating-below-satisfactory';
                                }
                                
                                // Get evaluation details for observations
                                $evaluation_details = $evaluation->getEvaluationDetails($eval['id']);
                                $observations = [];
                                
                                while($detail = $evaluation_details->fetch(PDO::FETCH_ASSOC)) {
                                    if (!empty($detail['comments'])) {
                                        $observations[] = htmlspecialchars($detail['comments']);
                                    }
                                }
                                
                                // Get strengths and areas for improvement
                                if (!empty($eval['strengths'])) {
                                    $observations[] = "<strong>Strengths:</strong> " . htmlspecialchars($eval['strengths']);
                                }
                                if (!empty($eval['improvement_areas'])) {
                                    $observations[] = "<strong>Areas for Improvement:</strong> " . htmlspecialchars($eval['improvement_areas']);
                                }
                                ?>
                                <tr>
                                    <td><?php echo date('F j, Y', strtotime($eval['observation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($eval['teacher_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($eval['subject_observed']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($eval['observation_type']); ?> Observation</small>
                                    </td>
                                    <td>
                                        <div class="observation-notes">
                                            <?php if(!empty($observations)): ?>
                                                <ul>
                                                    <?php foreach($observations as $observation): ?>
                                                        <li><?php echo $observation; ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <em>No specific observations recorded.</em>
                                            <?php endif; ?>
                                            
                                            <?php if(!empty($eval['recommendations'])): ?>
                                                <div class="mt-2">
                                                    <strong>Recommendations:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($eval['recommendations'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="rating-badge <?php echo $rating_class; ?>">
                                            <?php echo $rating_text; ?>
                                        </span>
                                        <div class="text-center mt-1">
                                            <small><?php echo number_format($eval['overall_avg'], 1); ?></small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-clipboard-list fa-2x text-muted mb-3"></i>
                                        <h5>No Evaluation Data</h5>
                                        <p class="text-muted">No classroom observations found for the selected filters.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Report Footer -->
                <div class="report-info">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Generated on:</strong> <?php echo date('F j, Y'); ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Total Evaluations:</strong> <?php echo $stats['total_evaluations']; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics (Hidden in Print) -->
            <div class="card mt-4 no-print">
                <div class="card-header">
                    <h5 class="mb-0">Summary Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3 class="text-primary"><?php echo $stats['total_evaluations']; ?></h3>
                                <p class="text-muted">Total Evaluations</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3 class="text-info"><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                                <p class="text-muted">Average Rating</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3 class="text-success"><?php echo $stats['teachers_evaluated']; ?></h3>
                                <p class="text-muted">Teachers Evaluated</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3 class="text-warning"><?php echo $stats['ai_recommendations']; ?></h3>
                                <p class="text-muted">AI Recommendations</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Export functions
        function exportToPDF() {
            // Create a simplified version of the report for PDF export
            const reportContent = document.querySelector('.classroom-report').cloneNode(true);
            
            // Remove no-print elements
            const noPrintElements = reportContent.querySelectorAll('.no-print');
            noPrintElements.forEach(el => el.remove());
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Classroom Observation Report - <?php echo $_SESSION['department']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .classroom-report { border: 1px solid #ddd; }
                        .report-header { 
                            background: #2c3e50; 
                            color: white; 
                            padding: 20px; 
                            text-align: center; 
                        }
                        .report-title { font-size: 1.5rem; font-weight: bold; }
                        .report-info { background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd; }
                        .report-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                        .report-table th { background: #34495e; color: white; padding: 10px; text-align: left; }
                        .report-table td { padding: 8px; border: 1px solid #ddd; vertical-align: top; }
                        .rating-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; }
                        .rating-excellent { background: #28a745; color: white; }
                        .rating-very-satisfactory { background: #17a2b8; color: white; }
                        .rating-satisfactory { background: #ffc107; color: black; }
                        .rating-below-satisfactory { background: #fd7e14; color: white; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    ${reportContent.outerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            
            // Wait for content to load then print
            printWindow.onload = function() {
                printWindow.print();
            };
        }

        function exportToExcel() {
            alert('Excel export functionality would generate a spreadsheet version of this report.');
            // In a real implementation, this would call a PHP script to generate Excel
            // window.location.href = 'export_report.php?type=excel&academic_year=<?php echo $academic_year; ?>&semester=<?php echo $semester; ?>';
        }

        // Auto-print option for direct report generation
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'true') {
            window.print();
        }
    </script>
</body>
</html>