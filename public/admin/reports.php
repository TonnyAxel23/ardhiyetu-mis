<?php
require_once '../../includes/init.php';
require_admin();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';

// Get statistics for the date range
$stats_sql = "
    SELECT 
        -- User statistics
        (SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?) as new_users,
        (SELECT COUNT(*) FROM users WHERE last_login BETWEEN ? AND ?) as active_users,
        
        -- Land statistics
        (SELECT COUNT(*) FROM land_records WHERE registered_at BETWEEN ? AND ?) as new_lands,
        (SELECT SUM(size) FROM land_records WHERE registered_at BETWEEN ? AND ?) as new_land_size,
        
        -- Transfer statistics
        (SELECT COUNT(*) FROM ownership_transfers WHERE submitted_at BETWEEN ? AND ?) as new_transfers,
        (SELECT COUNT(*) FROM ownership_transfers WHERE status = 'approved' AND submitted_at BETWEEN ? AND ?) as approved_transfers,
        (SELECT COUNT(*) FROM ownership_transfers WHERE status = 'rejected' AND submitted_at BETWEEN ? AND ?) as rejected_transfers,
        
        -- Activity statistics
        (SELECT COUNT(*) FROM user_activities WHERE created_at BETWEEN ? AND ?) as activities
";

$stmt = mysqli_prepare($conn, $stats_sql);
$params = array_fill(0, 16, $start_date);
for ($i = 1; $i <= 8; $i++) {
    $params[2*$i-1] = $end_date;
}
$types = str_repeat('s', 16);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);

// Get monthly data for charts
$monthly_sql = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
";
$monthly_users = mysqli_query($conn, $monthly_sql);

$monthly_lands_sql = "
    SELECT 
        DATE_FORMAT(registered_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM land_records 
    WHERE registered_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(registered_at, '%Y-%m')
    ORDER BY month
";
$monthly_lands = mysqli_query($conn, $monthly_lands_sql);

$monthly_transfers_sql = "
    SELECT 
        DATE_FORMAT(submitted_at, '%Y-%m') as month,
        COUNT(*) as count
    FROM ownership_transfers 
    WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
    ORDER BY month
";
$monthly_transfers = mysqli_query($conn, $monthly_transfers_sql);

// Get transfer status distribution
$transfer_status_sql = "
    SELECT 
        status,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM ownership_transfers), 2) as percentage
    FROM ownership_transfers
    GROUP BY status
";
$transfer_status = mysqli_query($conn, $transfer_status_sql);

// Get user role distribution
$user_role_sql = "
    SELECT 
        role,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users), 2) as percentage
    FROM users
    GROUP BY role
";
$user_role = mysqli_query($conn, $user_role_sql);

// Get top land owners
$top_owners_sql = "
    SELECT 
        u.name,
        u.email,
        COUNT(l.record_id) as land_count,
        SUM(l.size) as total_size
    FROM users u
    LEFT JOIN land_records l ON u.user_id = l.owner_id
    GROUP BY u.user_id
    HAVING land_count > 0
    ORDER BY land_count DESC
    LIMIT 10
";
$top_owners = mysqli_query($conn, $top_owners_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css">
    <style>
        .report-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .date-range {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .date-inputs {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-inputs input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .report-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .report-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .report-card h3 {
            font-size: 28px;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .report-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .report-card .trend {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            font-size: 12px;
        }
        
        .trend.up {
            color: var(--success);
        }
        
        .trend.down {
            color: var(--danger);
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }
        
        .chart-container h3 {
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .export-btn {
            padding: 10px 20px;
            background: var(--light);
            color: var(--dark);
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .export-btn:hover {
            background: #f8f9fa;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Analytics & Reports</h1>
                    <p>System statistics and analytics</p>
                </div>
                <div class="header-right">
                    <div class="export-options">
                        <a href="reports.php?export=pdf" class="export-btn" target="_blank">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="reports.php?export=excel" class="export-btn">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="reports.php?export=print" class="export-btn" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </div>
                </div>
            </header>

            <div class="admin-content">
                <?php if (isset($_SESSION['flash'])): ?>
                    <?php foreach ($_SESSION['flash'] as $type => $message): ?>
                        <div class="alert alert-<?php echo $type; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <div class="report-header">
                    <form method="GET" class="filter-form">
                        <div class="date-range">
                            <div class="date-inputs">
                                <div>
                                    <label for="start_date" style="display: block; margin-bottom: 5px; font-size: 12px;">From Date</label>
                                    <input type="date" id="start_date" name="start_date" 
                                           value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label for="end_date" style="display: block; margin-bottom: 5px; font-size: 12px;">To Date</label>
                                    <input type="date" id="end_date" name="end_date" 
                                           value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="report_type" style="display: block; margin-bottom: 5px; font-size: 12px;">Report Type</label>
                                <select id="report_type" name="report_type" class="form-control" style="width: 200px;">
                                    <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                                    <option value="users" <?php echo $report_type === 'users' ? 'selected' : ''; ?>>User Analytics</option>
                                    <option value="lands" <?php echo $report_type === 'lands' ? 'selected' : ''; ?>>Land Analytics</option>
                                    <option value="transfers" <?php echo $report_type === 'transfers' ? 'selected' : ''; ?>>Transfer Analytics</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Generate
                            </button>
                        </div>
                    </form>
                    
                    <div class="report-period">
                        <p>Report Period: <?php echo date('F j, Y', strtotime($start_date)); ?> to <?php echo date('F j, Y', strtotime($end_date)); ?></p>
                    </div>
                </div>

                <?php if ($report_type === 'overview'): ?>
                    <div class="report-cards">
                        <div class="report-card">
                            <h3><?php echo $stats['new_users']; ?></h3>
                            <p>New Users</p>
                            <div class="trend up">
                                <i class="fas fa-arrow-up"></i>
                                <span>12% from last period</span>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <h3><?php echo $stats['new_lands']; ?></h3>
                            <p>New Land Records</p>
                            <div class="trend up">
                                <i class="fas fa-arrow-up"></i>
                                <span>8% from last period</span>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <h3><?php echo $stats['new_transfers']; ?></h3>
                            <p>New Transfer Requests</p>
                            <div class="trend up">
                                <i class="fas fa-arrow-up"></i>
                                <span>15% from last period</span>
                            </div>
                        </div>
                        
                        <div class="report-card">
                            <h3><?php echo $stats['active_users']; ?></h3>
                            <p>Active Users</p>
                            <div class="trend up">
                                <i class="fas fa-arrow-up"></i>
                                <span>5% from last period</span>
                            </div>
                        </div>
                    </div>

                    <div class="chart-grid">
                        <div class="chart-container">
                            <h3>User Registrations (Last 12 Months)</h3>
                            <canvas id="usersChart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <h3>Land Registrations (Last 12 Months)</h3>
                            <canvas id="landsChart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <h3>Transfer Status Distribution</h3>
                            <canvas id="transferChart"></canvas>
                        </div>
                        
                        <div class="chart-container">
                            <h3>User Role Distribution</h3>
                            <canvas id="roleChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-container">
                        <h3>Top 10 Land Owners</h3>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Owner</th>
                                        <th>Email</th>
                                        <th>Number of Lands</th>
                                        <th>Total Size (ha)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php if (mysqli_num_rows($top_owners) > 0): ?>
                                        <?php while ($owner = mysqli_fetch_assoc($top_owners)): ?>
                                            <tr>
                                                <td><?php echo $rank++; ?></td>
                                                <td><?php echo htmlspecialchars($owner['name']); ?></td>
                                                <td><?php echo htmlspecialchars($owner['email']); ?></td>
                                                <td><?php echo $owner['land_count']; ?></td>
                                                <td><?php echo number_format($owner['total_size'], 2); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($report_type === 'users'): ?>
                    <!-- User Analytics Report -->
                    <div class="chart-container">
                        <h3>User Growth Over Time</h3>
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                    
                <?php elseif ($report_type === 'lands'): ?>
                    <!-- Land Analytics Report -->
                    <div class="chart-container">
                        <h3>Land Registration Trends</h3>
                        <canvas id="landTrendsChart"></canvas>
                    </div>
                    
                <?php elseif ($report_type === 'transfers'): ?>
                    <!-- Transfer Analytics Report -->
                    <div class="chart-container">
                        <h3>Transfer Request Trends</h3>
                        <canvas id="transferTrendsChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Prepare data for charts
        const monthlyUsers = <?php 
            $data = [];
            while ($row = mysqli_fetch_assoc($monthly_users)) {
                $data[$row['month']] = $row['count'];
            }
            echo json_encode($data);
        ?>;
        
        const monthlyLands = <?php 
            $data = [];
            while ($row = mysqli_fetch_assoc($monthly_lands)) {
                $data[$row['month']] = $row['count'];
            }
            echo json_encode($data);
        ?>;
        
        const transferStatus = <?php 
            $data = [];
            $labels = [];
            while ($row = mysqli_fetch_assoc($transfer_status)) {
                $data[] = $row['count'];
                $labels[] = ucfirst(str_replace('_', ' ', $row['status']));
            }
            echo json_encode(['data' => $data, 'labels' => $labels]);
        ?>;
        
        const userRoles = <?php 
            $data = [];
            $labels = [];
            while ($row = mysqli_fetch_assoc($user_role)) {
                $data[] = $row['count'];
                $labels[] = ucfirst($row['role']);
            }
            echo json_encode(['data' => $data, 'labels' => $labels]);
        ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // User Registration Chart
            const usersCtx = document.getElementById('usersChart').getContext('2d');
            const usersChart = new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(monthlyUsers),
                    datasets: [{
                        label: 'User Registrations',
                        data: Object.values(monthlyUsers),
                        borderColor: '#2E86AB',
                        backgroundColor: 'rgba(46, 134, 171, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Land Registration Chart
            const landsCtx = document.getElementById('landsChart').getContext('2d');
            const landsChart = new Chart(landsCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(monthlyLands),
                    datasets: [{
                        label: 'Land Registrations',
                        data: Object.values(monthlyLands),
                        backgroundColor: '#F4A261',
                        borderColor: '#E76F51',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            
            // Transfer Status Chart
            const transferCtx = document.getElementById('transferChart').getContext('2d');
            const transferChart = new Chart(transferCtx, {
                type: 'doughnut',
                data: {
                    labels: transferStatus.labels,
                    datasets: [{
                        data: transferStatus.data,
                        backgroundColor: [
                            '#FFC107', // Submitted - Yellow
                            '#17A2B8', // Under Review - Teal
                            '#28A745', // Approved - Green
                            '#DC3545'  // Rejected - Red
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.parsed / total) * 100);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // User Role Chart
            const roleCtx = document.getElementById('roleChart').getContext('2d');
            const roleChart = new Chart(roleCtx, {
                type: 'pie',
                data: {
                    labels: userRoles.labels,
                    datasets: [{
                        data: userRoles.data,
                        backgroundColor: [
                            '#28A745', // Admin - Green
                            '#17A2B8', // Officer - Teal
                            '#6C757D'  // User - Gray
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.parsed / total) * 100);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>