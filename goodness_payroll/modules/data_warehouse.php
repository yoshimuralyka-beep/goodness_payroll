<?php
require_once 'config/database.php';
require_once 'includes/log_activity.php';

$message = '';
$messageType = '';
$userId = $_SESSION['user_id'];

// ETL FUNCTION — delegates to the stored procedure sp_run_etl()
// The stored procedure handles:
//   - START TRANSACTION / COMMIT / ROLLBACK on error
//   - Clearing + reloading all 4 warehouse tables
//   - Logging to etl_jobs (including error_message on failure)
//   - dim_time now includes quarter_number and half_year
//   - fact_payroll now includes gross_pay, total_deductions, allowances, overtime
//   - dim_employee limited to active employees only (emp_status = 'active')
function run_etl($db) {
    try {
        // Check if there are processed payroll records before calling proc
        $check = $db->query("SELECT COUNT(*) FROM payroll_records WHERE payroll_status = 'processed'")->fetchColumn();
        if ($check == 0) {
            return "No processed payroll records found. Please process payroll first.";
        }

        // Call the stored procedure — it handles transaction + rollback internally
        $db->exec("CALL sp_run_etl()");

        // Check the latest etl_jobs row to confirm success or failure
        $job = $db->query("SELECT * FROM etl_jobs ORDER BY job_id DESC LIMIT 1")->fetch();
        if ($job && $job['job_status'] === 'failed') {
            return "Error: ETL procedure failed — " . ($job['error_message'] ?? 'Unknown error');
        }

        return "success";

    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// RUN ETL PROCESS
if (isset($_POST['run_etl'])) {
    $result = run_etl($db);
    if ($result === "success") {
        $factCount = $db->query("SELECT COUNT(*) FROM fact_payroll")->fetchColumn();
        logActivity($db, $userId, 'RUN_ETL', 'Data Warehouse', "ETL process completed - $factCount records processed");
        $message = "ETL process completed successfully! Data warehouse has been refreshed.";
        $messageType = "success";
        echo "<script>setTimeout(function() { window.location.href = '?page=data_warehouse'; }, 1500);</script>";
    } else {
        $message = $result;
        $messageType = "danger";
    }
}

// =============================================
// GET DIMENSION TABLE COUNTS
// =============================================
$dimEmployeeCount = 0;
$dimTimeCount = 0;
$dimDepartmentCount = 0;
$factCount = 0;

try {
    $result = $db->query("SELECT COUNT(*) as count FROM dim_employee");
    $dimEmployeeCount = $result->fetch()['count'];
} catch(Exception $e) { $dimEmployeeCount = 0; }

try {
    $result = $db->query("SELECT COUNT(*) as count FROM dim_time");
    $dimTimeCount = $result->fetch()['count'];
} catch(Exception $e) { $dimTimeCount = 0; }

try {
    $result = $db->query("SELECT COUNT(*) as count FROM dim_department");
    $dimDepartmentCount = $result->fetch()['count'];
} catch(Exception $e) { $dimDepartmentCount = 0; }

try {
    $result = $db->query("SELECT COUNT(*) as count FROM fact_payroll");
    $factCount = $result->fetch()['count'];
} catch(Exception $e) { $factCount = 0; }

// =============================================
// GET DATA FOR PREVIEW POPUPS
// =============================================
$dimEmployeeData = [];
$dimTimeData = [];
$dimDepartmentData = [];
$factDataPreview = [];

try {
    $dimEmployeeData = $db->query("SELECT employee_id, employee_code, full_name, department_name, position_title FROM dim_employee LIMIT 20")->fetchAll();
} catch(Exception $e) { $dimEmployeeData = []; }

try {
    $dimTimeData = $db->query("SELECT year_number, month_number, month_name_text, year_month_text FROM dim_time ORDER BY year_number DESC, month_number DESC LIMIT 20")->fetchAll();
} catch(Exception $e) { $dimTimeData = []; }

try {
    $dimDepartmentData = $db->query("SELECT department_name FROM dim_department LIMIT 20")->fetchAll();
} catch(Exception $e) { $dimDepartmentData = []; }

try {
    $factDataPreview = $db->query("
        SELECT fp.fact_id, fp.net_pay, fp.gross_pay, fp.total_deductions,
               de.full_name, de.department_name
        FROM fact_payroll fp
        JOIN dim_employee de ON fp.employee_key = de.employee_key
        LIMIT 10
    ")->fetchAll();
} catch(Exception $e) { $factDataPreview = []; }

// =============================================
// GET DEPARTMENT SUMMARY — from data mart view
// v_data_mart_dept_summary reads from star schema (fact + dims)
// =============================================
$deptSummary = [];
try {
    $deptSummary = $db->query("
        SELECT 
            department_name,
            CONCAT(month_name_text, ' ', year_number) AS period_name,
            year_number,
            month_number,
            quarter_number,
            payroll_count            AS employee_count,
            total_net_pay            AS total_net,
            total_gross_pay,
            total_deductions,
            avg_net_pay,
            highest_net_pay,
            lowest_net_pay
        FROM v_data_mart_dept_summary
        ORDER BY year_number DESC, month_number DESC, total_net_pay DESC
    ")->fetchAll();
} catch(Exception $e) { $deptSummary = []; }

// =============================================
// GET FACT TABLE DATA — includes all measures from new schema
// fact_payroll now stores: net_pay, gross_pay, total_deductions, allowances, overtime
// =============================================
$factData = [];
try {
    $factData = $db->query("
        SELECT 
            fp.fact_id,
            fp.net_pay,
            fp.gross_pay,
            fp.total_deductions,
            fp.allowances,
            fp.overtime,
            de.full_name,
            de.department_name,
            de.position_title,
            CONCAT(dt.month_name_text, ' ', dt.year_number) AS period,
            dt.quarter_number
        FROM fact_payroll fp
        JOIN dim_employee   de ON fp.employee_key = de.employee_key
        JOIN dim_time       dt ON fp.time_key     = dt.time_key
        JOIN dim_department dd ON fp.department_key = dd.department_key
        ORDER BY dt.year_number DESC, dt.month_number DESC, fp.net_pay DESC
        LIMIT 30
    ")->fetchAll();
} catch(Exception $e) { $factData = []; }

// =============================================
// GET ETL JOB HISTORY — includes error_message from new schema
// =============================================
$etlHistory = [];
try {
    $etlHistory = $db->query("
        SELECT job_id, job_name, job_status, records_processed,
               error_message, started_at, completed_at
        FROM etl_jobs 
        ORDER BY started_at DESC 
        LIMIT 10
    ")->fetchAll();
} catch(Exception $e) { $etlHistory = []; }

// =============================================
// GET TOTAL STATISTICS
// =============================================
$totalRecords = 0;
$totalAmount = 0;
try {
    $result = $db->query("SELECT COUNT(*) as total FROM payroll_records WHERE payroll_status = 'processed'");
    $totalRecords = $result->fetch()['total'];
    
    $result = $db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM payroll_records WHERE payroll_status = 'processed'");
    $totalAmount = $result->fetch()['total'];
} catch(Exception $e) {
    $totalRecords = 0;
    $totalAmount = 0;
}
?>

<style>
/* Data Warehouse Styles */
.dw-stat-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 18px;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}
.dw-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    border-color: #ff4d6d;
}
.stat-value {
    color: var(--text-primary);
    font-size: 28px;
    font-weight: 800;
}
.stat-label {
    color: var(--text-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-sub {
    color: var(--text-muted);
    font-size: 10px;
    margin-top: 5px;
}
.stat-icon {
    background: var(--hover-bg);
    padding: 12px;
    border-radius: 16px;
}
.stat-icon i {
    font-size: 22px;
}

/* Schema Card */
.schema-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 20px;
    border: 1px solid var(--border-color);
    transition: all 0.3s;
}
.schema-card:hover {
    border-color: #ff4d6d;
}
.card-title {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 15px;
}
.card-title i {
    color: #e6395c;
    margin-right: 8px;
}

/* Dimension Boxes */
.dimension-box {
    background: linear-gradient(135deg, #1a1a2e, #16213e);
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    color: white;
    transition: all 0.3s;
    height: 100%;
    cursor: pointer;
}
.dimension-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.dimension-box h6 {
    color: white;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 5px;
}
.dimension-box small {
    color: rgba(255,255,255,0.7);
    font-size: 10px;
}
.dimension-badge {
    background: rgba(255,255,255,0.2);
    color: #ff4d6d;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 600;
}

/* Fact Box */
.fact-box {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    color: white;
    transition: all 0.3s;
    cursor: pointer;
}
.fact-box:hover {
    transform: scale(1.02);
    box-shadow: 0 10px 25px rgba(230,57,92,0.3);
}
.fact-box h5 {
    color: white;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 5px;
}
.fact-box small {
    color: rgba(255,255,255,0.9);
    font-size: 10px;
}
.fact-badge {
    background: rgba(255,255,255,0.25);
    color: white;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 600;
}

.schema-connector {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    font-size: 20px;
    color: #e6395c;
}

.etl-button {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    color: white;
    border: none;
    padding: 12px 28px;
    border-radius: 40px;
    font-weight: 600;
    transition: all 0.3s;
    cursor: pointer;
}
.etl-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(230,57,92,0.4);
}

.dw-table {
    width: 100%;
    border-collapse: collapse;
}
.dw-table th {
    background: var(--table-header-bg);
    color: #e6395c;
    padding: 12px;
    font-weight: 600;
    font-size: 11px;
    text-align: left;
}
.dw-table td {
    padding: 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 13px;
}
.dw-table tr:hover td {
    background: var(--hover-bg);
}

.status-completed {
    background: #d1fae5;
    color: #059669;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    display: inline-block;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}
.empty-state i {
    font-size: 60px;
    color: var(--border-color);
    margin-bottom: 15px;
}
.empty-state h5 {
    color: var(--text-primary);
    margin-bottom: 8px;
}
.empty-state p {
    color: var(--text-muted);
    font-size: 13px;
}

/* Preview Modal */
.preview-modal .modal-content {
    background: var(--card-bg);
    border-radius: 24px;
    border: 1px solid var(--border-color);
}
.preview-modal .modal-header {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    color: white;
    border: none;
    border-radius: 24px 24px 0 0;
}
.preview-modal .modal-title {
    color: white;
    font-weight: 700;
}
.preview-modal .modal-body {
    max-height: 500px;
    overflow-y: auto;
}
.preview-table {
    width: 100%;
    border-collapse: collapse;
}
.preview-table th {
    background: var(--table-header-bg);
    color: #e6395c;
    padding: 10px;
    font-size: 11px;
    text-align: left;
}
.preview-table td {
    padding: 10px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 12px;
}
.record-count {
    font-size: 12px;
    margin-top: 10px;
    text-align: center;
    color: var(--text-muted);
}

/* Section Header */
.section-header {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-header i {
    color: #e6395c;
    font-size: 18px;
}

/* Responsive */
@media (max-width: 768px) {
    .schema-connector {
        display: none;
    }
}
</style>

<div>
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--text-primary);"><i class="fas fa-warehouse me-2" style="color:#e6395c;"></i> Data Warehouse & ETL</h4>
            <p class="small mb-0" style="color: var(--text-muted);">Star Schema Architecture | Automated ETL Process | Click any box to view data</p>
        </div>
        <form method="POST" onsubmit="return confirm('Run ETL process? This will refresh the data warehouse.');">
            <button type="submit" name="run_etl" class="etl-button"><i class="fas fa-sync-alt me-2"></i> Run ETL Process</button>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="dw-stat-card"><div class="d-flex justify-content-between"><div><div class="stat-label">Transaction Records</div><div class="stat-value"><?php echo number_format($totalRecords); ?></div><div class="stat-sub">Source data</div></div><div class="stat-icon"><i class="fas fa-database" style="color:#e6395c;"></i></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="dw-stat-card"><div class="d-flex justify-content-between"><div><div class="stat-label">Total Amount</div><div class="stat-value" style="font-size:20px;">₱<?php echo number_format($totalAmount,2); ?></div><div class="stat-sub">All time</div></div><div class="stat-icon"><i class="fas fa-chart-line" style="color:#059669;"></i></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="dw-stat-card"><div class="d-flex justify-content-between"><div><div class="stat-label">Fact Records</div><div class="stat-value"><?php echo number_format($factCount); ?></div><div class="stat-sub">In warehouse</div></div><div class="stat-icon"><i class="fas fa-chart-pie" style="color:#d97706;"></i></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="dw-stat-card"><div class="d-flex justify-content-between"><div><div class="stat-label">Dimensions</div><div class="stat-value"><?php echo $dimEmployeeCount + $dimTimeCount + $dimDepartmentCount; ?></div><div class="stat-sub">Total records</div></div><div class="stat-icon"><i class="fas fa-cubes" style="color:#4f46e5;"></i></div></div></div></div>
    </div>

    <!-- Star Schema Visualization -->
    <div class="schema-card mb-4">
        <div class="card-title"><i class="fas fa-star-of-life"></i> Star Schema Architecture</div>
        <div class="row align-items-center">
            <div class="col-md-3 mb-3">
                <div class="dimension-box" onclick="showPreview('dim_employee', <?php echo htmlspecialchars(json_encode($dimEmployeeData)); ?>, <?php echo $dimEmployeeCount; ?>, 'employee')">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h6>dim_employee</h6>
                    <small><?php echo $dimEmployeeCount; ?> records</small>
                    <div class="mt-2"><span class="dimension-badge">Employee Dim</span></div>
                </div>
            </div>
            <div class="col-md-1 schema-connector"><i class="fas fa-arrow-right fa-lg"></i></div>
            <div class="col-md-4 mb-3">
                <div class="fact-box" onclick="showPreview('fact_payroll', <?php echo htmlspecialchars(json_encode($factDataPreview)); ?>, <?php echo $factCount; ?>, 'fact')">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <h5>fact_payroll</h5>
                    <small><?php echo $factCount; ?> fact records</small>
                    <div class="mt-2"><span class="fact-badge">Measure: net_pay</span></div>
                </div>
            </div>
            <div class="col-md-1 schema-connector"><i class="fas fa-arrow-left fa-lg"></i></div>
            <div class="col-md-3 mb-3">
                <div class="dimension-box" onclick="showPreview('dim_time', <?php echo htmlspecialchars(json_encode($dimTimeData)); ?>, <?php echo $dimTimeCount; ?>, 'time')">
                    <i class="fas fa-calendar fa-2x mb-2"></i>
                    <h6>dim_time</h6>
                    <small><?php echo $dimTimeCount; ?> records</small>
                    <div class="mt-2"><span class="dimension-badge">Time Dim</span></div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4 offset-md-4">
                <div class="dimension-box" onclick="showPreview('dim_department', <?php echo htmlspecialchars(json_encode($dimDepartmentData)); ?>, <?php echo $dimDepartmentCount; ?>, 'department')">
                    <i class="fas fa-building fa-2x mb-2"></i>
                    <h6>dim_department</h6>
                    <small><?php echo $dimDepartmentCount; ?> records</small>
                    <div class="mt-2"><span class="dimension-badge">Department Dim</span></div>
                </div>
            </div>
        </div>
        <div class="text-center mt-3">
            <div class="d-inline-block px-3 py-1 rounded-pill" style="background: var(--hover-bg);">
                <span style="color: var(--text-muted); font-size: 11px;"><i class="fas fa-arrow-right me-1"></i> ETL: Extract → Transform → Load <i class="fas fa-arrow-left ms-1"></i></span>
            </div>
        </div>
    </div>

    <!-- Department Summary -->
    <div class="schema-card mb-4">
        <div class="section-header"><i class="fas fa-chart-simple"></i> Department Summary (Data Mart)</div>
        <div class="table-responsive">
            <table class="dw-table">
                <thead><tr><th>Department</th><th>Period</th><th>Employees</th><th>Total Net Pay</th><th>Average Pay</th></tr></thead>
                <tbody>
                    <?php if(empty($deptSummary)): ?>
                    <tr class="empty-state"><td colspan="5"><i class="fas fa-database"></i><h5>No Data Available</h5><p>Click "Run ETL Process" to populate.</p></td></tr>
                    <?php else: foreach($deptSummary as $row): ?>
                    <tr>
                        <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($row['department_name']); ?></td>
                        <td style="color: var(--text-secondary);"><?php echo $row['period_name']; ?></td>
                        <td style="color: var(--text-secondary);"><?php echo $row['employee_count']; ?></td>
                        <td class="text-success fw-bold">₱<?php echo number_format($row['total_net'],2); ?></td>
                        <td style="color: var(--text-secondary);">₱<?php echo number_format($row['avg_net_pay'],2); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fact Table -->
    <div class="schema-card mb-4">
        <div class="section-header"><i class="fas fa-table"></i> Fact Payroll Table</div>
        <div class="table-responsive">
            <table class="dw-table">
                <thead><tr><th>Period</th><th>Employee Name</th><th>Department</th><th>Position</th><th>Net Pay</th></tr></thead>
                <tbody>
                    <?php if(empty($factData)): ?>
                    <tr class="empty-state"><td colspan="5"><i class="fas fa-database"></i><h5>No Fact Data Available</h5><p>Click "Run ETL Process" to populate.</p></td></tr>
                    <?php else: foreach($factData as $row): ?>
                    <tr>
                        <td style="color: var(--text-secondary);"><?php echo $row['period']; ?></td>
                        <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['department_name']); ?></td>
                        <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($row['position_title']); ?></td>
                        <td class="text-success fw-bold">₱<?php echo number_format($row['net_pay'],2); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ETL Job History -->
    <?php if(!empty($etlHistory)): ?>
    <div class="schema-card">
        <div class="section-header"><i class="fas fa-history"></i> ETL Job History</div>
        <div class="table-responsive">
            <table class="dw-table">
                <thead><tr><th>Job Name</th><th>Started At</th><th>Status</th><th>Records</th></tr></thead>
                <tbody>
                    <?php foreach($etlHistory as $job): ?>
                    <tr>
                        <td class="fw-bold" style="color: var(--text-primary);"><?php echo htmlspecialchars($job['job_name']); ?></td>
                        <td style="color: var(--text-secondary);"><?php echo date('Y-m-d H:i:s', strtotime($job['started_at'])); ?></td>
                        <td><span class="status-completed"><i class="fas fa-check-circle me-1"></i> Completed</span></td>
                        <td style="color: var(--text-secondary);"><?php echo number_format($job['records_processed']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Preview Modal -->
<div class="modal fade preview-modal" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalTitle">Table Data</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showPreview(tableName, data, recordCount, type) {
    let modalTitle = '';
    let modalContent = '';
    
    if (type === 'employee') {
        modalTitle = 'dim_employee - Employee Dimension';
        modalContent = `
            <div class="table-responsive">
                <table class="preview-table">
                    <thead>
                        <tr><th>Employee ID</th><th>Code</th><th>Full Name</th><th>Department</th><th>Position</th></tr>
                    </thead>
                    <tbody>
                        ${data.map(row => `
                            <tr>
                                <td>${row.employee_id || '-'}</td>
                                <td>${row.employee_code || '-'}</td>
                                <td><strong>${row.full_name || '-'}</strong></td>
                                <td>${row.department_name || '-'}</td>
                                <td>${row.position_title || '-'}</td>
                            </tr>
                        `).join('')}
                        ${data.length === 0 ? '<tr><td colspan="5" class="text-center">No data available</td></tr>' : ''}
                    </tbody>
                </table>
            </div>
            <div class="record-count">Total records: ${recordCount} | Showing ${data.length} of ${recordCount}</div>
        `;
    } 
    else if (type === 'time') {
        modalTitle = 'dim_time - Time Dimension';
        modalContent = `
            <div class="table-responsive">
                <table class="preview-table">
                    <thead><tr><th>Year</th><th>Month</th><th>Month Name</th><th>Year-Month</th></tr></thead>
                    <tbody>
                        ${data.map(row => `
                            <tr>
                                <td>${row.year_number || '-'}</td>
                                <td>${row.month_number || '-'}</td>
                                <td>${row.month_name_text || '-'}</td>
                                <td>${row.year_month_text || '-'}</td>
                            </tr>
                        `).join('')}
                        ${data.length === 0 ? '<tr><td colspan="4" class="text-center">No data available</td></tr>' : ''}
                    </tbody>
                </table>
            </div>
            <div class="record-count">Total records: ${recordCount} | Showing ${data.length} of ${recordCount}</div>
        `;
    }
    else if (type === 'department') {
        modalTitle = 'dim_department - Department Dimension';
        modalContent = `
            <div class="table-responsive">
                <table class="preview-table">
                    <thead><tr><th>Department Name</th></tr></thead>
                    <tbody>
                        ${data.map(row => `<tr><td><strong>${row.department_name || '-'}</strong></td></tr>`).join('')}
                        ${data.length === 0 ? '<tr><td class="text-center">No data available</td></tr>' : ''}
                    </tbody>
                </table>
            </div>
            <div class="record-count">Total records: ${recordCount} | Showing ${data.length} of ${recordCount}</div>
        `;
    }
    else if (type === 'fact') {
        modalTitle = 'fact_payroll - Fact Table';
        modalContent = `
            <div class="table-responsive">
                <table class="preview-table">
                    <thead><tr><th>Fact ID</th><th>Employee Name</th><th>Department</th><th>Net Pay</th></tr></thead>
                    <tbody>
                        ${data.map(row => `
                            <tr>
                                <td>${row.fact_id || '-'}</td>
                                <td><strong>${row.full_name || '-'}</strong></td>
                                <td>${row.department_name || '-'}</td>
                                <td class="text-success fw-bold">₱${parseFloat(row.net_pay || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                            </tr>
                        `).join('')}
                        ${data.length === 0 ? '<tr><td colspan="4" class="text-center">No data available</td></tr>' : ''}
                    </tbody>
                </table>
            </div>
            <div class="record-count">Total records: ${recordCount} | Showing first ${data.length} records</div>
        `;
    }
    
    document.getElementById('previewModalTitle').innerHTML = `<i class="fas fa-database me-2"></i> ${modalTitle}`;
    document.getElementById('previewModalBody').innerHTML = modalContent;
    new bootstrap.Modal(document.getElementById('previewModal')).show();
}
</script>

<!-- Toast Notification -->
<?php if($message): ?>
<div class="toast-custom">
    <div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="5000">
        <div class="toast-header" style="background:<?php echo $messageType=='success'?'#10b981':'#e6395c'; ?>; color:white;">
            <strong>ETL Process</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"><?php echo $message; ?></div>
    </div>
</div>
<?php endif; ?>