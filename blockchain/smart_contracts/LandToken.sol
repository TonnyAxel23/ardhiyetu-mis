// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/token/ERC721/ERC721.sol";
import "@openzeppelin/contracts/token/ERC721/extensions/ERC721URIStorage.sol";

contract LandTitleNFT is ERC721, ERC721URIStorage {
    
    // Land NFT Structure
    struct LandNFT {
        uint256 tokenId;
        string parcelNumber;
        string location;
        uint256 size;
        string deedHash;
        uint256 registrationDate;
        address currentOwner;
        address previousOwner;
        string[] transferHistory;
    }
    
    // Mapping
    mapping(uint256 => LandNFT) public landNFTs;
    mapping(string => uint256) public parcelToTokenId;
    mapping(address => uint256[]) public ownerNFTs;
    
    uint256 private _nextTokenId;
    address public landRegistry;
    
    // Events
    event LandNFTMinted(uint256 tokenId, string parcelNumber, address owner);
    event LandNFTTransferred(uint256 tokenId, address from, address to);
    event LandVerified(uint256 tokenId, bool isValid);
    
    constructor(address _landRegistry) ERC721("ArdhiYetu Land Title", "ALAND") {
        landRegistry = _landRegistry;
        _nextTokenId = 1;
    }
    
    // Mint NFT for land
    function mintLandNFT(
        string memory parcelNumber,
        string memory location,
        uint256 size,
        string memory deedHash,
        string memory tokenURI
    ) public returns (uint256) {
        require(parcelToTokenId[parcelNumber] == 0, "NFT already exists for this parcel");
        
        uint256 tokenId = _nextTokenId++;
        
        LandNFT memory newNFT = LandNFT({
            tokenId: tokenId,
            parcelNumber: parcelNumber,
            location: location,
            size: size,
            deedHash: deedHash,
            registrationDate: block.timestamp,
            currentOwner: msg.sender,
            previousOwner: address(0),
            transferHistory: new string[](0)
        });
        
        landNFTs[tokenId] = newNFT;
        parcelToTokenId[parcelNumber] = tokenId;
        ownerNFTs[msg.sender].push(tokenId);
        
        _safeMint(msg.sender, tokenId);
        _setTokenURI(tokenId, tokenURI);
        
        emit LandNFTMinted(tokenId, parcelNumber, msg.sender);
        
        return tokenId;
    }
    
    // Transfer NFT with validation
    function transferLand(
        address from,
        address to,
        uint256 tokenId,
        string memory transferDocumentHash
    ) public {
        require(_isApprovedOrOwner(msg.sender, tokenId), "Not approved to transfer");
        require(ownerOf(tokenId) == from, "Not the current owner");
        
        // Add to transfer history
        landNFTs[tokenId].transferHistory.push(string(abi.encodePacked(
            "Transfer from ", 
            addressToString(from), 
            " to ", 
            addressToString(to),
            " at ",
            uintToString(block.timestamp),
            " - Document: ",
            transferDocumentHash
        )));
        
        landNFTs[tokenId].previousOwner = from;
        landNFTs[tokenId].currentOwner = to;
        
        // Update owner mappings
        removeFromOwnerList(from, tokenId);
        ownerNFTs[to].push(tokenId);
        
        safeTransferFrom(from, to, tokenId);
        
        emit LandNFTTransferred(tokenId, from, to);
    }
    
    // Verify land authenticity
    function verifyLand(uint256 tokenId) public view returns (bool) {
        return keccak256(bytes(landNFTs[tokenId].deedHash)) != keccak256(bytes(""));
    }
    
    // Get land history
    function getLandHistory(uint256 tokenId) public view returns (string[] memory) {
        return landNFTs[tokenId].transferHistory;
    }
    
    // Get owner's lands
    function getOwnerLands(address owner) public view returns (uint256[] memory) {
        return ownerNFTs[owner];
    }
    
    // Helper functions
    function removeFromOwnerList(address owner, uint256 tokenId) private {
        uint256[] storage tokens = ownerNFTs[owner];
        for (uint256 i = 0; i < tokens.length; i++) {
            if (tokens[i] == tokenId) {
                tokens[i] = tokens[tokens.length - 1];
                tokens.pop();
                break;
            }
        }
    }
    
    function addressToString(address _addr) private pure returns (string memory) {
        return toString(abi.encodePacked(_addr));
    }
    
    function uintToString(uint256 value) private pure returns (string memory) {
        return toString(abi.encodePacked(value));
    }
    
    function toString(bytes memory data) private pure returns (string memory) {
        bytes memory alphabet = "0123456789abcdef";
        bytes memory str = new bytes(2 + data.length * 2);
        str[0] = "0";
        str[1] = "x";
        for (uint256 i = 0; i < data.length; i++) {
            str[2+i*2] = alphabet[uint8(data[i] >> 4)];
            str[3+i*2] = alphabet[uint8(data[i] & 0x0f)];
        }
        return string(str);
    }
    
    // Override required functions
    function tokenURI(uint256 tokenId)
        public
        view
        override(ERC721, ERC721URIStorage)
        returns (string memory)
    {
        return super.tokenURI(tokenId);
    }
    
    function supportsInterface(bytes4 interfaceId)
        public
        view
        override(ERC721, ERC721URIStorage)
        returns (bool)
    {
        return super.supportsInterface(interfaceId);
    }
}