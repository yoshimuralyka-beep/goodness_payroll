<?php
require_once 'config/database.php';
require_once 'includes/log_activity.php';

$message = '';
$messageType = '';
$userId = $_SESSION['user_id'];

// GET ALL EMPLOYEES AND DEPARTMENTS
$departments = $db->query("SELECT DISTINCT department FROM employees WHERE emp_status = 'active' AND department IS NOT NULL ORDER BY department")->fetchAll();
$employees = $db->query("SELECT employee_id, employee_code, first_name, last_name, department, basic_salary FROM employees WHERE emp_status = 'active' ORDER BY department, first_name")->fetchAll();

// Create department employee mapping
$departmentEmployees = [];
$departmentSalarySum = [];
$departmentEmployeeCount = [];

foreach($employees as $emp) {
    $dept = $emp['department'];
    if (!isset($departmentEmployees[$dept])) {
        $departmentEmployees[$dept] = [];
        $departmentSalarySum[$dept] = 0;
        $departmentEmployeeCount[$dept] = 0;
    }
    $departmentEmployees[$dept][] = $emp;
    $departmentSalarySum[$dept] += $emp['basic_salary'];
    $departmentEmployeeCount[$dept]++;
}

// PROCESS PAYROLL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $month = $_POST['month'];
    $year = $_POST['year'];
    $selection_type = $_POST['selection_type'] ?? 'all';
    $selected_employees = $_POST['employees'] ?? [];
    $selected_department = $_POST['department'] ?? '';
    
    try {
        $check = $db->prepare("SELECT COUNT(*) FROM payroll_records WHERE payroll_month = ? AND payroll_year = ?");
        $check->execute([$month, $year]);
        if ($check->fetchColumn() > 0) {
            throw new Exception("Payroll for this period already exists!");
        }
        
        if ($selection_type == 'all') {
            $employeeStmt = $db->prepare("SELECT employee_id, first_name, last_name, basic_salary FROM employees WHERE emp_status = 'active'");
            $employeeStmt->execute();
        } elseif ($selection_type == 'department' && $selected_department) {
            $employeeStmt = $db->prepare("SELECT employee_id, first_name, last_name, basic_salary FROM employees WHERE emp_status = 'active' AND department = ?");
            $employeeStmt->execute([$selected_department]);
        } elseif ($selection_type == 'specific' && !empty($selected_employees)) {
            $placeholders = implode(',', array_fill(0, count($selected_employees), '?'));
            $employeeStmt = $db->prepare("SELECT employee_id, first_name, last_name, basic_salary FROM employees WHERE emp_status = 'active' AND employee_id IN ($placeholders)");
            $employeeStmt->execute($selected_employees);
        } else {
            throw new Exception("No employees selected.");
        }
        
        $employeesToProcess = $employeeStmt->fetchAll();
        if (empty($employeesToProcess)) {
            throw new Exception("No active employees found.");
        }
        
        $db->beginTransaction();
        $count = 0;
        
        foreach ($employeesToProcess as $emp) {
            $allowances = $emp['basic_salary'] * 0.10;
            $overtime = $emp['basic_salary'] * 0.05;
            $gross = $emp['basic_salary'] + $allowances + $overtime;
            $tax = $emp['basic_salary'] * 0.10;
            $sss = 1350;
            $philhealth = 450;
            $pagibig = 200;
            $deductions = $tax + $sss + $philhealth + $pagibig;
            $net = $gross - $deductions;
            
            $stmt = $db->prepare("INSERT INTO payroll_records 
                (employee_id, payroll_month, payroll_year, basic_salary, allowances_amount, overtime_amount, 
                 tax_amount, sss_amount, philhealth_amount, pagibig_amount, gross_amount, total_deductions_amount, 
                 net_amount, payroll_status, processed_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'processed',NOW())");
            $stmt->execute([
                $emp['employee_id'], $month, $year, $emp['basic_salary'],
                $allowances, $overtime, $tax, $sss, $philhealth, $pagibig,
                $gross, $deductions, $net
            ]);
            $count++;
        }
        
        $db->commit();
        $periodName = date('F Y', mktime(0,0,0,$month,1,$year));
        logActivity($db, $userId, 'PROCESS_PAYROLL', 'Payroll', "Processed payroll for $periodName - $count employees");
        $message = "Payroll successfully processed for $count employees in period: $periodName";
        $messageType = "success";
        echo "<script>setTimeout(function() { window.location.href = '?page=process_payroll'; }, 1500);</script>";
        
    } catch (Exception $e) {
        try {
            if ($db->inTransaction()) $db->rollBack();
        } catch (Exception $rollbackError) {}
        $message = "Failed: " . $e->getMessage();
        $messageType = "danger";
    }
}

// DELETE PAYROLL RECORDS
if (isset($_GET['delete']) && isset($_GET['month']) && isset($_GET['year'])) {
    $month = $_GET['month'];
    $year = $_GET['year'];
    try {
        $stmt = $db->prepare("DELETE FROM payroll_records WHERE payroll_month = ? AND payroll_year = ? AND payroll_status = 'processed'");
        $stmt->execute([$month, $year]);
        $message = "Payroll records for " . date('F Y', mktime(0,0,0,$month,1,$year)) . " have been deleted.";
        $messageType = "warning";
        echo "<script>setTimeout(function() { window.location.href = '?page=process_payroll'; }, 1500);</script>";
    } catch (Exception $e) {
        $message = "Failed to delete: " . $e->getMessage();
        $messageType = "danger";
    }
}

// GET EXISTING PAYROLL PERIODS
$periods = $db->query("
    SELECT payroll_year, payroll_month, COUNT(*) as emp_count, SUM(net_amount) as total_paid, 
           MAX(processed_at) as processed_date 
    FROM payroll_records 
    WHERE payroll_status = 'processed' 
    GROUP BY payroll_year, payroll_month 
    ORDER BY payroll_year DESC, payroll_month DESC
")->fetchAll();

// GET STATISTICS
$activeCount = count($employees);
$totalSalarySum = array_sum(array_column($employees, 'basic_salary'));
?>

<style>
/* ── Process Payroll Styles ── */
.process-card {
    background: var(--card-bg);
    border-radius: 24px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s;
}
.process-card:hover {
    border-color: #ff4d6d;
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
}
.card-header-premium {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    padding: 20px;
    text-align: center;
}
.card-header-premium h3 { color: white; font-weight: 700; margin: 0; font-size: 20px; }
.card-header-premium p  { color: rgba(255,255,255,0.85); font-size: 12px; margin-top: 5px; }
.card-header-premium i  { font-size: 28px; margin-bottom: 8px; }
.card-body-premium { padding: 25px; }

.form-group { margin-bottom: 20px; }
.form-label {
    font-size: 11px; font-weight: 700; color: var(--text-secondary);
    margin-bottom: 8px; display: block; letter-spacing: 0.5px;
}
.form-control-premium {
    width: 100%; padding: 12px 16px;
    border: 1.5px solid var(--border-color); border-radius: 14px;
    background: var(--input-bg); color: var(--text-primary);
    font-size: 14px; transition: all 0.2s;
}
.form-control-premium:focus {
    outline: none; border-color: #e6395c;
    box-shadow: 0 0 0 3px rgba(230,57,92,0.15);
}

.toggle-group { display: flex; gap: 10px; margin-bottom: 20px; }
.toggle-btn {
    flex: 1; padding: 10px 8px;
    border: 2px solid var(--border-color); background: var(--input-bg);
    color: var(--text-secondary); border-radius: 40px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    text-align: center; transition: all 0.3s;
}
.toggle-btn i { margin-right: 6px; }
.toggle-btn:hover { border-color: #e6395c; color: #e6395c; transform: translateY(-2px); }
.toggle-btn.active {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    border-color: transparent; color: white;
    box-shadow: 0 4px 12px rgba(230,57,92,0.3);
}

.selection-panel {
    background: var(--input-bg); border-radius: 16px;
    padding: 15px; margin-top: 15px; border: 1px solid var(--border-color);
}
.panel-title {
    font-size: 12px; font-weight: 600; color: var(--text-primary);
    margin-bottom: 12px; display: flex; align-items: center; gap: 6px;
}
.panel-title i { color: #e6395c; }
.dept-select {
    width: 100%; padding: 12px 16px;
    border: 1.5px solid var(--border-color); border-radius: 14px;
    background: var(--card-bg); color: var(--text-primary);
    font-size: 14px; cursor: pointer;
}

.employee-list-container {
    max-height: 320px; overflow-y: auto;
    border: 1px solid var(--border-color); border-radius: 14px;
    background: var(--card-bg);
}
.employee-list-container::-webkit-scrollbar { width: 5px; }
.employee-list-container::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.employee-list-container::-webkit-scrollbar-thumb { background: #e6395c; border-radius: 10px; }

.employee-item {
    display: flex; align-items: center; padding: 12px 15px;
    border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s;
}
.employee-item:hover { background: var(--hover-bg); }
.employee-item:last-child { border-bottom: none; }
.employee-checkbox { width: 18px; height: 18px; margin-right: 15px; accent-color: #e6395c; cursor: pointer; }
.employee-avatar-small {
    width: 36px; height: 36px; border-radius: 12px;
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    display: flex; align-items: center; justify-content: center; margin-right: 12px;
}
.employee-avatar-small i { color: white; font-size: 16px; }
.employee-info { flex: 1; }
.employee-name { font-weight: 600; color: var(--text-primary); font-size: 13px; }
.employee-details { font-size: 10px; color: var(--text-muted); margin-top: 2px; }
.employee-salary { font-weight: 700; color: #10b981; font-size: 13px; }

.batch-buttons { display: flex; gap: 10px; margin-bottom: 15px; }
.btn-batch { padding: 6px 14px; border-radius: 30px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: none; }
.btn-batch-select { background: #e6395c; color: white; }
.btn-batch-select:hover { background: #c92a4a; transform: scale(1.02); }
.btn-batch-deselect { background: var(--hover-bg); color: var(--text-secondary); }
.btn-batch-deselect:hover { background: var(--border-color); }

.summary-box {
    background: linear-gradient(135deg, var(--hover-bg), var(--card-bg));
    border-radius: 18px; padding: 18px; margin-top: 20px; border: 1px solid var(--border-color);
}
.summary-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; }
.summary-row:first-child { padding-top: 0; }
.summary-row:last-child { border-top: 1px solid var(--border-color); margin-top: 5px; padding-top: 12px; }
.summary-label { color: var(--text-muted); font-size: 12px; font-weight: 500; }
.summary-value { font-weight: 800; font-size: 20px; color: var(--text-primary); }
.summary-value.highlight { color: #10b981; }

.btn-process {
    width: 100%; padding: 14px;
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    border: none; border-radius: 50px; color: white;
    font-weight: 700; font-size: 16px; cursor: pointer;
    transition: all 0.3s; margin-top: 20px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    box-shadow: 0 4px 12px rgba(230,57,92,0.3);
}
.btn-process:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(230,57,92,0.4); }

/* ── Periods Card ── */
.periods-card {
    background: var(--card-bg); border-radius: 24px;
    border: 1px solid var(--border-color); height: 100%;
}
.periods-header {
    padding: 20px 20px 0;
    display: flex; justify-content: space-between; align-items: center;
}
.periods-header h5 { font-weight: 700; color: var(--text-primary); margin: 0; font-size: 16px; }
.period-badge {
    background: var(--hover-bg); color: #e6395c;
    padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600;
}
.periods-list {
    padding: 15px 20px 20px; max-height: 500px; overflow-y: auto;
}
.periods-list::-webkit-scrollbar { width: 4px; }
.periods-list::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
.periods-list::-webkit-scrollbar-thumb { background: #e6395c; border-radius: 10px; }

/* ── Period Item ── */
.period-item {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 14px 16px;
    margin-bottom: 12px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    min-width: 0;
}
.period-item:hover { border-color: #ff4d6d; transform: translateX(3px); }

/* col: name + date — fixed min-width so it never squishes */
.period-item .pi-info {
    flex: 0 0 auto;
    min-width: 90px;
}
.period-name { font-weight: 700; color: var(--text-primary); font-size: 13px; white-space: nowrap; }
.period-date { font-size: 10px; color: var(--text-muted); margin-top: 2px; white-space: nowrap; }

/* col: employee pill */
.period-item .pi-pill {
    flex: 0 0 auto;
}
.period-count {
    background: var(--hover-bg); color: #e6395c;
    padding: 4px 10px; border-radius: 30px;
    font-size: 11px; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
}

/* col: amount — grows to fill remaining space */
.period-item .pi-amount {
    flex: 1 1 auto;
    min-width: 0;
    text-align: right;
    padding-right: 4px;
}
.period-total {
    font-weight: 700; color: #10b981; font-size: 15px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.period-avg { font-size: 10px; color: var(--text-muted); margin-top: 2px; text-decoration: line-through; white-space: nowrap; }

/* col: buttons — fixed width, never shrinks */
.period-item .pi-actions {
    flex: 0 0 auto;
    display: flex;
    gap: 7px;
    align-items: center;
}

/* ── Improved Period Action Buttons ── */
.btn-actions-wrap {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: nowrap;
}

/* VIEW — teal filled pill */
.btn-period-view {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 15px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.18s, box-shadow 0.18s, transform 0.15s;
    /* Teal filled */
    background: #0d9488;
    color: #ffffff;
    border: none;
    box-shadow: 0 2px 8px rgba(13,148,136,0.30);
    letter-spacing: 0.01em;
}
.btn-period-view i { font-size: 13px; }
.btn-period-view:hover {
    background: #0f766e;
    box-shadow: 0 4px 14px rgba(13,148,136,0.45);
    transform: translateY(-2px);
    color: #ffffff;
    text-decoration: none;
}
.btn-period-view:active { transform: translateY(0); box-shadow: none; }

/* DELETE — ghost red with icon, fills on hover */
.btn-period-delete {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 13px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.18s, border-color 0.18s, color 0.18s, transform 0.15s;
    background: transparent;
    color: #ef4444;
    border: 1.5px solid rgba(239,68,68,0.45);
    letter-spacing: 0.01em;
}
.btn-period-delete i { font-size: 13px; }
.btn-period-delete:hover {
    background: rgba(239,68,68,0.12);
    border-color: #ef4444;
    color: #ff6b6b;
    transform: translateY(-2px);
    text-decoration: none;
}
.btn-period-delete:active { transform: translateY(0); }

/* ── Info Cards ── */
.info-cards { margin-top: 20px; }
.info-card-item {
    background: var(--card-bg); border-radius: 18px; padding: 15px;
    display: flex; gap: 15px; border: 1px solid var(--border-color);
    transition: all 0.3s; height: 100%;
}
.info-card-item:hover { border-color: #ff4d6d; transform: translateY(-3px); }
.info-icon {
    width: 48px; height: 48px; background: var(--hover-bg);
    border-radius: 16px; display: flex; align-items: center; justify-content: center;
}
.info-icon i { font-size: 24px; color: #e6395c; }
.info-content h6 { font-weight: 700; color: var(--text-primary); margin-bottom: 5px; font-size: 13px; }
.info-content p  { color: var(--text-muted); font-size: 11px; margin: 0; }

/* ── Empty State ── */
.empty-state-periods { text-align: center; padding: 50px 20px; }
.empty-state-periods i { font-size: 50px; color: var(--border-color); margin-bottom: 15px; }
.empty-state-periods p { color: var(--text-muted); margin: 0; font-size: 13px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .toggle-group { flex-direction: column; }
    .period-item .row { flex-direction: column; gap: 10px; }
    .period-item .text-end { text-align: left !important; }
    .btn-actions-wrap { justify-content: flex-start; }
}
</style>

<div>
    <div class="row">
        <!-- Process Payroll Form -->
        <div class="col-lg-6 mb-4">
            <div class="process-card">
                <div class="card-header-premium">
                    <i class="fas fa-calculator"></i>
                    <h3>Run Payroll</h3>
                    <p>Process monthly payroll for employees</p>
                </div>
                <div class="card-body-premium">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-calendar-alt me-1" style="color:#e6395c;"></i> SELECT PERIOD</label>
                            <div class="row g-2">
                                <div class="col-7">
                                    <select name="month" class="form-control-premium" required>
                                        <option value="">Month</option>
                                        <?php for($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-5">
                                    <select name="year" class="form-control-premium" required>
                                        <option value="">Year</option>
                                        <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-users me-1" style="color:#e6395c;"></i> SELECT EMPLOYEES</label>
                            <div class="toggle-group">
                                <div class="toggle-btn" data-selection="all" onclick="setSelectionType('all')">
                                    <i class="fas fa-users"></i> All Employees
                                </div>
                                <div class="toggle-btn" data-selection="department" onclick="setSelectionType('department')">
                                    <i class="fas fa-building"></i> By Department
                                </div>
                                <div class="toggle-btn" data-selection="specific" onclick="setSelectionType('specific')">
                                    <i class="fas fa-user-check"></i> Specific
                                </div>
                            </div>
                            <input type="hidden" name="selection_type" id="selection_type" value="all">

                            <!-- Department Panel -->
                            <div id="departmentPanel" style="display:none;" class="selection-panel">
                                <div class="panel-title"><i class="fas fa-building"></i> Choose Department</div>
                                <select name="department" id="departmentSelect" class="dept-select">
                                    <option value="">Select Department</option>
                                    <?php foreach($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Specific Employees Panel -->
                            <div id="specificPanel" style="display:none;" class="selection-panel">
                                <div class="panel-title"><i class="fas fa-user-check"></i> Select Employees</div>
                                <div class="batch-buttons">
                                    <button type="button" class="btn-batch btn-batch-select" onclick="selectAll()">
                                        <i class="fas fa-check-double"></i> Select All
                                    </button>
                                    <button type="button" class="btn-batch btn-batch-deselect" onclick="deselectAll()">
                                        <i class="fas fa-times"></i> Deselect All
                                    </button>
                                </div>
                                <div class="employee-list-container">
                                    <?php foreach($employees as $emp): ?>
                                    <label class="employee-item">
                                        <input type="checkbox" name="employees[]" value="<?php echo $emp['employee_id']; ?>" class="employee-checkbox" data-salary="<?php echo $emp['basic_salary']; ?>">
                                        <div class="employee-avatar-small"><i class="fas fa-user"></i></div>
                                        <div class="employee-info">
                                            <div class="employee-name"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                            <div class="employee-details"><?php echo htmlspecialchars($emp['employee_code']); ?> • <?php echo htmlspecialchars($emp['department']); ?></div>
                                        </div>
                                        <div class="employee-salary">₱<?php echo number_format($emp['basic_salary'], 2); ?></div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="summary-box">
                            <div class="summary-row">
                                <span class="summary-label">Employees to Process</span>
                                <span class="summary-value" id="employeeCount"><?php echo $activeCount; ?></span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Estimated Gross Payroll</span>
                                <span class="summary-value highlight" id="estimatedGross">₱<?php echo number_format($totalSalarySum * 1.15, 2); ?></span>
                            </div>
                        </div>

                        <button type="submit" name="process_payroll" class="btn-process"
                            onclick="return confirm('Process payroll for selected period? This action cannot be undone.');">
                            <i class="fas fa-play"></i> Process Payroll
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Processed Periods -->
        <div class="col-lg-6 mb-4">
            <div class="periods-card">
                <div class="periods-header">
                    <h5><i class="fas fa-history me-2" style="color:#e6395c;"></i> Processed Periods</h5>
                    <span class="period-badge"><?php echo count($periods); ?> periods</span>
                </div>
                <div class="periods-list">
                    <?php if(empty($periods)): ?>
                    <div class="empty-state-periods">
                        <i class="fas fa-inbox"></i>
                        <p>No payroll records yet</p>
                        <small style="color:var(--text-muted);">Process your first payroll</small>
                    </div>
                    <?php else: ?>
                    <?php foreach($periods as $p):
                        $periodName = date('F Y', mktime(0,0,0,$p['payroll_month'],1,$p['payroll_year']));
                        $avgPerEmp  = $p['emp_count'] > 0 ? $p['total_paid'] / $p['emp_count'] : 0;
                    ?>
                    <div class="period-item">

                        <!-- Name + date -->
                        <div class="pi-info">
                            <div class="period-name"><?php echo $periodName; ?></div>
                            <div class="period-date"><?php echo date('M d, Y', strtotime($p['processed_date'])); ?></div>
                        </div>

                        <!-- Employee pill -->
                        <div class="pi-pill">
                            <span class="period-count">
                                <i class="fas fa-users" style="font-size:10px;"></i>
                                <?php echo $p['emp_count']; ?> employees
                            </span>
                        </div>

                        <!-- Amount (grows to fill) -->
                        <div class="pi-amount">
                            <div class="period-total">₱<?php echo number_format($p['total_paid'], 2); ?></div>
                            <div class="period-avg">₱<?php echo number_format($avgPerEmp, 2); ?> avg</div>
                        </div>

                        <!-- Buttons (fixed, never shrinks) -->
                        <div class="pi-actions">
                            <a href="?page=payroll_history&month=<?php echo $p['payroll_month']; ?>&year=<?php echo $p['payroll_year']; ?>"
                               class="btn-period-view" title="View payroll details">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="?page=process_payroll&delete=1&month=<?php echo $p['payroll_month']; ?>&year=<?php echo $p['payroll_year']; ?>"
                               class="btn-period-delete"
                               onclick="return confirm('Delete payroll records for <?php echo $periodName; ?>?')"
                               title="Delete this payroll period">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>

                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Cards -->
    <div class="row info-cards">
        <div class="col-md-4 mb-3">
            <div class="info-card-item">
                <div class="info-icon"><i class="fas fa-users"></i></div>
                <div class="info-content">
                    <h6>Selective Processing</h6>
                    <p>Process payroll for all, by department, or specific employees</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-card-item">
                <div class="info-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="info-content">
                    <h6>Transaction Safety</h6>
                    <p>ACID compliant with COMMIT/ROLLBACK protection</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-card-item">
                <div class="info-icon"><i class="fas fa-trash-alt"></i></div>
                <div class="info-content">
                    <h6>Delete &amp; Reprocess</h6>
                    <p>Remove incorrect payroll runs and reprocess</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const departmentEmployees  = <?php
    $deptEmpList = [];
    foreach($departmentEmployees as $dept => $emps) {
        $deptEmpList[$dept] = [];
        foreach($emps as $emp) {
            $deptEmpList[$dept][] = ['id' => $emp['employee_id'], 'salary' => $emp['basic_salary']];
        }
    }
    echo json_encode($deptEmpList);
?>;
const departmentSalarySums = <?php echo json_encode($departmentSalarySum); ?>;
const departmentCounts     = <?php echo json_encode($departmentEmployeeCount); ?>;

let currentSelection = 'all';

function setSelectionType(type) {
    currentSelection = type;
    document.getElementById('selection_type').value = type;

    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-selection') === type);
    });

    document.getElementById('departmentPanel').style.display = type === 'department' ? 'block' : 'none';
    document.getElementById('specificPanel').style.display   = type === 'specific'   ? 'block' : 'none';
    updateSummary();
}

function updateSummary() {
    const countEl = document.getElementById('employeeCount');
    const grossEl = document.getElementById('estimatedGross');

    if (currentSelection === 'all') {
        const totalSalary = <?php echo $totalSalarySum; ?>;
        countEl.innerText = <?php echo $activeCount; ?>;
        grossEl.innerText = '₱' + (totalSalary * 1.15).toLocaleString(undefined, {minimumFractionDigits: 2});
    } else if (currentSelection === 'department') {
        const dept = document.getElementById('departmentSelect').value;
        if (dept && departmentCounts[dept]) {
            countEl.innerText = departmentCounts[dept];
            grossEl.innerText = '₱' + (departmentSalarySums[dept] * 1.15).toLocaleString(undefined, {minimumFractionDigits: 2});
            document.querySelectorAll('.employee-checkbox').forEach(cb => {
                cb.checked = departmentEmployees[dept]?.some(e => e.id == cb.value) ?? false;
            });
        } else {
            countEl.innerText = 'Select a department';
            grossEl.innerText = 'Select department to estimate';
        }
    } else if (currentSelection === 'specific') {
        const checked = document.querySelectorAll('.employee-checkbox:checked');
        let total = 0;
        checked.forEach(cb => total += parseFloat(cb.getAttribute('data-salary')));
        if (checked.length > 0) {
            countEl.innerText = checked.length;
            grossEl.innerText = '₱' + (total * 1.15).toLocaleString(undefined, {minimumFractionDigits: 2});
        } else {
            countEl.innerText = 'No employees selected';
            grossEl.innerText = 'Select employees to estimate';
        }
    }
}

function selectAll()   { document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = true);  updateSummary(); }
function deselectAll() { document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false); updateSummary(); }

document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('.toggle-btn[data-selection="all"]').classList.add('active');
    document.getElementById('departmentSelect')?.addEventListener('change', updateSummary);
    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.addEventListener('change', updateSummary));
});
</script>

<!-- Toast Notification -->
<?php if($message): ?>
<div class="toast-custom">
    <div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="5000">
        <div class="toast-header" style="background:<?php echo $messageType == 'success' ? '#10b981' : '#e6395c'; ?>; color:white;">
            <i class="fas fa-bell me-2"></i>
            <strong class="me-auto">Payroll Processing</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" style="background:var(--card-bg); color:var(--text-primary);"><?php echo $message; ?></div>
    </div>
</div>
<?php endif; ?>