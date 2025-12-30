#!/bin/bash

echo "Setting up ArdhiYetu Blockchain Integration..."

# Create blockchain directory structure
mkdir -p blockchain/{smart_contracts,database,api,ui,wallets,scripts}

# Install dependencies
npm install -g truffle @openzeppelin/contracts
npm install web3 ethers
pip install web3.py

# Compile smart contracts
cd blockchain/smart_contracts
truffle compile

# Deploy to testnet
echo "Deploying to Polygon Mumbai testnet..."
truffle migrate --network mumbai

echo "Blockchain setup complete!"