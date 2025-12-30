-- Blockchain Transactions Log
CREATE TABLE IF NOT EXISTS blockchain_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_hash VARCHAR(66) UNIQUE NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(100),
    block_number INT,
    gas_used DECIMAL(20,0),
    gas_price DECIMAL(20,0),
    from_address VARCHAR(42),
    to_address VARCHAR(42),
    contract_address VARCHAR(42),
    data JSON,
    status ENUM('pending', 'confirmed', 'failed', 'reverted') DEFAULT 'pending',
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transaction_hash (transaction_hash),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- User Wallets
CREATE TABLE IF NOT EXISTS user_wallets (
    wallet_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    wallet_address VARCHAR(42) NOT NULL UNIQUE,
    wallet_type ENUM('eth', 'bsc', 'polygon') DEFAULT 'eth',
    private_key_encrypted TEXT,
    mnemonic_encrypted TEXT,
    balance_eth DECIMAL(20,18) DEFAULT 0,
    balance_usd DECIMAL(20,2) DEFAULT 0,
    last_synced TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_wallet_address (wallet_address),
    INDEX idx_user_id (user_id)
);

-- Land NFTs
CREATE TABLE IF NOT EXISTS land_nfts (
    nft_id INT PRIMARY KEY AUTO_INCREMENT,
    parcel_no VARCHAR(100) NOT NULL,
    token_id VARCHAR(100) NOT NULL,
    token_standard ENUM('ERC721', 'ERC1155') DEFAULT 'ERC721',
    contract_address VARCHAR(42) NOT NULL,
    owner_id INT NOT NULL,
    previous_owner_id INT,
    ipfs_hash VARCHAR(255),
    metadata_url TEXT,
    mint_transaction_hash VARCHAR(66),
    minted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_transfer_at TIMESTAMP NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id),
    FOREIGN KEY (previous_owner_id) REFERENCES users(user_id),
    FOREIGN KEY (verified_by) REFERENCES users(user_id),
    UNIQUE KEY uk_token (contract_address, token_id),
    INDEX idx_parcel (parcel_no),
    INDEX idx_owner (owner_id),
    INDEX idx_contract (contract_address)
);

-- Smart Contracts
CREATE TABLE IF NOT EXISTS smart_contracts (
    contract_id INT PRIMARY KEY AUTO_INCREMENT,
    contract_name VARCHAR(100) NOT NULL,
    contract_address VARCHAR(42) NOT NULL UNIQUE,
    contract_type ENUM('land_registry', 'land_token', 'payment', 'dispute') NOT NULL,
    network ENUM('mainnet', 'testnet', 'local') DEFAULT 'testnet',
    abi JSON NOT NULL,
    bytecode TEXT,
    compiler_version VARCHAR(50),
    owner_address VARCHAR(42),
    deployed_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contract_address (contract_address),
    INDEX idx_contract_type (contract_type)
);

-- Blockchain Nodes
CREATE TABLE IF NOT EXISTS blockchain_nodes (
    node_id INT PRIMARY KEY AUTO_INCREMENT,
    node_name VARCHAR(100) NOT NULL,
    node_url VARCHAR(255) NOT NULL,
    network ENUM('ethereum', 'binance', 'polygon', 'arbitrum') DEFAULT 'ethereum',
    chain_id INT NOT NULL,
    node_type ENUM('rpc', 'ws', 'ipc') DEFAULT 'rpc',
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    last_checked TIMESTAMP NULL,
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_network (network),
    INDEX idx_is_active (is_active)
);

-- IPFS Storage
CREATE TABLE IF NOT EXISTS ipfs_storage (
    ipfs_id INT PRIMARY KEY AUTO_INCREMENT,
    content_hash VARCHAR(255) NOT NULL UNIQUE,
    content_type VARCHAR(100),
    original_filename VARCHAR(255),
    file_size BIGINT,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pin_status ENUM('pinned', 'unpinned', 'failed') DEFAULT 'pinned',
    pin_date TIMESTAMP NULL,
    gateway_url TEXT,
    metadata JSON,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id),
    INDEX idx_content_hash (content_hash),
    INDEX idx_uploaded_by (uploaded_by)
);

-- Transaction Witnesses
CREATE TABLE IF NOT EXISTS transaction_witnesses (
    witness_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_hash VARCHAR(66) NOT NULL,
    witness_address VARCHAR(42) NOT NULL,
    witness_type ENUM('lawyer', 'surveyor', 'neighbor', 'government') DEFAULT 'lawyer',
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approval_timestamp TIMESTAMP NULL,
    signature_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_hash) REFERENCES blockchain_transactions(transaction_hash) ON DELETE CASCADE,
    UNIQUE KEY uk_witness_transaction (transaction_hash, witness_address)
);

-- Land History Blockchain View
CREATE VIEW vw_land_blockchain_history AS
SELECT 
    l.parcel_no,
    l.location,
    bt.transaction_hash,
    bt.action,
    bt.from_address,
    bt.to_address,
    bt.block_number,
    bt.created_at as transaction_date,
    JSON_UNQUOTE(JSON_EXTRACT(bt.data, '$.price')) as transaction_price,
    bt.status
FROM land_records l
LEFT JOIN blockchain_transactions bt ON l.parcel_no = bt.entity_id AND bt.entity_type = 'land'
WHERE bt.transaction_hash IS NOT NULL
ORDER BY bt.created_at DESC;

-- Blockchain Stats View
CREATE VIEW vw_blockchain_stats AS
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_transactions,
    SUM(CASE WHEN action = 'land_registration' THEN 1 ELSE 0 END) as registrations,
    SUM(CASE WHEN action = 'land_transfer' THEN 1 ELSE 0 END) as transfers,
    AVG(gas_used * gas_price / 1e18) as avg_eth_cost
FROM blockchain_transactions
GROUP BY DATE(created_at);