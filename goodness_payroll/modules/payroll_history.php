<?php
require_once 'config/database.php';

// =============================================
// GET FILTER PARAMETERS
// =============================================
$year_filter = $_GET['year'] ?? '';
$month_filter = $_GET['month'] ?? '';
$dept_filter = $_GET['department'] ?? '';

// =============================================
// BUILD QUERY WITH FILTERS
// =============================================
$query = "
    SELECT 
        p.payroll_id,
        p.payroll_month,
        p.payroll_year,
        p.basic_salary,
        p.allowances_amount as allowances,
        p.overtime_amount as overtime,
        p.tax_amount as tax,
        p.sss_amount as sss,
        p.philhealth_amount as philhealth,
        p.pagibig_amount as pagibig,
        p.gross_amount as gross_pay,
        p.total_deductions_amount as total_deductions,
        p.net_amount as net_pay,
        p.processed_at,
        e.employee_id,
        e.employee_code,
        e.first_name,
        e.last_name,
        e.department,
        e.emp_position as position
    FROM payroll_records p
    INNER JOIN employees e ON p.employee_id = e.employee_id
    WHERE p.payroll_status = 'processed'
";

$params = [];

if ($year_filter) {
    $query .= " AND p.payroll_year = ?";
    $params[] = $year_filter;
}
if ($month_filter) {
    $query .= " AND p.payroll_month = ?";
    $params[] = $month_filter;
}
if ($dept_filter) {
    $query .= " AND e.department = ?";
    $params[] = $dept_filter;
}

$query .= " ORDER BY p.payroll_year DESC, p.payroll_month DESC, p.net_amount DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$payrolls = $stmt->fetchAll();

// =============================================
// GET SUMMARY STATISTICS
// =============================================
$summaryQuery = "
    SELECT 
        COUNT(*) as total_records,
        COALESCE(SUM(net_amount), 0) as total_paid,
        COALESCE(ROUND(AVG(net_amount), 2), 0) as avg_pay,
        COALESCE(MAX(net_amount), 0) as highest_pay,
        COALESCE(MIN(net_amount), 0) as lowest_pay,
        COUNT(DISTINCT employee_id) as unique_employees
    FROM payroll_records
    WHERE payroll_status = 'processed'
";
$summaryStmt = $db->prepare($summaryQuery);
$summaryStmt->execute();
$summary = $summaryStmt->fetch();

// =============================================
// GET FILTER OPTIONS - Years 2020 to 2026
// =============================================
$years = [];
for($y = 2026; $y >= 2020; $y--) {
    $years[] = ['payroll_year' => $y];
}
$departments = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department")->fetchAll();
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<style>
/* Payroll History Styles - Premium Dark Mode Compatible */
.history-stat-card {
    background: var(--card-bg);
    border-radius: 24px;
    padding: 20px;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}
.history-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #ff4d6d, #e6395c);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}
.history-stat-card:hover::before {
    transform: scaleX(1);
}
.history-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(230,57,92,0.1);
    border-color: #ff4d6d;
}
.stat-number {
    font-size: 32px;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1.2;
}
.stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}
.stat-sub {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 8px;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: var(--hover-bg);
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-icon i {
    font-size: 22px;
}

/* Filter Section */
.filter-section {
    background: var(--card-bg);
    border-radius: 24px;
    padding: 20px;
    border: 1px solid var(--border-color);
    margin-bottom: 24px;
}
.filter-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
    display: block;
    letter-spacing: 0.5px;
}
.filter-select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 13px;
    transition: all 0.2s;
    cursor: pointer;
}
.filter-select:focus {
    outline: none;
    border-color: #e6395c;
    box-shadow: 0 0 0 3px rgba(230,57,92,0.1);
}

/* Small Icon Filter Button */
.filter-icon-btn {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    border: none;
    border-radius: 12px;
    color: white;
    cursor: pointer;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    margin-top: 24px;
}
.filter-icon-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(230,57,92,0.3);
}
.filter-icon-btn i {
    font-size: 16px;
}

/* Reset Button */
.reset-btn {
    background: var(--hover-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-secondary);
    cursor: pointer;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    margin-top: 24px;
}
.reset-btn:hover {
    background: var(--border-color);
    color: var(--text-primary);
}
.reset-btn i {
    font-size: 16px;
}

/* Table Container */
.table-container {
    background: var(--card-bg);
    border-radius: 24px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}
.payroll-table {
    width: 100%;
    border-collapse: collapse;
}
.payroll-table th {
    background: var(--table-header-bg);
    padding: 14px 12px;
    font-weight: 600;
    font-size: 11px;
    color: #e6395c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
}
.payroll-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 13px;
    vertical-align: middle;
}
.payroll-table tr:hover td {
    background: var(--hover-bg);
}

/* Period Badge */
.period-badge {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

/* Employee Avatar */
.employee-avatar-small {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}
.employee-avatar-small i {
    color: white;
    font-size: 16px;
}
.employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.employee-details {
    flex: 1;
}
.employee-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 13px;
}
.employee-code {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Department Badge */
.dept-badge {
    background: var(--hover-bg);
    color: #e6395c;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

/* Amounts */
.amount-gross {
    font-weight: 600;
    color: var(--text-primary);
}
.amount-deduction {
    color: #dc2626;
    font-weight: 500;
}
.amount-net {
    font-weight: 700;
    color: #10b981;
    font-size: 15px;
}

/* View Details Button */
.btn-details {
    background: transparent;
    border: 1px solid #e6395c;
    color: #e6395c;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-details:hover {
    background: #e6395c;
    color: white;
}

/* Empty State */
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

/* Responsive */
@media (max-width: 768px) {
    .payroll-table th, .payroll-table td {
        padding: 10px 8px;
    }
    .employee-name {
        font-size: 12px;
    }
    .amount-net {
        font-size: 13px;
    }
}
</style>

<div>
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="history-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($summary['total_records'] ?? 0); ?></div>
                        <div class="stat-label">Total Records</div>
                        <div class="stat-sub">Payroll transactions</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice" style="color: #e6395c;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="history-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number">₱<?php echo number_format($summary['total_paid'] ?? 0, 2); ?></div>
                        <div class="stat-label">Total Paid</div>
                        <div class="stat-sub">All time</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-coins" style="color: #d97706;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="history-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number">₱<?php echo number_format($summary['avg_pay'] ?? 0, 2); ?></div>
                        <div class="stat-label">Average Pay</div>
                        <div class="stat-sub">Per employee</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line" style="color: #4f46e5;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="history-stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-number"><?php echo number_format($summary['unique_employees'] ?? 0); ?></div>
                        <div class="stat-label">Employees Paid</div>
                        <div class="stat-sub">Unique recipients</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users" style="color: #059669;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section - Icon Only Buttons -->
    <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="payroll_history">
            
            <div class="col-md-3">
                <label class="filter-label">YEAR</label>
                <select name="year" class="filter-select">
                    <option value="">All Years</option>
                    <?php foreach($years as $y): ?>
                    <option value="<?php echo $y['payroll_year']; ?>" <?php echo $year_filter == $y['payroll_year'] ? 'selected' : ''; ?>>
                        <?php echo $y['payroll_year']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="filter-label">MONTH</label>
                <select name="month" class="filter-select">
                    <option value="">All Months</option>
                    <?php foreach($months as $num => $name): ?>
                    <option value="<?php echo $num; ?>" <?php echo $month_filter == $num ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-4">
                <label class="filter-label">DEPARTMENT</label>
                <select name="department" class="filter-select">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $d): ?>
                    <option value="<?php echo htmlspecialchars($d['department']); ?>" <?php echo $dept_filter == $d['department'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d['department']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="filter-icon-btn" title="Apply Filters">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="?page=payroll_history" class="reset-btn" title="Reset Filters">
                        <i class="fas fa-undo-alt"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Payroll Records Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="payroll-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($payrolls)): ?>
                    <tr class="empty-state-row">
                        <td colspan="8" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h5>No Payroll Records Found</h5>
                            <p>Try adjusting your filters or process a new payroll.</p>
                            <a href="?page=process_payroll" class="btn-pink" style="display: inline-block; margin-top: 10px;">Process Payroll</a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($payrolls as $pay): 
                        $periodName = date('F Y', mktime(0,0,0,$pay['payroll_month'],1,$pay['payroll_year']));
                        $initial = strtoupper(substr($pay['first_name'], 0, 1));
                    ?>
                    <tr>
                        <td>
                            <span class="period-badge"><?php echo $periodName; ?></span>
                        </td>
                        <td>
                            <div class="employee-info">
                                <div class="employee-avatar-small">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="employee-details">
                                    <div class="employee-name"><?php echo htmlspecialchars($pay['first_name'] . ' ' . $pay['last_name']); ?></div>
                                    <div class="employee-code"><?php echo htmlspecialchars($pay['employee_code']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="dept-badge"><?php echo htmlspecialchars($pay['department']); ?></span>
                            <div class="employee-code" style="margin-top: 4px;"><?php echo htmlspecialchars($pay['position']); ?></div>
                        </td>
                        <td class="amount-gross">₱<?php echo number_format($pay['gross_pay'], 2); ?></td>
                        <td class="amount-deduction">-₱<?php echo number_format($pay['total_deductions'], 2); ?></td>
                        <td class="amount-net">₱<?php echo number_format($pay['net_pay'], 2); ?></td>
                        <td style="font-size: 11px; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($pay['processed_at'])); ?></td>
                        <td>
                            <button class="btn-details" onclick='showDetails(<?php echo htmlspecialchars(json_encode($pay)); ?>)'>
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 24px; background: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="modal-header" style="background: linear-gradient(135deg, #ff4d6d, #e6395c); border: none; border-radius: 24px 24px 0 0; padding: 20px 25px;">
                <h5 class="modal-title text-white"><i class="fas fa-receipt me-2"></i> Payroll Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody" style="padding: 25px;">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 15px 25px;">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 40px;">Close</button>
                <button type="button" class="btn-pink" onclick="window.print()" style="border-radius: 40px;">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showDetails(data) {
    const periodName = new Date(data.payroll_year, data.payroll_month - 1, 1).toLocaleString('default', { month: 'long', year: 'numeric' });
    
    const html = `
        <div class="row">
            <div class="col-md-6">
                <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                    <strong style="color: var(--text-primary);">Employee Information</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Full Name:</span>
                    <strong style="color: var(--text-primary);">${data.first_name} ${data.last_name}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Employee Code:</span>
                    <strong style="color: var(--text-primary);">${data.employee_code}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Department:</span>
                    <strong style="color: var(--text-primary);">${data.department}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Position:</span>
                    <strong style="color: var(--text-primary);">${data.position}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Pay Period:</span>
                    <strong style="color: var(--text-primary);">${periodName}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Processed On:</span>
                    <strong style="color: var(--text-primary);">${new Date(data.processed_at).toLocaleString()}</strong>
                </div>
            </div>
            <div class="col-md-6">
                <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                    <strong style="color: var(--text-primary);">Payroll Breakdown</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Basic Salary:</span>
                    <strong style="color: var(--text-primary);">₱${parseFloat(data.basic_salary).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Allowances:</span>
                    <strong style="color: #10b981;">+₱${parseFloat(data.allowances).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Overtime:</span>
                    <strong style="color: #10b981;">+₱${parseFloat(data.overtime).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border-color);">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Gross Pay:</span>
                    <strong style="color: var(--text-primary);">₱${parseFloat(data.gross_pay).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Tax Deduction:</span>
                    <strong style="color: #dc2626;">-₱${parseFloat(data.tax).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">SSS Contribution:</span>
                    <strong style="color: #dc2626;">-₱${parseFloat(data.sss).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">PhilHealth:</span>
                    <strong style="color: #dc2626;">-₱${parseFloat(data.philhealth).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-bottom: 12px;">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Pag-IBIG:</span>
                    <strong style="color: #dc2626;">-₱${parseFloat(data.pagibig).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
                <div style="margin-top: 15px; padding-top: 12px; border-top: 2px solid var(--border-color);">
                    <span style="color: var(--text-muted); width: 120px; display: inline-block;">Net Pay:</span>
                    <strong style="color: #10b981; font-size: 18px;">₱${parseFloat(data.net_pay).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('detailsModalBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>