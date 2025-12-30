<?php
require_once '../../includes/init.php';
require_admin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';
$source_filter = isset($_GET['source']) ? $_GET['source'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-1 day'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Handle log clearing
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    $sql = "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if (mysqli_query($conn, $sql)) {
        log_activity($_SESSION['user_id'], 'log_clear', 'Cleared system logs older than 30 days');
        flash_message('success', 'System logs cleared successfully.');
    } else {
        flash_message('error', 'Failed to clear system logs.');
    }
    header('Location: system-logs.php');
    exit();
}

// Build query
$where = ["created_at BETWEEN ? AND ?"];
$params = [$date_from, $date_to . ' 23:59:59'];
$types = 'ss';

if (!empty($level_filter)) {
    $where[] = "level = ?";
    $params[] = $level_filter;
    $types .= 's';
}

if (!empty($source_filter)) {
    $where[] = "source = ?";
    $params[] = $source_filter;
    $types .= 's';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Helper function for prepared statements with references
function execute_prepared_stmt($conn, $sql, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    
    if (!empty($params)) {
        // Create references for bind_param
        $bind_params = array();
        $bind_params[] = &$types;
        foreach ($params as $key => $value) {
            $bind_params[] = &$params[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge(array($stmt), $bind_params));
    }
    
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

// Count total logs
$count_sql = "SELECT COUNT(*) as total FROM system_logs $where_clause";
$count_result = execute_prepared_stmt($conn, $count_sql, $params, $types);
if ($count_result) {
    $total_data = mysqli_fetch_assoc($count_result);
    $total_logs = $total_data ? $total_data['total'] : 0;
} else {
    $total_logs = 0;
}

// Pagination
$items_per_page = get_setting('items_per_page', 50);
$pagination = paginate($page, $items_per_page, $total_logs);

// Get logs
$sql = "SELECT * FROM system_logs 
        $where_clause 
        ORDER BY created_at DESC 
        LIMIT ?, ?";

$types .= 'ii';
$params[] = $pagination['offset'];
$params[] = $pagination['limit'];

$logs = execute_prepared_stmt($conn, $sql, $params, $types);
if (!$logs) {
    flash_message('error', 'Failed to fetch logs: ' . mysqli_error($conn));
    $logs = [];
}

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN level = 'error' THEN 1 ELSE 0 END) as errors,
        SUM(CASE WHEN level = 'warning' THEN 1 ELSE 0 END) as warnings,
        SUM(CASE WHEN level = 'critical' THEN 1 ELSE 0 END) as critical,
        COUNT(DISTINCT source) as unique_sources,
        MIN(created_at) as earliest,
        MAX(created_at) as latest
    FROM system_logs
    WHERE created_at BETWEEN ? AND ?
";
$stats_result = execute_prepared_stmt($conn, $stats_sql, [$date_from, $date_to . ' 23:59:59'], 'ss');
if ($stats_result) {
    $stats_data = mysqli_fetch_assoc($stats_result);
    $stats = $stats_data ? $stats_data : [
        'total' => 0,
        'errors' => 0,
        'warnings' => 0,
        'critical' => 0,
        'unique_sources' => 0,
        'earliest' => null,
        'latest' => null
    ];
} else {
    $stats = [
        'total' => 0,
        'errors' => 0,
        'warnings' => 0,
        'critical' => 0,
        'unique_sources' => 0,
        'earliest' => null,
        'latest' => null
    ];
}

// Get unique sources
$sources_sql = "SELECT DISTINCT source FROM system_logs ORDER BY source";
$sources_result = mysqli_query($conn, $sources_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-level {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .level-info {
            background: #D1ECF1;
            color: #0C5460;
        }
        
        .level-warning {
            background: #FFF3CD;
            color: #856404;
        }
        
        .level-error {
            background: #F8D7DA;
            color: #721C24;
        }
        
        .level-critical {
            background: #721C24;
            color: white;
        }
        
        .message-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .details-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 1001;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            display: none;
        }
        
        .details-popup.active {
            display: block;
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .details-content {
            font-family: monospace;
            white-space: pre-wrap;
            word-break: break-all;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }
        
        .overlay.active {
            display: block;
        }
        
        .danger-zone {
            background: #F8D7DA;
            border: 1px solid #F5C6CB;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .danger-zone h3 {
            color: #721C24;
            margin-bottom: 10px;
        }
        
        .danger-zone p {
            color: #721C24;
            margin-bottom: 15px;
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
                    <h1>System Logs</h1>
                    <p>Application and server logs</p>
                </div>
                <div class="header-right">
                    <div class="system-status">
                        <span class="status-indicator <?php echo isset($stats['critical']) && $stats['critical'] > 0 ? 'inactive' : 'active'; ?>"></span>
                        <span><?php echo isset($stats['critical']) && $stats['critical'] > 0 ? 'System Issues Detected' : 'System Healthy'; ?></span>
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

                <div class="danger-zone">
                    <h3><i class="fas fa-exclamation-triangle"></i> Warning</h3>
                    <p>System logs contain sensitive information. Clear logs older than 30 days to maintain performance.</p>
                    <a href="system-logs.php?action=clear" class="btn" 
                       onclick="return confirm('This will permanently delete system logs older than 30 days. Continue?')"
                       style="background: var(--danger); color: white;">
                        <i class="fas fa-trash"></i> Clear Old Logs
                    </a>
                </div>

                <div class="stats-cards">
                    <div class="stat-mini">
                        <h4><?php echo isset($stats['total']) ? $stats['total'] : 0; ?></h4>
                        <p>Total Logs</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo isset($stats['errors']) ? $stats['errors'] : 0; ?></h4>
                        <p>Errors</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo isset($stats['warnings']) ? $stats['warnings'] : 0; ?></h4>
                        <p>Warnings</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo isset($stats['critical']) ? $stats['critical'] : 0; ?></h4>
                        <p>Critical</p>
                    </div>
                    <div class="stat-mini">
                        <h4><?php echo isset($stats['unique_sources']) ? $stats['unique_sources'] : 0; ?></h4>
                        <p>Sources</p>
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
                            <label for="level">Log Level</label>
                            <select id="level" name="level" class="form-control">
                                <option value="">All Levels</option>
                                <option value="info" <?php echo $level_filter === 'info' ? 'selected' : ''; ?>>Info</option>
                                <option value="warning" <?php echo $level_filter === 'warning' ? 'selected' : ''; ?>>Warning</option>
                                <option value="error" <?php echo $level_filter === 'error' ? 'selected' : ''; ?>>Error</option>
                                <option value="critical" <?php echo $level_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="source">Source</label>
                            <select id="source" name="source" class="form-control">
                                <option value="">All Sources</option>
                                <?php 
                                if ($sources_result && mysqli_num_rows($sources_result) > 0): 
                                    mysqli_data_seek($sources_result, 0); // Reset pointer
                                    while ($source = mysqli_fetch_assoc($sources_result)): ?>
                                        <option value="<?php echo $source['source']; ?>" 
                                            <?php echo $source_filter === $source['source'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($source['source']); ?>
                                        </option>
                                    <?php endwhile; 
                                endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="system-logs.php" class="btn" style="margin-top: 5px;">
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
                                    <th>Level</th>
                                    <th>Source</th>
                                    <th>Message</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (is_object($logs) && mysqli_num_rows($logs) > 0): ?>
                                    <?php while ($log = mysqli_fetch_assoc($logs)): ?>
                                        <tr>
                                            <td>
                                                <?php echo format_date($log['created_at']); ?>
                                                <br><small><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="log-level level-<?php echo $log['level']; ?>">
                                                    <?php echo strtoupper($log['level']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['source']); ?></td>
                                            <td class="message-cell" title="<?php echo htmlspecialchars($log['message']); ?>">
                                                <?php echo htmlspecialchars(substr($log['message'], 0, 100)); ?>
                                                <?php if (strlen($log['message']) > 100): ?>...<?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn-small" onclick="showDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                                    <i class="fas fa-search"></i> Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <div class="empty-state">
                                                <i class="fas fa-server"></i>
                                                <p>No system logs found for the selected period</p>
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
                            <a href="?page=<?php echo $page - 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $level_filter ? '&level=' . $level_filter : ''; ?><?php echo $source_filter ? '&source=' . $source_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $level_filter ? '&level=' . $level_filter : ''; ?><?php echo $source_filter ? '&source=' . $source_filter : ''; ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pagination['total_pages']): ?>
                            <a href="?page=<?php echo $page + 1; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?><?php echo $level_filter ? '&level=' . $level_filter : ''; ?><?php echo $source_filter ? '&source=' . $source_filter : ''; ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Details Popup -->
    <div class="overlay" id="overlay"></div>
    <div class="details-popup" id="detailsPopup">
        <div class="details-header">
            <h3>Log Details</h3>
            <button class="close-modal" onclick="hideDetails()">&times;</button>
        </div>
        <div id="detailsContent" class="details-content"></div>
    </div>

    <script>
        function showDetails(log) {
            const details = `
Log ID: ${log.log_id}
Timestamp: ${log.created_at}
Level: ${log.level.toUpperCase()}
Source: ${log.source}
Message: ${log.message}
Details: ${log.details || 'No additional details'}
            `;
            
            document.getElementById('detailsContent').textContent = details;
            document.getElementById('detailsPopup').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }
        
        function hideDetails() {
            document.getElementById('detailsPopup').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }
        
        // Auto-refresh logs every 30 seconds if on first page and showing recent logs
        let currentPage = <?php echo $page; ?>;
        if (currentPage === 1 && '<?php echo $date_to; ?>' === '<?php echo date('Y-m-d'); ?>') {
            setInterval(() => {
                if (!document.hidden) {
                    location.reload();
                }
            }, 30000);
        }
    </script>
</body>
</html>