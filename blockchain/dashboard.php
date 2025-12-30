<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/BlockchainManager.php';

use ArdhiYetu\Blockchain\BlockchainManager;

// Check authentication
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$blockchain = new BlockchainManager($conn);
$stats = $blockchain->getBlockchainStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Dashboard - ArdhiYetu</title>
    <style>
        :root {
            --blockchain-primary: #627eea;
            --blockchain-secondary: #353535;
            --blockchain-accent: #f7931a;
            --blockchain-success: #27ae60;
        }
        
        .blockchain-dashboard {
            padding: 2rem;
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: white;
            min-height: 100vh;
        }
        
        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .blockchain-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--blockchain-primary);
            margin: 0.5rem 0;
        }
        
        .blockchain-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            color: #333;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .action-card h3 {
            color: var(--blockchain-primary);
            margin-bottom: 1rem;
        }
        
        .btn-blockchain {
            background: linear-gradient(135deg, var(--blockchain-primary) 0%, #8a2be2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-blockchain:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(98, 126, 234, 0.3);
        }
        
        .transaction-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .transaction-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--blockchain-primary);
        }
        
        .wallet-info {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .qr-code {
            width: 150px;
            height: 150px;
            background: white;
            padding: 10px;
            border-radius: 10px;
            margin: 1rem auto;
        }
    </style>
</head>
<body>
    <div class="blockchain-dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-link"></i> ArdhiYetu Blockchain Dashboard</h1>
            <p>Immutable Land Records on Blockchain</p>
            <div class="network-badge">
                <span class="badge" style="background: #8247e5;">Polygon Mumbai Testnet</span>
                <span class="badge" style="background: #27ae60;">✓ Blockchain Active</span>
            </div>
        </div>
        
        <div class="blockchain-stats">
            <div class="stat-card">
                <i class="fas fa-exchange-alt fa-2x"></i>
                <div class="stat-value"><?php echo number_format($stats['total_transactions'] ?? 0); ?></div>
                <p>Total Transactions</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-landmark fa-2x"></i>
                <div class="stat-value"><?php echo number_format($stats['land_registrations'] ?? 0); ?></div>
                <p>Land NFTs Minted</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-users fa-2x"></i>
                <div class="stat-value"><?php echo number_format($stats['confirmed_transactions'] ?? 0); ?></div>
                <p>Confirmed Transactions</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-gas-pump fa-2x"></i>
                <div class="stat-value"><?php echo $stats['avg_eth_cost'] ?? '0.00'; ?></div>
                <p>Avg Gas Cost (MATIC)</p>
            </div>
        </div>
        
        <div class="blockchain-actions">
            <div class="action-card">
                <h3><i class="fas fa-wallet"></i> My Blockchain Wallet</h3>
                <?php
                $walletAddress = $blockchain->getUserWalletAddress($_SESSION['user_id']);
                ?>
                <p><strong>Address:</strong> <code><?php echo $walletAddress; ?></code></p>
                <p><strong>Balance:</strong> Loading...</p>
                <div class="qr-code">
                    <!-- QR code would be generated here -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($walletAddress); ?>" 
                         alt="Wallet QR Code">
                </div>
                <button class="btn-blockchain" onclick="copyToClipboard('<?php echo $walletAddress; ?>')">
                    <i class="fas fa-copy"></i> Copy Address
                </button>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-landmark"></i> My Land NFTs</h3>
                <p>View and manage your blockchain land titles</p>
                <button class="btn-blockchain" onclick="window.location.href='blockchain/my-nfts.php'">
                    <i class="fas fa-eye"></i> View My NFTs
                </button>
                <button class="btn-blockchain" style="background: var(--blockchain-accent);" 
                        onclick="window.location.href='blockchain/verify-land.php'">
                    <i class="fas fa-check-circle"></i> Verify Land
                </button>
            </div>
            
            <div class="action-card">
                <h3><i class="fas fa-file-contract"></i> Smart Contracts</h3>
                <p>Interact with blockchain smart contracts</p>
                <button class="btn-blockchain" onclick="window.open('<?php echo BLOCKCHAIN_EXPLORER_URL . '/address/' . LAND_REGISTRY_CONTRACT; ?>')">
                    <i class="fas fa-external-link-alt"></i> View Land Registry
                </button>
                <button class="btn-blockchain" onclick="window.open('<?php echo NFT_EXPLORER_URL . '/collection/ardhiyetu-land'; ?>')">
                    <i class="fas fa-external-link-alt"></i> View NFT Collection
                </button>
            </div>
        </div>
        
        <div class="transaction-list">
            <h3><i class="fas fa-history"></i> Recent Blockchain Transactions</h3>
            <?php
            $sql = "SELECT * FROM blockchain_transactions 
                    WHERE from_address = ? OR to_address = ?
                    ORDER BY created_at DESC LIMIT 10";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $walletAddress, $walletAddress);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0):
            ?>
                <div class="transactions">
                    <?php while ($tx = mysqli_fetch_assoc($result)): ?>
                    <div class="transaction-item">
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <strong><?php echo ucfirst(str_replace('_', ' ', $tx['action'])); ?></strong>
                                <br>
                                <small><?php echo date('M d, Y H:i:s', strtotime($tx['created_at'])); ?></small>
                            </div>
                            <div>
                                <span class="badge" style="background: 
                                    <?php echo $tx['status'] == 'confirmed' ? '#27ae60' : 
                                           ($tx['status'] == 'pending' ? '#f39c12' : '#e74c3c'); ?>">
                                    <?php echo ucfirst($tx['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <code><?php echo substr($tx['transaction_hash'], 0, 20); ?>...</code>
                            <a href="<?php echo BLOCKCHAIN_EXPLORER_URL . '/tx/' . $tx['transaction_hash']; ?>" 
                               target="_blank" style="color: var(--blockchain-primary); margin-left: 1rem;">
                                <i class="fas fa-external-link-alt"></i> View on Explorer
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem; color: #ccc;">
                    <i class="fas fa-inbox fa-2x"></i><br>
                    No blockchain transactions yet
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Wallet address copied to clipboard!');
        });
    }
    
    // Load wallet balance
    async function loadWalletBalance() {
        try {
            const response = await fetch(`/api/blockchain/balance.php?address=<?php echo $walletAddress; ?>`);
            const data = await response.json();
            
            if (data.success) {
                document.querySelector('.action-card p:nth-child(2) strong').nextSibling.textContent = 
                    ` ${data.balance} MATIC ($${data.balance_usd})`;
            }
        } catch (error) {
            console.error('Failed to load balance:', error);
        }
    }
    
    // Initial load
    loadWalletBalance();
    
    // Refresh every 30 seconds
    setInterval(loadWalletBalance, 30000);
    </script>
</body>
</html>