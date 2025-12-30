<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require __DIR__ . '/vendor/autoload.php';

class NotificationServer implements MessageComponentInterface {
    protected $clients;
    protected $adminConnections;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->adminConnections = [];
        $this->userConnections = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "[" . date('Y-m-d H:i:s') . "] New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (isset($data['type']) && $data['type'] === 'auth') {
            if ($data['role'] === 'admin') {
                $this->adminConnections[$data['user_id']] = $from;
                echo "[" . date('Y-m-d H:i:s') . "] Admin {$data['user_id']} connected\n";
                $this->sendPendingCounts($from);
            } else {
                $this->userConnections[$data['user_id']] = $from;
                echo "[" . date('Y-m-d H:i:s') . "] User {$data['user_id']} connected\n";
            }
        }
        
        if (isset($data['type']) && $data['type'] === 'new_land') {
            $this->notifyAdmins([
                'type' => 'new_land',
                'land_id' => $data['land_id'],
                'parcel_no' => $data['parcel_no'],
                'user_name' => $data['user_name'],
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => "New land registration: {$data['parcel_no']} by {$data['user_name']}"
            ]);
        }
        
        if (isset($data['type']) && $data['type'] === 'new_transfer') {
            $this->notifyAdmins([
                'type' => 'new_transfer',
                'transfer_id' => $data['transfer_id'],
                'reference_no' => $data['reference_no'],
                'from_user' => $data['from_user'],
                'to_user' => $data['to_user'],
                'parcel_no' => $data['parcel_no'],
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => "New transfer request: {$data['parcel_no']} from {$data['from_user']} to {$data['to_user']}"
            ]);
        }
        
        if (isset($data['type']) && $data['type'] === 'transfer_approved') {
            $this->notifyUser($data['to_user_id'], [
                'type' => 'transfer_approved',
                'transfer_id' => $data['transfer_id'],
                'parcel_no' => $data['parcel_no'],
                'message' => "Transfer of {$data['parcel_no']} has been approved. You are now the owner.",
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $this->notifyUser($data['from_user_id'], [
                'type' => 'transfer_completed',
                'transfer_id' => $data['transfer_id'],
                'parcel_no' => $data['parcel_no'],
                'message' => "Transfer of {$data['parcel_no']} to {$data['to_user']} has been completed.",
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        if (isset($data['type']) && $data['type'] === 'land_approved') {
            $this->notifyUser($data['user_id'], [
                'type' => 'land_approved',
                'land_id' => $data['land_id'],
                'parcel_no' => $data['parcel_no'],
                'message' => "Your land registration for {$data['parcel_no']} has been approved.",
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        if (isset($data['type']) && $data['type'] === 'transfer_rejected') {
            $this->notifyUser($data['from_user_id'], [
                'type' => 'transfer_rejected',
                'transfer_id' => $data['transfer_id'],
                'parcel_no' => $data['parcel_no'],
                'message' => "Transfer of {$data['parcel_no']} has been rejected. Reason: {$data['reason']}",
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $this->notifyUser($data['to_user_id'], [
                'type' => 'transfer_rejected',
                'transfer_id' => $data['transfer_id'],
                'parcel_no' => $data['parcel_no'],
                'message' => "Transfer request for {$data['parcel_no']} has been rejected.",
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        if (isset($data['type']) && $data['type'] === 'land_rejected') {
            $this->notifyUser($data['user_id'], [
                'type' => 'land_rejected',
                'land_id' => $data['land_id'],
                'parcel_no' => $data['parcel_no'],
                'message' => "Your land registration for {$data['parcel_no']} has been rejected. Reason: {$data['reason']}",
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        if (isset($data['type']) && $data['type'] === 'get_pending_counts') {
            $this->sendPendingCounts($from);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from admin connections
        $adminId = array_search($conn, $this->adminConnections);
        if ($adminId !== false) {
            unset($this->adminConnections[$adminId]);
            echo "[" . date('Y-m-d H:i:s') . "] Admin {$adminId} disconnected\n";
        }
        
        // Remove from user connections
        $userId = array_search($conn, $this->userConnections);
        if ($userId !== false) {
            unset($this->userConnections[$userId]);
            echo "[" . date('Y-m-d H:i:s') . "] User {$userId} disconnected\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    private function notifyAdmins($data) {
        foreach ($this->adminConnections as $adminConn) {
            try {
                $adminConn->send(json_encode($data));
            } catch (\Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Failed to send to admin: {$e->getMessage()}\n";
            }
        }
    }

    private function notifyUser($userId, $data) {
        if (isset($this->userConnections[$userId])) {
            try {
                $this->userConnections[$userId]->send(json_encode($data));
            } catch (\Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] Failed to send to user {$userId}: {$e->getMessage()}\n";
            }
        }
    }

    private function sendPendingCounts($conn) {
        try {
            require_once __DIR__ . '/includes/init.php';
            global $conn;
            
            $land_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM land_records WHERE status = 'pending'");
            $transfer_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM ownership_transfers WHERE status IN ('submitted', 'under_review')");
            
            $data = [
                'type' => 'pending_counts',
                'pending_lands' => mysqli_fetch_assoc($land_count)['count'],
                'pending_transfers' => mysqli_fetch_assoc($transfer_count)['count']
            ];
            
            $conn->send(json_encode($data));
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] Error getting pending counts: {$e->getMessage()}\n";
        }
    }
}

// Start the server
$port = 8080;
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NotificationServer()
        )
    ),
    $port
);

echo "[" . date('Y-m-d H:i:s') . "] WebSocket server started on port {$port}\n";
echo "[" . date('Y-m-d H:i:s') . "] Press Ctrl+C to stop the server\n";

$server->run();