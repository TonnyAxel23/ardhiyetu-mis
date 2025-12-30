// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

contract LandRegistry {
    
    // Land Parcel Structure
    struct LandParcel {
        string parcelNumber;
        string location;
        uint256 size; // in square meters
        address currentOwner;
        address previousOwner;
        uint256 registrationDate;
        uint256 lastTransferDate;
        string ipfsHash; // Document hash
        bool isActive;
        bool hasEncumbrance;
        string[] historicalOwners;
    }
    
    // Land Transfer Request
    struct TransferRequest {
        uint256 requestId;
        string parcelNumber;
        address fromOwner;
        address toOwner;
        uint256 proposedPrice;
        uint256 requestDate;
        TransferStatus status;
        string ipfsDocumentHash;
        address[] witnesses;
        bool[] witnessApprovals;
    }
    
    enum TransferStatus { Pending, WitnessApproved, GovernmentApproved, Completed, Rejected }
    
    // Events
    event LandRegistered(string parcelNumber, address owner, uint256 timestamp);
    event TransferInitiated(uint256 requestId, string parcelNumber, address from, address to);
    event TransferCompleted(uint256 requestId, string parcelNumber, address newOwner);
    event OwnershipVerified(string parcelNumber, address verifiedOwner, uint256 timestamp);
    event DisputeLogged(string parcelNumber, string disputeDetails, address reporter);
    
    // Mappings
    mapping(string => LandParcel) public landRecords;
    mapping(uint256 => TransferRequest) public transferRequests;
    mapping(address => string[]) public ownerToLands;
    mapping(string => bool) public parcelExists;
    
    // Government authority address
    address public governmentAuthority;
    uint256 public requestCounter;
    
    modifier onlyGovernment() {
        require(msg.sender == governmentAuthority, "Only government can call this");
        _;
    }
    
    modifier onlyLandOwner(string memory parcelNumber) {
        require(landRecords[parcelNumber].currentOwner == msg.sender, "Not the land owner");
        _;
    }
    
    constructor() {
        governmentAuthority = msg.sender;
        requestCounter = 0;
    }
    
    // Register new land parcel
    function registerLand(
        string memory parcelNumber,
        string memory location,
        uint256 size,
        string memory ipfsHash
    ) public onlyGovernment {
        require(!parcelExists[parcelNumber], "Parcel already registered");
        
        LandParcel memory newParcel = LandParcel({
            parcelNumber: parcelNumber,
            location: location,
            size: size,
            currentOwner: governmentAuthority,
            previousOwner: address(0),
            registrationDate: block.timestamp,
            lastTransferDate: block.timestamp,
            ipfsHash: ipfsHash,
            isActive: true,
            hasEncumbrance: false,
            historicalOwners: new string[](0)
        });
        
        landRecords[parcelNumber] = newParcel;
        parcelExists[parcelNumber] = true;
        ownerToLands[governmentAuthority].push(parcelNumber);
        
        emit LandRegistered(parcelNumber, governmentAuthority, block.timestamp);
    }
    
    // Initiate land transfer
    function initiateTransfer(
        string memory parcelNumber,
        address toOwner,
        uint256 proposedPrice,
        string memory ipfsDocumentHash
    ) public onlyLandOwner(parcelNumber) returns (uint256) {
        require(landRecords[parcelNumber].isActive, "Land is not active");
        require(!landRecords[parcelNumber].hasEncumbrance, "Land has encumbrance");
        
        requestCounter++;
        
        TransferRequest memory newRequest = TransferRequest({
            requestId: requestCounter,
            parcelNumber: parcelNumber,
            fromOwner: msg.sender,
            toOwner: toOwner,
            proposedPrice: proposedPrice,
            requestDate: block.timestamp,
            status: TransferStatus.Pending,
            ipfsDocumentHash: ipfsDocumentHash,
            witnesses: new address[](3), // 3 witnesses required
            witnessApprovals: new bool[](3)
        });
        
        transferRequests[requestCounter] = newRequest;
        
        emit TransferInitiated(requestCounter, parcelNumber, msg.sender, toOwner);
        
        return requestCounter;
    }
    
    // Witness approval
    function approveTransfer(uint256 requestId, uint256 witnessIndex) public {
        TransferRequest storage request = transferRequests[requestId];
        require(request.status == TransferStatus.Pending, "Request not pending");
        require(witnessIndex < 3, "Invalid witness index");
        
        request.witnesses[witnessIndex] = msg.sender;
        request.witnessApprovals[witnessIndex] = true;
        
        // Check if all witnesses approved
        bool allApproved = true;
        for (uint256 i = 0; i < 3; i++) {
            if (!request.witnessApprovals[i]) {
                allApproved = false;
                break;
            }
        }
        
        if (allApproved) {
            request.status = TransferStatus.WitnessApproved;
        }
    }
    
    // Government approval
    function approveTransferByGovernment(uint256 requestId) public onlyGovernment {
        TransferRequest storage request = transferRequests[requestId];
        require(request.status == TransferStatus.WitnessApproved, "Not witness approved");
        
        request.status = TransferStatus.GovernmentApproved;
    }
    
    // Complete transfer
    function completeTransfer(uint256 requestId) public {
        TransferRequest storage request = transferRequests[requestId];
        require(request.status == TransferStatus.GovernmentApproved, "Not government approved");
        require(msg.sender == request.fromOwner, "Only transfer initiator can complete");
        
        // Update land record
        LandParcel storage parcel = landRecords[request.parcelNumber];
        parcel.previousOwner = parcel.currentOwner;
        parcel.currentOwner = request.toOwner;
        parcel.lastTransferDate = block.timestamp;
        
        // Update historical owners
        parcel.historicalOwners.push(addressToString(parcel.previousOwner));
        
        // Update owner mappings
        removeFromOwner(parcel.previousOwner, request.parcelNumber);
        ownerToLands[request.toOwner].push(request.parcelNumber);
        
        request.status = TransferStatus.Completed;
        
        emit TransferCompleted(requestId, request.parcelNumber, request.toOwner);
    }
    
    // Verify ownership
    function verifyOwnership(string memory parcelNumber, address claimedOwner) 
        public view returns (bool) {
        return landRecords[parcelNumber].currentOwner == claimedOwner;
    }
    
    // Get land history
    function getLandHistory(string memory parcelNumber) 
        public view returns (string[] memory) {
        return landRecords[parcelNumber].historicalOwners;
    }
    
    // Add encumbrance (mortgage, lien)
    function addEncumbrance(string memory parcelNumber, string memory encumbranceDetails) 
        public onlyGovernment {
        landRecords[parcelNumber].hasEncumbrance = true;
        // Emit event for encumbrance
    }
    
    // Remove encumbrance
    function removeEncumbrance(string memory parcelNumber) public onlyGovernment {
        landRecords[parcelNumber].hasEncumbrance = false;
    }
    
    // Helper function to remove land from owner's list
    function removeFromOwner(address owner, string memory parcelNumber) private {
        string[] storage lands = ownerToLands[owner];
        for (uint256 i = 0; i < lands.length; i++) {
            if (keccak256(bytes(lands[i])) == keccak256(bytes(parcelNumber))) {
                lands[i] = lands[lands.length - 1];
                lands.pop();
                break;
            }
        }
    }
    
    // Helper to convert address to string
    function addressToString(address _addr) private pure returns (string memory) {
        bytes32 value = bytes32(uint256(uint160(_addr)));
        bytes memory alphabet = "0123456789abcdef";
        
        bytes memory str = new bytes(42);
        str[0] = '0';
        str[1] = 'x';
        for (uint256 i = 0; i < 20; i++) {
            str[2+i*2] = alphabet[uint8(value[i + 12] >> 4)];
            str[3+i*2] = alphabet[uint8(value[i + 12] & 0x0f)];
        }
        return string(str);
    }
    
    // Get owner's lands
    function getOwnerLands(address owner) public view returns (string[] memory) {
        return ownerToLands[owner];
    }
}