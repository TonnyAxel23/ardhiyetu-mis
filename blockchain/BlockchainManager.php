<?php
namespace ArdhiYetu\Blockchain;

require_once __DIR__ . '/../../includes/init.php';

class BlockchainManager {
    private $conn;
    private $web3Provider;
    private $contractAddress;
    private $privateKey;
    private $ipfsGateway;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
        $this->web3Provider = BLOCKCHAIN_RPC_URL;
        $this->contractAddress = BLOCKCHAIN_CONTRACT_ADDRESS;
        $this->privateKey = BLOCKCHAIN_PRIVATE_KEY;
        $this->ipfsGateway = IPFS_GATEWAY_URL;
        
        // Initialize web3
        $this->initializeWeb3();
    }
    
    private function initializeWeb3() {
        // Check if web3.php is available
        if (!class_exists('Web3\Web3')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
    }
    
    /**
     * Register land on blockchain
     */
    public function registerLandOnBlockchain(array $landData): array {
        try {
            // 1. Upload documents to IPFS
            $ipfsHash = $this->uploadToIPFS($landData);
            
            // 2. Prepare transaction data
            $transactionData = [
                'parcelNumber' => $landData['parcel_no'],
                'location' => $landData['location'],
                'size' => $this->convertToSquareMeters($landData['size']),
                'ipfsHash' => $ipfsHash,
                'ownerAddress' => $this->getUserWalletAddress($landData['owner_id'])
            ];
            
            // 3. Call smart contract
            $txHash = $this->callContract(
                'registerLand',
                [
                    $transactionData['parcelNumber'],
                    $transactionData['location'],
                    $transactionData['size'],
                    $transactionData['ipfsHash']
                ]
            );
            
            // 4. Wait for confirmation
            $receipt = $this->waitForTransaction($txHash);
            
            // 5. Create land NFT
            $nftId = $this->mintLandNFT($landData, $ipfsHash);
            
            // 6. Log blockchain transaction
            $this->logBlockchainTransaction([
                'action' => 'land_registration',
                'parcel_no' => $landData['parcel_no'],
                'transaction_hash' => $txHash,
                'block_number' => $receipt['blockNumber'] ?? null,
                'nft_id' => $nftId,
                'ipfs_hash' => $ipfsHash,
                'status' => 'confirmed'
            ]);
            
            return [
                'success' => true,
                'transaction_hash' => $txHash,
                'nft_id' => $nftId,
                'ipfs_hash' => $ipfsHash,
                'block_number' => $receipt['blockNumber'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Blockchain registration failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Transfer land ownership on blockchain
     */
    public function transferLandOnBlockchain(array $transferData): array {
        try {
            // 1. Validate current ownership
            if (!$this->verifyOwnership(
                $transferData['parcel_no'],
                $transferData['from_user_id']
            )) {
                throw new Exception("Ownership verification failed");
            }
            
            // 2. Get wallet addresses
            $fromAddress = $this->getUserWalletAddress($transferData['from_user_id']);
            $toAddress = $this->getUserWalletAddress($transferData['to_user_id']);
            
            // 3. Upload transfer documents to IPFS
            $documentHash = $this->uploadTransferDocuments($transferData);
            
            // 4. Initiate transfer on blockchain
            $requestId = $this->callContract(
                'initiateTransfer',
                [
                    $transferData['parcel_no'],
                    $toAddress,
                    $transferData['price'] ?? 0,
                    $documentHash
                ],
                $fromAddress
            );
            
            // 5. Get witnesses (3 witnesses required)
            $witnesses = $this->getWitnesses($transferData['record_id']);
            
            // 6. Submit witness approvals
            foreach ($witnesses as $index => $witness) {
                $this->callContract(
                    'approveTransfer',
                    [$requestId, $index],
                    $this->getUserWalletAddress($witness['user_id'])
                );
            }
            
            // 7. Government approval (simulated)
            $this->callContract(
                'approveTransferByGovernment',
                [$requestId],
                BLOCKCHAIN_GOVERNMENT_WALLET
            );
            
            // 8. Complete transfer
            $txHash = $this->callContract(
                'completeTransfer',
                [$requestId],
                $fromAddress
            );
            
            // 9. Transfer NFT
            $nftTransfer = $this->transferLandNFT(
                $transferData['parcel_no'],
                $fromAddress,
                $toAddress,
                $documentHash
            );
            
            // 10. Log transaction
            $this->logBlockchainTransaction([
                'action' => 'land_transfer',
                'transfer_id' => $transferData['transfer_id'],
                'parcel_no' => $transferData['parcel_no'],
                'transaction_hash' => $txHash,
                'nft_transaction' => $nftTransfer,
                'request_id' => $requestId,
                'status' => 'completed'
            ]);
            
            return [
                'success' => true,
                'transaction_hash' => $txHash,
                'request_id' => $requestId,
                'nft_transfer' => $nftTransfer
            ];
            
        } catch (Exception $e) {
            error_log("Blockchain transfer failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify land ownership on blockchain
     */
    public function verifyLandOwnership(string $parcelNumber, int $userId): array {
        try {
            $userAddress = $this->getUserWalletAddress($userId);
            
            $result = $this->callContract(
                'verifyOwnership',
                [$parcelNumber, $userAddress],
                null,
                'call' // Read-only call
            );
            
            // Get land history from blockchain
            $history = $this->callContract(
                'getLandHistory',
                [$parcelNumber],
                null,
                'call'
            );
            
            // Get NFT details
            $nftDetails = $this->getLandNFTDetails($parcelNumber);
            
            return [
                'is_owner' => (bool)$result,
                'verification_timestamp' => time(),
                'blockchain_address' => $userAddress,
                'ownership_history' => $history,
                'nft_details' => $nftDetails
            ];
            
        } catch (Exception $e) {
            return [
                'is_owner' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload documents to IPFS
     */
    private function uploadToIPFS(array $data): string {
        // Create JSON document
        $document = [
            'parcel_number' => $data['parcel_no'],
            'location' => $data['location'],
            'size' => $data['size'],
            'owner' => $this->getUserDetails($data['owner_id']),
            'registration_date' => date('Y-m-d H:i:s'),
            'survey_details' => $data['survey_details'] ?? '',
            'boundary_coordinates' => $data['boundary_coordinates'] ?? ''
        ];
        
        // Convert to JSON
        $jsonData = json_encode($document, JSON_PRETTY_PRINT);
        
        // In production, use IPFS API
        // For demo, we'll create a hash
        $hash = 'ipfs_' . hash('sha256', $jsonData);
        
        // Store locally for demo
        $ipfsDir = __DIR__ . '/../uploads/ipfs/';
        if (!is_dir($ipfsDir)) {
            mkdir($ipfsDir, 0777, true);
        }
        
        file_put_contents($ipfsDir . $hash . '.json', $jsonData);
        
        return $hash;
    }
    
    /**
     * Call smart contract
     */
    private function callContract(string $function, array $params, ?string $from = null, string $type = 'send'): mixed {
        // For demo purposes - in production use web3.php
        
        if ($type === 'call') {
            // Read-only call
            return $this->simulateContractCall($function, $params);
        } else {
            // Transaction
            $txHash = '0x' . bin2hex(random_bytes(32));
            
            // Simulate blockchain confirmation
            $this->simulateBlockConfirmation($txHash);
            
            return $txHash;
        }
    }
    
    /**
     * Get user's wallet address
     */
    private function getUserWalletAddress(int $userId): string {
        $sql = "SELECT wallet_address FROM user_wallets WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if ($row && !empty($row['wallet_address'])) {
            return $row['wallet_address'];
        }
        
        // Generate new wallet if doesn't exist
        return $this->createUserWallet($userId);
    }
    
    /**
     * Create wallet for user
     */
    private function createUserWallet(int $userId): string {
        // In production, use proper key generation
        // For demo, generate deterministic address
        $address = '0x' . hash('sha256', "ardhiyetu_user_{$userId}_" . time());
        $address = substr($address, 0, 42); // Ethereum address format
        
        // Store in database
        $sql = "INSERT INTO user_wallets (user_id, wallet_address, created_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE wallet_address = ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iss', $userId, $address, $address);
        mysqli_stmt_execute($stmt);
        
        return $address;
    }
    
    /**
     * Log blockchain transaction
     */
    private function logBlockchainTransaction(array $data): void {
        $sql = "INSERT INTO blockchain_transactions 
                (action, entity_id, entity_type, transaction_hash, 
                 block_number, data, created_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        
        $entityId = $data['transfer_id'] ?? $data['parcel_no'] ?? null;
        $entityType = isset($data['transfer_id']) ? 'transfer' : 'land';
        $jsonData = json_encode($data);
        
        mysqli_stmt_bind_param($stmt, 'sississ',
            $data['action'],
            $entityId,
            $entityType,
            $data['transaction_hash'],
            $data['block_number'] ?? null,
            $jsonData,
            $data['status'] ?? 'pending'
        );
        
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Mint land NFT
     */
    private function mintLandNFT(array $landData, string $ipfsHash): int {
        // Simulate NFT minting
        $nftId = rand(1000, 9999);
        
        $sql = "INSERT INTO land_nfts 
                (parcel_no, token_id, owner_id, ipfs_hash, minted_at, contract_address)
                VALUES (?, ?, ?, ?, NOW(), ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $contractAddress = $this->contractAddress;
        
        mysqli_stmt_bind_param($stmt, 'siiss',
            $landData['parcel_no'],
            $nftId,
            $landData['owner_id'],
            $ipfsHash,
            $contractAddress
        );
        
        mysqli_stmt_execute($stmt);
        
        return $nftId;
    }
    
    /**
     * Get land NFT details
     */
    private function getLandNFTDetails(string $parcelNumber): ?array {
        $sql = "SELECT * FROM land_nfts WHERE parcel_no = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $parcelNumber);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    /**
     * Convert acres to square meters
     */
    private function convertToSquareMeters(float $acres): int {
        return (int)($acres * 4046.86); // 1 acre = 4046.86 sq meters
    }
    
    /**
     * Get witnesses for transfer
     */
    private function getWitnesses(int $recordId): array {
        // Get 3 random users as witnesses (in production, use actual witnesses)
        $sql = "SELECT user_id, name FROM users 
                WHERE user_id != ? AND role IN ('lawyer', 'surveyor', 'admin')
                ORDER BY RAND() LIMIT 3";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $recordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $witnesses = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $witnesses[] = $row;
        }
        
        return $witnesses;
    }
    
    /**
     * Simulate contract call (for demo)
     */
    private function simulateContractCall(string $function, array $params): mixed {
        // Simulate blockchain response
        switch ($function) {
            case 'verifyOwnership':
                return rand(0, 1) == 1; // Random true/false for demo
            case 'getLandHistory':
                return ['0xOwner1', '0xOwner2', '0xOwner3'];
            default:
                return null;
        }
    }
    
    /**
     * Simulate block confirmation (for demo)
     */
    private function simulateBlockConfirmation(string $txHash): void {
        // Simulate blockchain delay
        sleep(2);
        
        // Update transaction status
        $sql = "UPDATE blockchain_transactions 
                SET status = 'confirmed', 
                    block_number = ?, 
                    confirmed_at = NOW() 
                WHERE transaction_hash = ?";
        
        $blockNumber = rand(1000000, 2000000);
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $blockNumber, $txHash);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get blockchain dashboard stats
     */
    public function getBlockchainStats(): array {
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_transactions,
                    SUM(CASE WHEN action = 'land_registration' THEN 1 ELSE 0 END) as land_registrations,
                    SUM(CASE WHEN action = 'land_transfer' THEN 1 ELSE 0 END) as land_transfers,
                    MIN(created_at) as first_transaction,
                    MAX(created_at) as last_transaction
                FROM blockchain_transactions";
        
        $result = mysqli_query($this->conn, $sql);
        $stats = mysqli_fetch_assoc($result);
        
        // Get NFT stats
        $sql = "SELECT COUNT(*) as total_nfts FROM land_nfts";
        $result = mysqli_query($this->conn, $sql);
        $nftStats = mysqli_fetch_assoc($result);
        
        $stats['total_nfts'] = $nftStats['total_nfts'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Get transaction history for land
     */
    public function getLandBlockchainHistory(string $parcelNumber): array {
        $sql = "SELECT * FROM blockchain_transactions 
                WHERE entity_type = 'land' AND entity_id = ?
                ORDER BY created_at DESC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $parcelNumber);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $transactions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['data'] = json_decode($row['data'], true);
            $transactions[] = $row;
        }
        
        return $transactions;
    }
}