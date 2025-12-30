<?php
require_once '../../includes/init.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build WHERE clause with safe escaping
$where_conditions = [];

// Add date range
$date_from_escaped = mysqli_real_escape_string($conn, $date_from);
$date_to_escaped = mysqli_real_escape_string($conn, $date_to);
$where_conditions[] = "a.created_at BETWEEN '$date_from_escaped' AND '$date_to_escaped 23:59:59'";

// Add user filter
if ($user_id > 0) {
    $user_id_escaped = (int)$user_id; // Already integer, but cast for safety
    $where_conditions[] = "a.user_id = $user_id_escaped";
}

// Add action filter
if (!empty($action_filter)) {
    $action_filter_escaped = mysqli_real_escape_string($conn, $action_filter);
    $where_conditions[] = "a.action_type = '$action_filter_escaped'";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total logs
$count_sql = "SELECT COUNT(*) as total FROM user_activities a $where_clause";
$count_result = mysqli_query($conn, $count_sql);
$count_row = mysqli_fetch_assoc($count_result);
$total_logs = $count_row ? $count_row['total'] : 0;

// Pagination
$items_per_page = get_setting('items_per_page', 50);
$pagination = paginate($page, $items_per_page, $total_logs);

// Get logs
$sql = "SELECT a.*, u.name as user_name, u.email, u.role 
        FROM user_activities a
        JOIN users u ON a.user_id = u.user_id
        $where_clause 
        ORDER BY a.created_at DESC 
        LIMIT {$pagination['offset']}, {$pagination['limit']}";

$logs = mysqli_query($conn, $sql);

// Get unique actions for filter
$actions_sql = "SELECT DISTINCT action_type as action FROM user_activities ORDER BY action_type";
$actions_result = mysqli_query($conn, $actions_sql);

// Get recent users for filter
$users_sql = "SELECT user_id, name, email FROM users ORDER BY name";
$users_result = mysqli_query($conn, $users_sql);

// Get statistics - use escaped dates here too
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT DATE(created_at)) as days_covered,
        MIN(created_at) as earliest,
        MAX(created_at) as latest
    FROM user_activities
    WHERE created_at BETWEEN '$date_from_escaped' AND '$date_to_escaped 23:59:59'
";
$stats_result = mysqli_query($conn, $stats_sql);
$stats = mysqli_fetch_assoc($stats_result);

// Helper function to safely escape HTML
function safe_html($value) {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 12px;
            font-family: monospace;
        }
        
        .log-details .detail-row {
            display: flex;
            margin-bottom: 5px;
        }
        
        .log-details .detail-label {
            width: 100px;
            color: var(--gray);
        }
        
        .log-details .detail-value {
            flex: 1;
            color: var(--dark);
            word-break: break-all;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-info {
            background: #D1ECF1;
            color: #0C5460;
        }
        
        .badge-success {
            background: #D4EDDA;
            color: #155724;
        }
        
        .badge-warning {
            background: #FFF3CD;
            color: #856404;
        }
        
        .badge-danger {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .action-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .view-details {
            color: var(--primary);
            cursor: pointer;
            text-decoration: underline;
            font-size: 12px;
        }
        
        .export-btn {
            padding: 8px 15px;
            background: var(--light);
            color: var(--dark);
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .date-inputs {
            grid-column: span 2;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <h1>Audit Logs</h1>
                    <p>System activity and user actions</p>
                </div>
                <div class="header-right">
                    <a href="audit-logs.php?export=csv&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
                       class="export-btn">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>
            </header>

            <div class="admin-content">
                <div class="stats-cards">
                    <div class="stat-mini">
                        <h4><?php echo $stats['total']; ?></h4>
                        <p>Total Logs</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['unique_users']; ?></h4>
                        <p>Unique Users</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo $stats['days_covered']; ?></h4>
                        <p>Days Covered</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo format_date($stats['earliest'], 'M j'); ?></h4>
                        <p>Earliest Log</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo format_date($stats['latest'], 'M j'); ?></h4>
                        <p>Latest Log</p>
                    </div>
                </div>

                <div class="filters">
                    <form method="GET" class="filter-form">
                        <div class="date-inputs">
                            <div class="form-group">
                                <label for="date_from">From Date</label>
                                <input type="date" id="date_from" name="date_from" 
                                       class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_to">To Date</label>
                                <input type="date" id="date_to" name="date_to" 
                                       class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_id">User</label>
                            <select id="user_id" name="user_id" class="form-control">
                                <option value="">All Users</option>
                                <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                    <option value="<?php echo $user['user_id']; ?>" 
                                        <?php echo $user_id == $user['user_id'] ? 'selected' : ''; ?>>
                                        <?php 
                                        $user_display = '';
                                        if (!empty($user['name'])) {
                                            $user_display .= $user['name'];
                                        }
                                        if (!empty($user['email'])) {
                                            $user_display .= $user_display ? ' (' . $user['email'] . ')' : $user['email'];
                                        }
                                        echo safe_html($user_display ?: 'User #' . $user['user_id']);
                                        ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="action">Action</label>
                            <select id="action" name="action" class="form-control">
                                <option value="">All Actions</option>
                                <?php while ($action = mysqli_fetch_assoc($actions_result)): ?>
                                    <option value="<?php echo $action['action']; ?>" 
                                        <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', safe_html($action['action']))); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="audit-logs.php" class="btn" style="margin-top: 5px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($logs) > 0): ?>
                                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                                        <tr>
                                            <td>
                                                <?php echo format_date($log['created_at']); ?>
                                                <br><small><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo safe_html($log['user_name']); ?></strong>
                                                <br><small><?php echo safe_html($log['email']); ?></small>
                                                <br><span class="badge badge-info"><?php echo ucfirst(safe_html($log['role'])); ?></span>
                                            </td>
                                            <td class="action-cell">
                                                <span class="badge 
                                                    <?php 
                                                    if (strpos($log['action_type'], 'delete') !== false) echo 'badge-danger';
                                                    elseif (strpos($log['action_type'], 'create') !== false || strpos($log['action_type'], 'add') !== false) echo 'badge-success';
                                                    elseif (strpos($log['action_type'], 'update') !== false || strpos($log['action_type'], 'edit') !== false) echo 'badge-warning';
                                                    else echo 'badge-info';
                                                    ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', safe_html($log['action_type']))); ?>
                                                </span>
                                            </td>
                                            <td><?php echo safe_html($log['description']); ?></td>
                                            <td><?php echo safe_html($log['ip_address']); ?></td>
                                            <td>
                                                <span class="view-details" onclick="toggleDetails(<?php echo $log['activity_id']; ?>)">
                                                    View Details
                                                </span>
                                            </td>
                                        </tr>
                                        <tr id="details-<?php echo $log['activity_id']; ?>" style="display: none;">
                                            <td colspan="6">
                                                <div class="log-details">
                                                    <div class="detail-row">
                                                        <div class="detail-label">Activity ID:</div>
                                                        <div class="detail-value"><?php echo $log['activity_id']; ?></div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label">User Agent:</div>
                                                        <div class="detail-value"><?php echo safe_html($log['user_agent']); ?></div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label">Full Timestamp:</div>
                                                        <div class="detail-value"><?php echo $log['created_at']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="empty-state">
                                                <i class="fas fa-history"></i>
                                                <p>No audit logs found for the selected period</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $user_id ? '&user_id=' . $user_id : ''; ?><?php echo $action_filter ? '&action=' . $action_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $user_id ? '&user_id=' . $user_id : ''; ?><?php echo $action_filter ? '&action=' . $action_filter : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $user_id ? '&user_id=' . $user_id : ''; ?><?php echo $action_filter ? '&action=' . $action_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleDetails(activityId) {
            const detailsRow = document.getElementById(`details-${activityId}`);
            const isVisible = detailsRow.style.display === 'table-row';
            detailsRow.style.display = isVisible ? 'none' : 'table-row';
        }
        
        // Auto-refresh every 60 seconds if on first page
        let currentPage = <?php echo $page; ?>;
        if (currentPage === 1) {
            setInterval(() => {
                if (!document.hidden) {
                    location.reload();
                }
            }, 60000);
        }
    </script>
</body>
</html>