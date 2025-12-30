<?php
// admin/chat-management.php
require_once '../../includes/init.php';

if (!is_admin()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

// Update agent status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_status'])) {
        $status = $_POST['status'] === 'online' ? 1 : 0;
        $stmt = mysqli_prepare($conn, 
            "INSERT INTO chat_agents (user_id, is_online) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE is_online = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $_SESSION['user_id'], $status, $status);
        mysqli_stmt_execute($stmt);
    }
}

// Get agent status
$agent_stmt = mysqli_prepare($conn, 
    "SELECT is_online FROM chat_agents WHERE user_id = ?");
mysqli_stmt_bind_param($agent_stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($agent_stmt);
$agent_result = mysqli_stmt_get_result($agent_stmt);
$agent = mysqli_fetch_assoc($agent_result);
$is_online = $agent ? $agent['is_online'] : false;

// Get assigned conversations
$conv_stmt = mysqli_prepare($conn, 
    "SELECT cc.*, u.name as user_name, 
            (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.conversation_id AND sender_id != cc.support_agent_id AND is_read = FALSE) as unread_messages
     FROM chat_conversations cc
     JOIN users u ON cc.user_id = u.user_id
     WHERE cc.support_agent_id = ? AND cc.status IN ('active', 'pending')
     ORDER BY cc.last_message_at DESC");
mysqli_stmt_bind_param($conv_stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($conv_stmt);
$conversations = mysqli_stmt_get_result($conv_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Management - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .agent-status-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: <?php echo $is_online ? '#4CAF50' : '#f44336'; ?>;
        }
        
        .chat-list {
            display: grid;
            gap: 15px;
        }
        
        .chat-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .chat-item.unread {
            border-left: 4px solid #667eea;
        }
        
        .unread-badge {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Chat Management</h1>
        
        <div class="agent-status-toggle">
            <div class="status-indicator"></div>
            <span>Status: <?php echo $is_online ? 'Online' : 'Offline'; ?></span>
            <form method="POST" style="display: inline;">
                <button type="submit" name="toggle_status" value="<?php echo $is_online ? 'offline' : 'online'; ?>" class="btn">
                    Go <?php echo $is_online ? 'Offline' : 'Online'; ?>
                </button>
            </form>
        </div>
        
        <h2>Assigned Chats</h2>
        <div class="chat-list">
            <?php while ($chat = mysqli_fetch_assoc($conversations)): ?>
                <div class="chat-item <?php echo $chat['unread_messages'] > 0 ? 'unread' : ''; ?>">
                    <div>
                        <h4><?php echo htmlspecialchars($chat['user_name']); ?></h4>
                        <p><?php echo htmlspecialchars($chat['subject']); ?></p>
                        <small>Last message: <?php echo date('H:i', strtotime($chat['last_message_at'])); ?></small>
                    </div>
                    <div>
                        <?php if ($chat['unread_messages'] > 0): ?>
                            <span class="unread-badge"><?php echo $chat['unread_messages']; ?> unread</span>
                        <?php endif; ?>
                        <a href="chat-view.php?id=<?php echo $chat['conversation_id']; ?>" class="btn btn-sm">View Chat</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>