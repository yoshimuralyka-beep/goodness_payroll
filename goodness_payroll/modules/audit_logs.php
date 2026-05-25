<?php
require_once 'config/database.php';

// Admin only access
if (($_SESSION['role'] ?? 'staff') !== 'admin') {
    header("Location: index.php?page=dashboard");
    exit();
}

// =============================================
// GET FILTER PARAMETERS
// =============================================
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// =============================================
// BUILD QUERY
// =============================================
$query = "SELECT * FROM activity_logs WHERE 1=1";
$params = [];

if (!empty($action_filter)) {
    $query .= " AND action_name = ?";
    $params[] = $action_filter;
}
if (!empty($user_filter)) {
    $query .= " AND username LIKE ?";
    $params[] = "%$user_filter%";
}
if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY created_at DESC LIMIT 200";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// =============================================
// GET STATISTICS
// =============================================
$totalLogs = $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$todayLogs = $db->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$uniqueUsers = $db->query("SELECT COUNT(DISTINCT username) FROM activity_logs")->fetchColumn();

// Get unique actions for filter
$actions = $db->query("SELECT DISTINCT action_name FROM activity_logs ORDER BY action_name")->fetchAll();
?>

<style>
/* Audit Logs Styles - Matching Dashboard */
.audit-stat-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 18px;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}
.audit-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    border-color: #ff4d6d;
}
.audit-stat-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--text-primary);
}
.audit-stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 5px;
}
.audit-stat-sub {
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 8px;
}
.audit-stat-icon {
    background: var(--hover-bg);
    padding: 12px;
    border-radius: 16px;
}
.audit-stat-icon i {
    font-size: 22px;
}

/* Filter Section */
.audit-filter-section {
    background: var(--card-bg);
    border-radius: 20px;
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
.filter-select, .filter-input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 13px;
    transition: all 0.2s;
}
.filter-select:focus, .filter-input:focus {
    outline: none;
    border-color: #e6395c;
    box-shadow: 0 0 0 3px rgba(230,57,92,0.1);
}
.btn-filter {
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    border: none;
    border-radius: 12px;
    color: white;
    cursor: pointer;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    width: 100%;
}
.btn-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(230,57,92,0.3);
}
.btn-reset {
    background: var(--hover-bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    width: 100%;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}
.btn-reset:hover {
    background: var(--border-color);
    color: var(--text-primary);
}
.btn-export {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    border-radius: 12px;
    color: white;
    cursor: pointer;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,185,129,0.3);
}

/* Table Container */
.audit-table-container {
    background: var(--card-bg);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
}
.audit-table {
    width: 100%;
    border-collapse: collapse;
}
.audit-table th {
    background: var(--table-header-bg);
    padding: 14px 12px;
    font-weight: 600;
    font-size: 11px;
    color: #e6395c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
}
.audit-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 12px;
    vertical-align: middle;
}
.audit-table tr:hover td {
    background: var(--hover-bg);
}

/* Action Badges */
.action-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
}
.action-login { background: #d1fae5; color: #059669; }
.action-logout { background: #f1f5f9; color: #475569; }
.action-add_employee { background: #dbeafe; color: #1d4ed8; }
.action-edit_employee { background: #fef3c7; color: #d97706; }
.action-delete_employee { background: #fee2e2; color: #dc2626; }
.action-process_payroll { background: #fce7f3; color: #db2777; }
.action-run_etl { background: #e0e7ff; color: #4f46e5; }

/* User Cell */
.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, #ff4d6d, #e6395c);
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-avatar-small i {
    color: white;
    font-size: 14px;
}
.user-name {
    font-weight: 600;
    color: var(--text-primary);
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
    .audit-table th, .audit-table td {
        padding: 10px 8px;
    }
}
</style>

<div>
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--text-primary);">
                <i class="fas fa-clipboard-list me-2" style="color: #e6395c;"></i> Audit Trail
            </h4>
            <p class="small mb-0" style="color: var(--text-muted);">Track user activities: Login, Logout, Employee Management, Payroll Processing, ETL</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="audit-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="audit-stat-number"><?php echo number_format($totalLogs); ?></div>
                        <div class="audit-stat-label">Total Activities</div>
                        <div class="audit-stat-sub">All time records</div>
                    </div>
                    <div class="audit-stat-icon">
                        <i class="fas fa-chart-line" style="color: #e6395c;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="audit-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="audit-stat-number"><?php echo number_format($todayLogs); ?></div>
                        <div class="audit-stat-label">Today's Activities</div>
                        <div class="audit-stat-sub">Last 24 hours</div>
                    </div>
                    <div class="audit-stat-icon">
                        <i class="fas fa-calendar-day" style="color: #10b981;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="audit-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="audit-stat-number"><?php echo number_format($uniqueUsers); ?></div>
                        <div class="audit-stat-label">Active Users</div>
                        <div class="audit-stat-sub">Unique users</div>
                    </div>
                    <div class="audit-stat-icon">
                        <i class="fas fa-users" style="color: #4f46e5;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="audit-filter-section">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="audit_logs">
            
            <div class="col-md-3">
                <label class="filter-label">ACTION TYPE</label>
                <select name="action" class="filter-select">
                    <option value="">All Actions</option>
                    <?php foreach($actions as $act): ?>
                    <option value="<?php echo htmlspecialchars($act['action_name']); ?>" <?php echo $action_filter == $act['action_name'] ? 'selected' : ''; ?>>
                        <?php echo str_replace('_', ' ', htmlspecialchars($act['action_name'])); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="filter-label">USER NAME</label>
                <input type="text" name="user" class="filter-input" placeholder="Search user..." value="<?php echo htmlspecialchars($user_filter); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">DATE FROM</label>
                <input type="date" name="date_from" class="filter-input" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">DATE TO</label>
                <input type="date" name="date_to" class="filter-input" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="col-md-2">
                <label class="filter-label">&nbsp;</label>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-filter">Apply</button>
                    <a href="?page=audit_logs" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Export Button -->
    <div class="mb-3 text-end">
        <button onclick="exportToCSV()" class="btn-export">
            <i class="fas fa-download me-2"></i> Export to CSV
        </button>
    </div>

    <!-- Audit Table -->
    <div class="audit-table-container">
        <div class="table-responsive">
            <table class="audit-table" id="auditTable">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                    <tr class="empty-state-row">
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h5>No Activity Logs Found</h5>
                            <p>Try adjusting your filters or perform some actions to generate logs.</p>
                            <div class="mt-3">
                                <small class="text-muted">Tracked activities:</small>
                                <div class="row mt-2">
                                    <div class="col-md-4"><small>✓ Login / Logout</small></div>
                                    <div class="col-md-4"><small>✓ Add Employee</small></div>
                                    <div class="col-md-4"><small>✓ Edit Employee</small></div>
                                    <div class="col-md-4"><small>✓ Delete Employee</small></div>
                                    <div class="col-md-4"><small>✓ Process Payroll</small></div>
                                    <div class="col-md-4"><small>✓ Run ETL</small></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($logs as $log): 
                        $action = $log['action_name'];
                        $actionDisplay = str_replace('_', ' ', $action);
                        
                        $badgeClass = 'action-badge';
                        if($action == 'LOGIN') $badgeClass .= ' action-login';
                        elseif($action == 'LOGOUT') $badgeClass .= ' action-logout';
                        elseif($action == 'ADD_EMPLOYEE' || $action == 'CREATE_EMPLOYEE') $badgeClass .= ' action-add_employee';
                        elseif($action == 'EDIT_EMPLOYEE') $badgeClass .= ' action-edit_employee';
                        elseif($action == 'DELETE_EMPLOYEE') $badgeClass .= ' action-delete_employee';
                        elseif($action == 'PROCESS_PAYROLL') $badgeClass .= ' action-process_payroll';
                        elseif($action == 'RUN_ETL') $badgeClass .= ' action-run_etl';
                        else $badgeClass .= ' action-login';
                    ?>
                    <tr>
                        <td class="timestamp"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="user-name"><?php echo htmlspecialchars($log['username']); ?></div>
                            </div>
                        </td>
                        <td><span class="<?php echo $badgeClass; ?>"><?php echo $actionDisplay; ?></span></td>
                        <td><?php echo htmlspecialchars($log['module_name'] ?? '-'); ?></td>
                        <td><small><?php echo htmlspecialchars($log['details_text'] ?? '-'); ?></small></td>
                        <td><code style="font-size: 11px;"><?php echo $log['ip_address']; ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    let csv = [];
    let rows = document.querySelectorAll('#auditTable tr');
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            let text = cols[j].innerText.replace(/,/g, ';');
            row.push('"' + text + '"');
        }
        csv.push(row.join(','));
    }
    
    let csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    let downloadLink = document.createElement('a');
    downloadLink.download = 'audit_logs_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.csv';
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}
</script>