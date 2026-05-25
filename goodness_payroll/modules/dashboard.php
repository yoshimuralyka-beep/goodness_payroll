<?php
require_once 'config/database.php';

$totalEmployees   = $db->query("SELECT COUNT(*) as total FROM employees")->fetch()['total'];
$activeEmployees  = $db->query("SELECT COUNT(*) as total FROM employees WHERE emp_status = 'active'")->fetch()['total'];
$totalRecords     = $db->query("SELECT COUNT(*) as total FROM payroll_records WHERE payroll_status = 'processed'")->fetch()['total'];
$totalPaid        = $db->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM payroll_records WHERE payroll_status = 'processed'")->fetch()['total'];
$totalDepartments = $db->query("SELECT COUNT(DISTINCT department) as total FROM employees WHERE department IS NOT NULL")->fetch()['total'];
$avgSalary        = $totalEmployees > 0 ? $totalPaid / $totalEmployees : 0;

// Last Payroll
$lastPayrollDate = $db->query("SELECT MAX(processed_at) as last_date FROM payroll_records WHERE payroll_status = 'processed'")->fetch();
$lastDate   = $lastPayrollDate['last_date'];
$lastPeriod = null;
$hasPayroll = false;
if ($lastDate) {
    $hasPayroll = true;
    $lastPeriod = $db->query("SELECT payroll_month, payroll_year FROM payroll_records WHERE payroll_status = 'processed' ORDER BY processed_at DESC LIMIT 1")->fetch();
}

// Department distribution
$deptData     = [];
$deptResult   = $db->query("SELECT COALESCE(department,'Unknown') as department, COUNT(*) as emp_count FROM employees GROUP BY COALESCE(department,'Unknown') ORDER BY emp_count DESC");
$totalEmpCount = 0;
$deptArray    = [];
while ($row = $deptResult->fetch()) {
    $totalEmpCount += $row['emp_count'];
    $deptArray[] = $row;
}
foreach ($deptArray as $row) {
    $percentage = $totalEmpCount > 0 ? round(($row['emp_count'] / $totalEmpCount) * 100, 1) : 0;
    $deptData[] = ['department' => $row['department'], 'emp_count' => $row['emp_count'], 'percentage' => $percentage];
}

// Trend data — ALL processed periods ordered oldest → newest
$trendData   = [];
$trendResult = $db->query("
    SELECT payroll_month, payroll_year, SUM(net_amount) as total_pay
    FROM payroll_records
    WHERE payroll_status = 'processed'
    GROUP BY payroll_year, payroll_month
    ORDER BY payroll_year ASC, payroll_month ASC
");
while ($row = $trendResult->fetch()) {
    $trendData[] = [
        'month_label' => date('M Y', mktime(0, 0, 0, $row['payroll_month'], 1, $row['payroll_year'])),
        'total_pay'   => (float) $row['total_pay'],
    ];
}

$recentEmployees = $db->query("SELECT employee_code, first_name, last_name, department, basic_salary FROM employees ORDER BY employee_id DESC LIMIT 5")->fetchAll();
?>

<style>
.dashboard-stat-card {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 18px;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}
.dashboard-stat-card:hover { transform: translateY(-3px); border-color: #ff4d6d; }
.stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }

/* FIX: stat card text colors use CSS vars */
.dashboard-stat-card .text-muted  { color: var(--text-muted, #6c757d) !important; }
.dashboard-stat-card .text-success { color: #10b981 !important; }
.dashboard-stat-card small         { color: var(--text-secondary, #6c757d); }

.chart-container {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 18px;
    border: 1px solid var(--border-color);
    height: 100%;
}
.chart-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);
}
.chart-title { font-size: 15px; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 8px; }
.chart-badge { background: var(--hover-bg); color: #e6395c; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }

/* single-point hint */
.trend-hint {
    text-align: center;
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 8px;
    font-style: italic;
}

.department-list { margin-top: 15px; max-height: 200px; overflow-y: auto; }
.dept-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.dept-name { color: var(--text-secondary); font-size: 12px; width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dept-count   { font-weight: 600; color: #e6395c; min-width: 35px; text-align: right; font-size: 11px; }
.dept-percent { font-weight: 600; color: #059669; min-width: 45px; text-align: right; font-size: 11px; }
.dept-bar .progress { height: 4px; background: var(--border-color); border-radius: 10px; }
.dept-bar .progress-bar { background: linear-gradient(90deg, #ff4d6d, #e6395c); border-radius: 10px; }
.dept-bar { flex: 1; margin: 0 10px; }

.recent-employee-item { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-color); }
.employee-avatar-small { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; color: white; margin-right: 12px; background: #e6395c; }

/* FIX: recent employee text */
.recent-employee-item .fw-bold { color: var(--text-primary) !important; }
.recent-employee-item .text-muted { color: var(--text-muted, #6c757d) !important; }
.recent-employee-item .text-success { color: #10b981 !important; }

.department-table-container { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border-color); overflow: hidden; }
.department-table { width: 100%; border-collapse: collapse; }
.department-table th { background: var(--table-header-bg); padding: 15px 12px; font-weight: 600; font-size: 13px; color: #e6395c; text-align: center; }
.department-table td { padding: 14px 12px; border-bottom: 1px solid var(--border-color); font-size: 13px; color: var(--text-secondary); vertical-align: middle; text-align: center; }
.department-table td:first-child, .department-table th:first-child { width: 15%; }
.department-table td:nth-child(2), .department-table th:nth-child(2) { width: 40%; text-align: left; }
.department-table td:nth-child(3), .department-table th:nth-child(3) { width: 15%; }
.department-table td:nth-child(4), .department-table th:nth-child(4) { width: 30%; }
.department-table tr:hover td { background: var(--hover-bg); }
.rank-badge { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 30px; font-weight: 700; font-size: 14px; }
.rank-1     { background: linear-gradient(135deg,#fbbf24,#f59e0b); color: white; box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
.rank-2     { background: linear-gradient(135deg,#94a3b8,#64748b); color: white; }
.rank-3     { background: linear-gradient(135deg,#cd7f32,#b87333); color: white; }

/* FIX: rank-other uses CSS var so it's visible in dark mode */
.rank-other { background: var(--hover-bg); color: var(--text-secondary); }

.department-name { font-weight: 700; color: var(--text-primary); text-align: left; }
.progress-wrapper { display: flex; align-items: center; gap: 12px; }
.percentage-value { min-width: 45px; font-weight: 700; color: #e6395c; font-size: 14px; }
.progress-bar-container { flex: 1; height: 8px; background: var(--border-color); border-radius: 10px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, #ff4d6d, #e6395c); border-radius: 10px; transition: width 0.3s; }

/* FIX: Last Payroll card text */
.last-payroll-date  { color: var(--text-primary) !important; }
.last-payroll-muted { color: var(--text-muted, #6c757d) !important; }

/* FIX: No-data / empty state text */
.empty-state-text { color: var(--text-muted, #94a3b8) !important; }

/* FIX: "All time" small tag inside payroll records card */
.dashboard-stat-card small.all-time { color: var(--text-secondary, #6c757d); }
</style>

<div>
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="dashboard-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Total Employees</div>
                        <h2 class="fw-bold mb-0" style="color:var(--text-primary);"><?php echo $totalEmployees; ?></h2>
                        <small class="text-success"><?php echo $activeEmployees; ?> active</small>
                    </div>
                    <div class="stat-icon" style="background:#fff0f3;"><i class="fas fa-users fa-xl" style="color:#e6395c;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="dashboard-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Departments</div>
                        <h2 class="fw-bold mb-0" style="color:var(--text-primary);"><?php echo $totalDepartments; ?></h2>
                        <small style="color:var(--text-muted);">Across company</small>
                    </div>
                    <div class="stat-icon" style="background:#e0e7ff;"><i class="fas fa-building fa-xl" style="color:#4f46e5;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="dashboard-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Payroll Records</div>
                        <h2 class="fw-bold mb-0" style="color:var(--text-primary);"><?php echo $totalRecords; ?></h2>
                        <small style="color:var(--text-muted);">All time</small>
                    </div>
                    <div class="stat-icon" style="background:#d1fae5;"><i class="fas fa-file-invoice-dollar fa-xl" style="color:#059669;"></i></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="dashboard-stat-card">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Total Paid</div>
                        <h5 class="fw-bold mb-0" style="color:var(--text-primary);">₱<?php echo number_format($totalPaid,2); ?></h5>
                        <small style="color:var(--text-muted);">₱<?php echo number_format($avgSalary,2); ?> avg</small>
                    </div>
                    <div class="stat-icon" style="background:#fef3c7;"><i class="fas fa-coins fa-xl" style="color:#d97706;"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Payroll Trend -->
        <div class="col-md-7 mb-3">
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title"><i class="fas fa-chart-line" style="color:#e6395c;"></i> Payroll Trend</div>
                    <span class="chart-badge">Past to Present</span>
                </div>
                <?php if(empty($trendData)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-3x mb-3" style="color:#ffe4e9;"></i>
                    <p class="empty-state-text small mb-0">No payroll data yet. Process your first payroll to see the trend.</p>
                </div>
                <?php else: ?>
                <!-- fixed height wrapper so maintainAspectRatio:false works -->
                <div style="position:relative; height:260px;">
                    <canvas id="trendChart"></canvas>
                </div>
                <?php if(count($trendData) === 1): ?>
                <p class="trend-hint">Only 1 period processed — trend will appear as more payrolls are run.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Department Distribution -->
        <div class="col-md-5 mb-3">
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title"><i class="fas fa-chart-pie" style="color:#e6395c;"></i> Department Distribution</div>
                    <span class="chart-badge">By Employee Count</span>
                </div>
                <div class="row">
                    <div class="col-6"><canvas id="deptChart" style="height:150px;width:100%;"></canvas></div>
                    <div class="col-6">
                        <div class="department-list">
                            <?php foreach($deptData as $dept): ?>
                            <div class="dept-item">
                                <span class="dept-name"><?php echo htmlspecialchars(substr($dept['department'],0,12)); ?></span>
                                <div class="dept-bar">
                                    <div class="progress">
                                        <div class="progress-bar" style="width:<?php echo $dept['percentage']; ?>%;"></div>
                                    </div>
                                </div>
                                <span class="dept-count"><?php echo $dept['emp_count']; ?></span>
                                <span class="dept-percent"><?php echo $dept['percentage']; ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Performance Table -->
    <div class="department-table-container mb-4">
        <div class="table-responsive">
            <table class="department-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Percentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rank = 1; foreach($deptData as $dept): ?>
                    <tr>
                        <td>
                            <div class="rank-badge rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                                <?php echo $rank; ?>
                            </div>
                        </td>
                        <td class="department-name"><?php echo htmlspecialchars($dept['department']); ?></td>
                        <td><?php echo $dept['emp_count']; ?></td>
                        <td>
                            <div class="progress-wrapper">
                                <span class="percentage-value"><?php echo $dept['percentage']; ?>%</span>
                                <div class="progress-bar-container">
                                    <div class="progress-fill" style="width:<?php echo $dept['percentage']; ?>%;"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Last Payroll & Recent Hires -->
    <div class="row">
        <div class="col-md-5 mb-3">
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title"><i class="fas fa-clock" style="color:#e6395c;"></i> Last Payroll</div>
                </div>
                <?php if($hasPayroll && $lastDate && $lastPeriod): ?>
                <div class="text-center py-3">
                    <i class="fas fa-calendar-check fa-3x mb-2" style="color:#10b981;"></i>
                    <h6 class="last-payroll-date mb-1"><?php echo date('F d, Y', strtotime($lastDate)); ?></h6>
                    <p class="small last-payroll-muted mb-0">Period: <?php echo date('F Y', mktime(0,0,0,$lastPeriod['payroll_month'],1,$lastPeriod['payroll_year'])); ?></p>
                    <p class="small last-payroll-muted">Last payroll processing date</p>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-check fa-3x mb-2" style="color:#cbd5e1;"></i>
                    <h6 class="mb-1 empty-state-text">No payroll records yet</h6>
                    <a href="?page=process_payroll" style="display:inline-block;padding:6px 16px;background:linear-gradient(135deg,#ff4d6d,#e6395c);color:white;border-radius:20px;text-decoration:none;font-size:12px;">Process Payroll</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-7 mb-3">
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title"><i class="fas fa-user-plus" style="color:#e6395c;"></i> Recent Hires</div>
                    <span class="chart-badge">Latest 5</span>
                </div>
                <?php foreach($recentEmployees as $emp): $initial = strtoupper(substr($emp['first_name'],0,1)); ?>
                <div class="recent-employee-item">
                    <div class="employee-avatar-small"><?php echo $initial; ?></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold small" style="color:var(--text-primary);"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                        <div class="small" style="color:var(--text-muted);"><?php echo htmlspecialchars($emp['employee_code']); ?> • <?php echo htmlspecialchars($emp['department']); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold small" style="color:#10b981;">₱<?php echo number_format($emp['basic_salary'],2); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Detect dark mode for Chart.js tick/label colors
var isDark     = document.documentElement.classList.contains('dark')
              || document.body.classList.contains('dark-mode')
              || document.body.getAttribute('data-theme') === 'dark'
              || window.matchMedia('(prefers-color-scheme: dark)').matches;
var tickColor  = isDark ? '#cbd5e1' : '#6b7280';
var gridColor  = isDark ? 'rgba(230,57,92,0.12)' : 'rgba(230,57,92,0.08)';

// ── Trend Chart ─────────────────────────────────────────────────
<?php if(!empty($trendData)): ?>
(function() {
    var ctx = document.getElementById('trendChart').getContext('2d');

    var trendLabels = <?php echo json_encode(array_column($trendData, 'month_label')); ?>;
    var trendValues = <?php echo json_encode(array_column($trendData, 'total_pay')); ?>;

    // Build gradient fill
    var gradient = ctx.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(230,57,92,0.22)');
    gradient.addColorStop(1, 'rgba(230,57,92,0.01)');

    // ── Y-axis range: tight band around the actual data ──────────
    var minVal  = Math.min.apply(null, trendValues);
    var maxVal  = Math.max.apply(null, trendValues);
    var spread  = maxVal - minVal;

    var yPad;
    if (trendValues.length <= 1) {
        yPad = maxVal * 0.15 || 50000;
    } else {
        yPad = spread > 0 ? spread * 0.25 : maxVal * 0.05 || 10000;
    }

    var yMin = Math.max(0, Math.floor((minVal - yPad) / 10000) * 10000);
    var yMax = Math.ceil((maxVal  + yPad) / 10000) * 10000;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Total Payroll (₱)',
                data: trendValues,
                borderColor: '#e6395c',
                backgroundColor: gradient,
                borderWidth: 2.5,
                fill: true,
                tension: trendValues.length > 1 ? 0.4 : 0,
                pointBackgroundColor: '#e6395c',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius:      trendValues.length <= 1 ? 8 : 5,
                pointHoverRadius: 9
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { left: 4, right: 16, top: 12, bottom: 4 } },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        font: { size: 11 },
                        color: tickColor   // FIX: legend label color
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ₱' + Number(context.raw).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    min: yMin,
                    max: yMax,
                    ticks: {
                        maxTicksLimit: 7,
                        font: { size: 10 },
                        color: tickColor,  // FIX: y-axis tick color
                        callback: function(value) {
                            if (value >= 1000000) return '₱' + (value / 1000000).toFixed(1) + 'M';
                            if (value >= 1000)    return '₱' + (value / 1000).toFixed(0) + 'K';
                            return '₱' + value;
                        }
                    },
                    grid: { color: gridColor }
                },
                x: {
                    ticks: {
                        font: { size: 10 },
                        color: tickColor   // FIX: x-axis tick color
                    },
                    grid: { display: false }
                }
            }
        }
    });
})();
<?php endif; ?>

// ── Department Doughnut Chart ────────────────────────────────────
var deptLabels     = <?php echo json_encode(array_column($deptData, 'department')); ?>;
var deptValues     = <?php echo json_encode(array_column($deptData, 'emp_count')); ?>;
var totalEmployees = <?php echo $totalEmpCount; ?>;

new Chart(document.getElementById('deptChart'), {
    type: 'doughnut',
    data: {
        labels: deptLabels,
        datasets: [{
            data: deptValues,
            backgroundColor: ['#e6395c','#ff4d6d','#f4a261','#2a9d8f','#9b5de5','#00b4d8','#6c757d','#20c997'],
            borderWidth: 0,
            cutout: '55%',
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        var val  = context.raw || 0;
                        var pct  = totalEmployees > 0 ? ((val / totalEmployees) * 100).toFixed(1) : 0;
                        return (context.label || '') + ': ' + val + ' employee(s) (' + pct + '%)';
                    }
                }
            }
        }
    }
});
</script>