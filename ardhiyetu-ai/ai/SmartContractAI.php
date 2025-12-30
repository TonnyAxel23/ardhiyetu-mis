<?php
namespace ArdhiYetu\AI;

require_once __DIR__ . '/../../includes/init.php';

class SmartContractAI {
    private $conn;
    private $web3;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
        $this->initializeWeb3();
    }
    
    private function initializeWeb3() {
        // Initialize web3 connection
        try {
            if (class_exists('Web3\Web3')) {
                $this->web3 = new \Web3\Web3(BLOCKCHAIN_RPC_URL);
            }
        } catch (Exception $e) {
            error_log("Web3 initialization failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate smart contract for land transfer
     */
    public function generateTransferContract(array $transferData): array {
        $contract = [
            'template' => $this->selectContractTemplate($transferData),
            'variables' => $this->extractContractVariables($transferData),
            'clauses' => $this->generateContractClauses($transferData),
            'conditions' => $this->generateContractConditions($transferData),
            'signatories' => $this->determineSignatories($transferData)
        ];
        
        // Generate Solidity code
        $contract['solidity_code'] = $this->generateSolidityCode($contract);
        
        // Generate ABI
        $contract['abi'] = $this->generateABI($contract);
        
        // Generate bytecode (simulated)
        $contract['bytecode'] = $this->generateBytecode($contract);
        
        // Generate natural language version
        $contract['natural_language'] = $this->generateNaturalLanguageContract($contract);
        
        return $contract;
    }
    
    private function selectContractTemplate(array $data): string {
        $type = $data['transfer_type'] ?? 'sale';
        $value = $data['price'] ?? 0;
        
        $templates = [
            'sale' => 'land_sale_contract',
            'gift' => 'land_gift_contract',
            'inheritance' => 'land_inheritance_contract',
            'lease' => 'land_lease_contract',
            'mortgage' => 'land_mortgage_contract',
            'partition' => 'land_partition_contract'
        ];
        
        return $templates[$type] ?? 'land_sale_contract';
    }
    
    private function extractContractVariables(array $data): array {
        return [
            'seller' => [
                'name' => $data['from_user_name'] ?? '',
                'address' => $data['from_address'] ?? '',
                'id_number' => $data['from_id_number'] ?? ''
            ],
            'buyer' => [
                'name' => $data['to_user_name'] ?? '',
                'address' => $data['to_address'] ?? '',
                'id_number' => $data['to_id_number'] ?? ''
            ],
            'land' => [
                'parcel_number' => $data['parcel_no'] ?? '',
                'location' => $data['location'] ?? '',
                'size' => $data['size'] ?? 0,
                'coordinates' => $data['coordinates'] ?? '',
                'land_reference' => $data['land_reference'] ?? ''
            ],
            'transaction' => [
                'price' => $data['price'] ?? 0,
                'deposit' => $data['deposit'] ?? 0,
                'payment_terms' => $data['payment_terms'] ?? 'full',
                'completion_date' => $data['completion_date'] ?? date('Y-m-d', strtotime('+30 days')),
                'possession_date' => $data['possession_date'] ?? date('Y-m-d', strtotime('+30 days'))
            ]
        ];
    }
    
    private function generateContractClauses(array $data): array {
        $clauses = [];
        
        // Standard clauses
        $clauses[] = [
            'id' => 'definitions',
            'title' => 'Definitions',
            'content' => $this->generateDefinitionsClause($data)
        ];
        
        $clauses[] = [
            'id' => 'sale_purchase',
            'title' => 'Sale and Purchase',
            'content' => $this->generateSalePurchaseClause($data)
        ];
        
        $clauses[] = [
            'id' => 'purchase_price',
            'title' => 'Purchase Price',
            'content' => $this->generatePurchasePriceClause($data)
        ];
        
        $clauses[] = [
            'id' => 'title_verification',
            'title' => 'Title Verification',
            'content' => $this->generateTitleVerificationClause($data)
        ];
        
        $clauses[] = [
            'id' => 'representations_warranties',
            'title' => 'Representations and Warranties',
            'content' => $this->generateRepresentationsClause($data)
        ];
        
        $clauses[] = [
            'id' => 'possession',
            'title' => 'Possession',
            'content' => $this->generatePossessionClause($data)
        ];
        
        $clauses[] = [
            'id' => 'default_remedies',
            'title' => 'Default and Remedies',
            'content' => $this->generateDefaultRemediesClause($data)
        ];
        
        $clauses[] = [
            'id' => 'governing_law',
            'title' => 'Governing Law',
            'content' => 'This Agreement shall be governed by and construed in accordance with the laws of Kenya.'
        ];
        
        return $clauses;
    }
    
    private function generateDefinitionsClause(array $data): string {
        return "In this Agreement, unless the context otherwise requires:
        (a) 'Property' means the land parcel described in Schedule A;
        (b) 'Purchase Price' means the sum of KES " . number_format($data['price'] ?? 0, 2) . ";
        (c) 'Completion Date' means " . ($data['completion_date'] ?? date('Y-m-d', strtotime('+30 days'))) . ";
        (d) 'Seller' means " . ($data['from_user_name'] ?? '') . ";
        (e) 'Buyer' means " . ($data['to_user_name'] ?? '') . ".";
    }
    
    private function generateContractConditions(array $data): array {
        $conditions = [
            [
                'type' => 'precedent',
                'description' => 'Verification of Seller\'s title',
                'deadline' => date('Y-m-d', strtotime('+7 days')),
                'responsible_party' => 'Buyer'
            ],
            [
                'type' => 'precedent',
                'description' => 'Payment of deposit',
                'deadline' => date('Y-m-d', strtotime('+3 days')),
                'responsible_party' => 'Buyer'
            ],
            [
                'type' => 'subsequent',
                'description' => 'Registration of transfer',
                'deadline' => date('Y-m-d', strtotime('+14 days after completion')),
                'responsible_party' => 'Both parties'
            ]
        ];
        
        // Add type-specific conditions
        $type = $data['transfer_type'] ?? 'sale';
        if ($type === 'lease') {
            $conditions[] = [
                'type' => 'precedent',
                'description' => 'Approval from Land Control Board',
                'deadline' => date('Y-m-d', strtotime('+21 days')),
                'responsible_party' => 'Seller'
            ];
        }
        
        return $conditions;
    }
    
    private function generateSolidityCode(array $contract): string {
        $variables = $contract['variables'];
        
        $solidity = <<<SOLIDITY
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

contract LandTransferContract {
    
    // Parties
    address public seller;
    address public buyer;
    address public witness1;
    address public witness2;
    address public governmentAuthority;
    
    // Land Details
    string public parcelNumber;
    string public location;
    uint256 public size; // in square meters
    uint256 public purchasePrice;
    
    // Contract Status
    enum ContractStatus { Draft, Signed, FundsEscrowed, Completed, Cancelled }
    ContractStatus public status;
    
    // Dates
    uint256 public agreementDate;
    uint256 public completionDeadline;
    uint256 public possessionDate;
    
    // Signatures
    mapping(address => bool) public signatures;
    
    // Conditions
    struct Condition {
        string description;
        bool fulfilled;
        uint256 deadline;
        address responsibleParty;
    }
    
    Condition[] public conditions;
    
    // Events
    event ContractSigned(address indexed signatory, uint256 timestamp);
    event ConditionFulfilled(uint256 conditionId, string description);
    event FundsDeposited(address indexed from, uint256 amount);
    event TransferCompleted(uint256 timestamp);
    event ContractCancelled(string reason);
    
    modifier onlyParties() {
        require(msg.sender == seller || msg.sender == buyer, "Not a party to contract");
        _;
    }
    
    modifier onlyGovernment() {
        require(msg.sender == governmentAuthority, "Not government authority");
        _;
    }
    
    constructor(
        address _seller,
        address _buyer,
        address _witness1,
        address _witness2,
        address _government,
        string memory _parcelNumber,
        string memory _location,
        uint256 _size,
        uint256 _price
    ) {
        seller = _seller;
        buyer = _buyer;
        witness1 = _witness1;
        witness2 = _witness2;
        governmentAuthority = _government;
        
        parcelNumber = _parcelNumber;
        location = _location;
        size = _size;
        purchasePrice = _price;
        
        agreementDate = block.timestamp;
        completionDeadline = block.timestamp + 30 days;
        possessionDate = block.timestamp + 30 days;
        
        status = ContractStatus.Draft;
        
        // Initialize conditions
        conditions.push(Condition({
            description: "Title verification completed",
            fulfilled: false,
            deadline: block.timestamp + 7 days,
            responsibleParty: buyer
        }));
        
        conditions.push(Condition({
            description: "Deposit payment made",
            fulfilled: false,
            deadline: block.timestamp + 3 days,
            responsibleParty: buyer
        }));
    }
    
    // Sign contract
    function signContract() public {
        require(msg.sender == seller || msg.sender == buyer || 
                msg.sender == witness1 || msg.sender == witness2, 
                "Not authorized to sign");
        
        signatures[msg.sender] = true;
        
        // Check if all required signatures are present
        bool allSigned = signatures[seller] && signatures[buyer] && 
                         signatures[witness1] && signatures[witness2];
        
        if (allSigned) {
            status = ContractStatus.Signed;
        }
        
        emit ContractSigned(msg.sender, block.timestamp);
    }
    
    // Deposit funds to escrow
    function depositFunds() public payable {
        require(msg.sender == buyer, "Only buyer can deposit funds");
        require(msg.value == purchasePrice, "Incorrect amount");
        require(status == ContractStatus.Signed, "Contract not signed");
        
        status = ContractStatus.FundsEscrowed;
        emit FundsDeposited(msg.sender, msg.value);
        
        // Mark deposit condition as fulfilled
        fulfillCondition(1);
    }
    
    // Fulfill condition
    function fulfillCondition(uint256 conditionId) public {
        require(conditionId < conditions.length, "Invalid condition");
        require(!conditions[conditionId].fulfilled, "Condition already fulfilled");
        require(block.timestamp <= conditions[conditionId].deadline, "Deadline passed");
        
        conditions[conditionId].fulfilled = true;
        emit ConditionFulfilled(conditionId, conditions[conditionId].description);
        
        // Check if all conditions are fulfilled
        if (allConditionsFulfilled()) {
            completeTransfer();
        }
    }
    
    // Complete transfer
    function completeTransfer() private {
        require(allConditionsFulfilled(), "Not all conditions fulfilled");
        require(status == ContractStatus.FundsEscrowed, "Funds not escrowed");
        
        // Release funds to seller
        payable(seller).transfer(address(this).balance);
        
        status = ContractStatus.Completed;
        emit TransferCompleted(block.timestamp);
    }
    
    // Government approval
    function approveTransfer() public onlyGovernment {
        require(status == ContractStatus.Signed, "Contract not signed");
        
        // Government-specific condition
        conditions.push(Condition({
            description: "Government approval received",
            fulfilled: true,
            deadline: block.timestamp,
            responsibleParty: governmentAuthority
        }));
    }
    
    // Cancel contract
    function cancelContract(string memory reason) public onlyParties {
        require(status != ContractStatus.Completed, "Contract already completed");
        
        // Refund buyer if funds deposited
        if (address(this).balance > 0) {
            payable(buyer).transfer(address(this).balance);
        }
        
        status = ContractStatus.Cancelled;
        emit ContractCancelled(reason);
    }
    
    // Helper functions
    function allConditionsFulfilled() public view returns (bool) {
        for (uint256 i = 0; i < conditions.length; i++) {
            if (!conditions[i].fulfilled) {
                return false;
            }
        }
        return true;
    }
    
    function getContractSummary() public view returns (
        address,
        address,
        string memory,
        uint256,
        ContractStatus
    ) {
        return (
            seller,
            buyer,
            parcelNumber,
            purchasePrice,
            status
        );
    }
    
    // Receive function
    receive() external payable {}
}
SOLIDITY;
        
        return $solidity;
    }
    
    /**
     * Analyze contract for legal compliance
     */
    public function analyzeContractCompliance(string $contractText, string $contractType = 'sale'): array {
        $analysis = [
            'compliance_score' => 0,
            'required_clauses' => [],
            'missing_clauses' => [],
            'risks' => [],
            'recommendations' => []
        ];
        
        // Define required clauses by contract type
        $requiredClauses = $this->getRequiredClauses($contractType);
        
        // Check for required clauses
        foreach ($requiredClauses as $clause) {
            if (stripos($contractText, $clause['keyword']) !== false) {
                $analysis['required_clauses'][] = $clause;
            } else {
                $analysis['missing_clauses'][] = $clause;
            }
        }
        
        // Calculate compliance score
        $totalClauses = count($requiredClauses);
        $foundClauses = count($analysis['required_clauses']);
        $analysis['compliance_score'] = $totalClauses > 0 ? ($foundClauses / $totalClauses) : 0;
        
        // Check for risky terms
        $analysis['risks'] = $this->identifyRisks($contractText);
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generateComplianceRecommendations($analysis);
        
        return $analysis;
    }
    
    private function getRequiredClauses(string $contractType): array {
        $clauses = [
            'sale' => [
                ['keyword' => 'warrant', 'name' => 'Warranty of Title', 'importance' => 'high'],
                ['keyword' => 'encumbrance', 'name' => 'No Encumbrance', 'importance' => 'high'],
                ['keyword' => 'possession', 'name' => 'Possession', 'importance' => 'medium'],
                ['keyword' => 'consideration', 'name' => 'Consideration', 'importance' => 'high'],
                ['keyword' => 'governing law', 'name' => 'Governing Law', 'importance' => 'medium']
            ],
            'lease' => [
                ['keyword' => 'term', 'name' => 'Lease Term', 'importance' => 'high'],
                ['keyword' => 'rent', 'name' => 'Rent Payment', 'importance' => 'high'],
                ['keyword' => 'security deposit', 'name' => 'Security Deposit', 'importance' => 'medium'],
                ['keyword' => 'maintenance', 'name' => 'Maintenance', 'importance' => 'medium'],
                ['keyword' => 'renewal', 'name' => 'Renewal Option', 'importance' => 'low']
            ]
        ];
        
        return $clauses[$contractType] ?? $clauses['sale'];
    }
    
    /**
     * Generate dispute resolution smart contract
     */
    public function generateDisputeResolutionContract(array $disputeData): array {
        $contract = [
            'parties' => [
                'claimant' => $disputeData['claimant'],
                'respondent' => $disputeData['respondent'],
                'arbitrators' => $disputeData['arbitrators'] ?? []
            ],
            'dispute' => [
                'description' => $disputeData['description'],
                'amount_claimed' => $disputeData['amount_claimed'] ?? 0,
                'dispute_type' => $disputeData['dispute_type'] ?? 'boundary'
            ],
            'resolution_mechanism' => 'smart_contract_arbitration',
            'terms' => $this->generateArbitrationTerms($disputeData)
        ];
        
        // Generate arbitration contract code
        $contract['solidity_code'] = $this->generateArbitrationContract($contract);
        
        return $contract;
    }
    
    /**
     * Auto-execute contract clauses based on conditions
     */
    public function autoExecuteClauses(array $contract, array $conditions): array {
        $executions = [];
        
        foreach ($contract['conditions'] as $index => $condition) {
            if ($this->isConditionMet($condition, $conditions)) {
                $executions[] = [
                    'condition_id' => $index,
                    'condition' => $condition['description'],
                    'action' => $this->determineAction($condition),
                    'executed_at' => date('Y-m-d H:i:s'),
                    'status' => 'executed'
                ];
            }
        }
        
        return $executions;
    }
    
    private function isConditionMet(array $condition, array $conditions): bool {
        // Check if condition deadline has passed
        if (isset($condition['deadline'])) {
            if (strtotime($condition['deadline']) < time()) {
                return true; // Deadline passed, condition considered unmet
            }
        }
        
        // Check against actual conditions
        foreach ($conditions as $actualCondition) {
            if (stripos($actualCondition['type'], $condition['type']) !== false) {
                return $actualCondition['met'] ?? false;
            }
        }
        
        return false;
    }
}
?>