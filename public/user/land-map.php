<?php
// File: public/user/land-map.php
require_once __DIR__ . '/../../includes/init.php';
require_login();

$user_id = $_SESSION['user_id'];

// Get user's land parcels with coordinates
$lands_sql = "SELECT * FROM land_records 
              WHERE owner_id = '$user_id' 
              AND latitude IS NOT NULL 
              AND longitude IS NOT NULL";
$lands_result = mysqli_query($conn, $lands_sql);

// Get all public lands for viewing (if allowed)
$public_lands_sql = "SELECT l.*, u.name as owner_name 
                     FROM land_records l 
                     JOIN users u ON l.owner_id = u.user_id 
                     WHERE l.is_public = 1 
                     AND l.latitude IS NOT NULL 
                     AND l.longitude IS NOT NULL";
$public_lands_result = mysqli_query($conn, $public_lands_sql);

// Google Maps API Key (store in config.php)
$google_maps_api_key = 'YOUR_GOOGLE_MAPS_API_KEY';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Map - ArdhiYetu</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/map.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS (free alternative to Google Maps) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet Geocoder CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        /* Ensure body and html allow scrolling */
        body, html {
            height: 100%;
            min-height: 100vh;
            overflow-y: auto;
            margin: 0;
            padding: 0;
        }
        
        /* Main container should expand with content */
        .map-container {
            padding: 20px 0;
            min-height: 1000px;
        }
        
        #map-container {
            height: 600px;
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .map-controls {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 200px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin: 5px 0;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #333;
        }
        
        .map-sidebar {
            height: 600px;
            overflow-y: auto;
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .parcel-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .parcel-item:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .parcel-active {
            border-color: #4CAF50;
            background: #f0fff4;
        }
        
        .coordinate-form {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .map-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
            padding-bottom: 10px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Ensure main content can expand */
        main {
            min-height: calc(100vh - 150px);
        }
        
        /* Search Bar Styles */
        .search-container {
            margin-bottom: 15px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-box button {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .search-box button:hover {
            background: #45a049;
        }
        
        .search-results {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 300px;
            overflow-y: auto;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1001;
            display: none;
        }
        
        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .search-result-item:hover {
            background: #f5f5f5;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .search-result-address {
            font-size: 12px;
            color: #666;
        }
        
        /* Map Search Control */
        .leaflet-control-geocoder {
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            #map-container {
                height: 400px;
                margin-bottom: 15px;
            }
            
            .map-sidebar {
                height: 400px;
                margin-top: 20px;
            }
            
            .row {
                flex-direction: column;
            }
            
            .col-8, .col-4 {
                width: 100%;
            }
            
            .legend {
                position: relative;
                bottom: auto;
                right: auto;
                margin-top: 10px;
                max-width: 100%;
            }
            
            .map-stats {
                flex-direction: column;
            }
            
            .stat-card {
                min-width: 100%;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-box button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation (same as other pages) -->
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="logo">
                <i class="fas fa-landmark"></i> ArdhiYetu
            </a>
            <div class="nav-links">
                <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="my-lands.php"><i class="fas fa-landmark"></i> My Lands</a>
                <a href="land-map.php" class="active"><i class="fas fa-map"></i> Land Map</a>
                <a href="transfer-land.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
                <a href="documents.php"><i class="fas fa-file-alt"></i> Documents</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <?php if (is_admin()): ?>
                    <a href="../admin/index.php" class="btn"><i class="fas fa-user-shield"></i> Admin</a>
                <?php endif; ?>
                <a href="../logout.php" class="btn logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="map-container">
        <div class="container">
            <div class="map-header">
                <h1><i class="fas fa-map-marked-alt"></i> Land Parcel Map</h1>
                <p>View your land parcels and public lands on an interactive map</p>
            </div>

            <div class="row">
                <div class="col-8">
                    <div class="map-controls">
                        <div class="row">
                            <div class="col-12">
                                <div class="search-container">
                                    <div class="form-group">
                                        <label><i class="fas fa-search"></i> Search Location:</label>
                                        <div class="search-box">
                                            <input type="text" 
                                                   id="search-input" 
                                                   class="form-control" 
                                                   placeholder="Enter place name, address, or coordinates (e.g., Nairobi, Kenya)">
                                            <button id="search-button" class="btn">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                        </div>
                                        <div id="search-results" class="search-results"></div>
                                        <small class="text-muted">Examples: "Nairobi", "Mombasa CBD", "-1.2921, 36.8219"</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label>View Type:</label>
                                    <div class="btn-group">
                                        <button id="view-my-lands" class="btn">
                                            <i class="fas fa-user"></i> My Lands
                                        </button>
                                        <button id="view-public" class="btn">
                                            <i class="fas fa-globe"></i> Public Lands
                                        </button>
                                        <button id="view-all" class="btn">
                                            <i class="fas fa-layer-group"></i> All
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Map Layer:</label>
                                    <select id="map-layer" class="form-control">
                                        <option value="streets">Streets</option>
                                        <option value="satellite">Satellite</option>
                                        <option value="topographic">Topographic</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="map-container"></div>
                    
                    <!-- Legend -->
                    <div class="legend">
                        <strong>Legend:</strong>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #4CAF50;"></div>
                            <span>Your Lands</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #2196F3;"></div>
                            <span>Public Lands</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: #FF9800;"></div>
                            <span>Government Lands</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-4">
                    <div class="map-sidebar">
                        <h3><i class="fas fa-list"></i> Land Parcels</h3>
                        <div id="parcels-list">
                            <!-- Dynamic content loaded via JavaScript -->
                        </div>
                        
                        <div class="coordinate-form">
                            <h4><i class="fas fa-crosshairs"></i> Add/Update Coordinates</h4>
                            <form id="coordinate-form">
                                <div class="form-group">
                                    <label>Select Land Parcel:</label>
                                    <select id="coordinate-parcel" class="form-control" required>
                                        <option value="">Select Parcel</option>
                                        <?php 
                                        mysqli_data_seek($lands_result, 0);
                                        while ($land = mysqli_fetch_assoc($lands_result)): 
                                        ?>
                                            <option value="<?php echo $land['record_id']; ?>">
                                                <?php echo htmlspecialchars($land['parcel_no']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Latitude:</label>
                                            <input type="number" 
                                                   id="latitude" 
                                                   step="any" 
                                                   class="form-control" 
                                                   placeholder="e.g., -1.286389"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label>Longitude:</label>
                                            <input type="number" 
                                                   id="longitude" 
                                                   step="any" 
                                                   class="form-control" 
                                                   placeholder="e.g., 36.817223"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="button" id="use-current-location" class="btn small">
                                        <i class="fas fa-location-arrow"></i> Use Current Location
                                    </button>
                                    <button type="button" id="pick-on-map" class="btn small">
                                        <i class="fas fa-map-marker-alt"></i> Pick on Map
                                    </button>
                                </div>
                                
                                <button type="submit" class="btn">
                                    <i class="fas fa-save"></i> Save Coordinates
                                </button>
                                <small>Note: Coordinates will be saved to your land record</small>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="map-stats">
                <div class="stat-card">
                    <h4><i class="fas fa-landmark"></i> My Lands on Map</h4>
                    <p class="stat-number"><?php echo mysqli_num_rows($lands_result); ?></p>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-globe"></i> Public Lands</h4>
                    <p class="stat-number"><?php echo mysqli_num_rows($public_lands_result); ?></p>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-map-marker-alt"></i> Total Markers</h4>
                    <p class="stat-number" id="total-markers">0</p>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-search"></i> Search History</h4>
                    <p class="stat-number" id="search-count">0</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal for Land Details -->
    <div id="land-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div id="modal-content"></div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> ArdhiYetu Land Management System. All rights reserved.</p>
            <p><i class="fas fa-envelope"></i> support@ardhiyetu.com | <i class="fas fa-phone"></i> +254 700 000 000</p>
        </div>
    </footer>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet Providers -->
    <script src="https://unpkg.com/leaflet-providers@latest/leaflet-providers.js"></script>
    <!-- Leaflet Geocoder -->
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    
    <script src="../../assets/js/script.js"></script>
    <script src="../../assets/js/map.js"></script>
    
    <script>
        // Initialize variables
        let map;
        let markers = [];
        let searchMarker = null;
        let geocoder;
        let searchHistory = [];
        const MAX_SEARCH_HISTORY = 10;
        
        let userLands = <?php 
            $user_lands = [];
            mysqli_data_seek($lands_result, 0);
            while ($land = mysqli_fetch_assoc($lands_result)) {
                $user_lands[] = $land;
            }
            echo json_encode($user_lands);
        ?>;
        
        let publicLands = <?php 
            $public_lands = [];
            while ($land = mysqli_fetch_assoc($public_lands_result)) {
                $public_lands[] = $land;
            }
            echo json_encode($public_lands);
        ?>;
        
        // Set default view based on what lands are available
        let defaultView = 'my-lands';
        if (userLands.length > 0) {
            defaultView = 'my-lands';
        } else if (publicLands.length > 0) {
            defaultView = 'public';
        } else {
            defaultView = 'all';
        }
        
        // Function to handle focus parameter from URL
        function handleFocusParameter() {
            const urlParams = new URLSearchParams(window.location.search);
            const focusLandId = urlParams.get('focus');
            
            if (focusLandId) {
                // Find the land in userLands or publicLands
                let land = userLands.find(l => l.record_id == focusLandId) || 
                           publicLands.find(l => l.record_id == focusLandId);
                
                if (land && land.latitude && land.longitude) {
                    // Zoom to the specific land
                    map.setView([land.latitude, land.longitude], 15);
                    
                    // Highlight in sidebar
                    setTimeout(() => {
                        highlightParcel(focusLandId);
                    }, 500);
                }
            }
        }
        
        // Update statistics display
        function updateStatistics() {
            document.getElementById('total-markers').textContent = markers.length;
            // Update any other statistics as needed
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map centered on Kenya
            map = L.map('map-container').setView([-1.286389, 36.817223], 7);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Initialize geocoder
            geocoder = L.Control.Geocoder.nominatim();
            
            // Add geocoder control to map
            L.Control.geocoder({
                defaultMarkGeocode: false
            })
            .on('markgeocode', function(e) {
                const latlng = e.geocode.center;
                map.setView(latlng, 15);
                showSearchResult(e.geocode);
            })
            .addTo(map);
            
            // Automatically plot all user lands on map load
            if (userLands.length > 0) {
                // Plot all user lands by default
                plotLands(userLands, 'user');
                updateSidebar(userLands);
                
                // Set "My Lands" as active by default
                document.getElementById('view-my-lands').classList.add('active');
                
                // Fit map bounds to show all user lands
                if (markers.length > 0) {
                    let group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.1));
                }
            } else {
                // If no user lands, plot public lands
                if (publicLands.length > 0) {
                    plotLands(publicLands, 'public');
                    updateSidebar(publicLands);
                    document.getElementById('view-public').classList.add('active');
                }
            }
            
            // Update statistics
            updateStatistics();
            
            // Handle focus parameter from URL
            handleFocusParameter();
            
            // Search button click event
            document.getElementById('search-button').addEventListener('click', searchLocation);
            
            // Search input enter key event
            document.getElementById('search-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchLocation();
                }
            });
            
            // Clear search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-container')) {
                    document.getElementById('search-results').style.display = 'none';
                }
            });
            
            // View type controls
            document.getElementById('view-my-lands').addEventListener('click', function() {
                clearMarkers();
                plotLands(userLands, 'user');
                updateSidebar(userLands);
                setActiveButton(this);
                // Fit bounds to show all user lands
                if (markers.length > 0) {
                    let group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds().pad(0.1));
                }
            });
            
            document.getElementById('view-public').addEventListener('click', function() {
                clearMarkers();
                plotLands(publicLands, 'public');
                updateSidebar(publicLands);
                setActiveButton(this);
            });
            
            document.getElementById('view-all').addEventListener('click', function() {
                clearMarkers();
                plotLands(userLands, 'user');
                plotLands(publicLands, 'public');
                updateSidebar([...userLands, ...publicLands]);
                setActiveButton(this);
            });
            
            // Map layer control
            document.getElementById('map-layer').addEventListener('change', function() {
                changeMapLayer(this.value);
            });
            
            // Use current location
            document.getElementById('use-current-location').addEventListener('click', function() {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        document.getElementById('latitude').value = position.coords.latitude;
                        document.getElementById('longitude').value = position.coords.longitude;
                        map.setView([position.coords.latitude, position.coords.longitude], 15);
                        
                        // Add marker for current location
                        if (searchMarker) {
                            map.removeLayer(searchMarker);
                        }
                        searchMarker = L.marker([position.coords.latitude, position.coords.longitude])
                            .addTo(map)
                            .bindPopup(`<b>Your Current Location</b><br>Lat: ${position.coords.latitude.toFixed(6)}<br>Lng: ${position.coords.longitude.toFixed(6)}`)
                            .openPopup();
                    });
                } else {
                    alert("Geolocation is not supported by this browser.");
                }
            });
            
            // Pick on map
            let marker;
            document.getElementById('pick-on-map').addEventListener('click', function() {
                alert("Click on the map to select a location");
                map.on('click', function(e) {
                    if (marker) {
                        map.removeLayer(marker);
                    }
                    marker = L.marker(e.latlng).addTo(map)
                        .bindPopup("Selected Location")
                        .openPopup();
                    
                    document.getElementById('latitude').value = e.latlng.lat;
                    document.getElementById('longitude').value = e.latlng.lng;
                    
                    // Remove click listener
                    map.off('click');
                });
            });
            
            // Save coordinates
            document.getElementById('coordinate-form').addEventListener('submit', function(e) {
                e.preventDefault();
                saveCoordinates();
            });
        });
        
        function searchLocation() {
            const query = document.getElementById('search-input').value.trim();
            if (!query) {
                alert('Please enter a location to search');
                return;
            }
            
            // Check if input is coordinates
            const coordMatch = query.match(/^(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)$/);
            if (coordMatch) {
                const lat = parseFloat(coordMatch[1]);
                const lng = parseFloat(coordMatch[2]);
                
                if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                    alert('Invalid coordinates. Please use format: latitude, longitude');
                    return;
                }
                
                map.setView([lat, lng], 15);
                showCoordinateResult(lat, lng, query);
                addToSearchHistory(`Coordinates: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                return;
            }
            
            // Show loading
            document.getElementById('search-results').innerHTML = '<div class="search-result-item">Searching...</div>';
            document.getElementById('search-results').style.display = 'block';
            
            // Perform geocoding search
            geocoder.geocode(query, function(results) {
                const resultsContainer = document.getElementById('search-results');
                resultsContainer.innerHTML = '';
                
                if (!results || results.length === 0) {
                    resultsContainer.innerHTML = '<div class="search-result-item">No results found</div>';
                    return;
                }
                
                // Display results
                results.slice(0, 5).forEach(result => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.innerHTML = `
                        <div class="search-result-name">${result.name || 'Unnamed Location'}</div>
                        <div class="search-result-address">${result.html || result.name || 'No address available'}</div>
                    `;
                    item.addEventListener('click', function() {
                        map.setView(result.center, 15);
                        showSearchResult(result);
                        resultsContainer.style.display = 'none';
                        document.getElementById('search-input').value = result.name || query;
                        addToSearchHistory(result.name || query);
                    });
                    resultsContainer.appendChild(item);
                });
                
                // Add to search history
                addToSearchHistory(query);
            });
        }
        
        function showSearchResult(result) {
            // Remove previous search marker
            if (searchMarker) {
                map.removeLayer(searchMarker);
            }
            
            // Add new marker
            searchMarker = L.marker(result.center)
                .addTo(map)
                .bindPopup(`
                    <div class="search-result-popup">
                        <h4>${result.name || 'Location Found'}</h4>
                        <p>${result.html || 'No additional information'}</p>
                        <p><strong>Coordinates:</strong> ${result.center.lat.toFixed(6)}, ${result.center.lng.toFixed(6)}</p>
                        <button onclick="saveSearchAsFavorite('${result.name || 'Location'}', ${result.center.lat}, ${result.center.lng})" class="btn small">
                            <i class="fas fa-star"></i> Save as Favorite
                        </button>
                    </div>
                `)
                .openPopup();
            
            // Add circle to highlight area
            L.circle(result.center, {
                color: '#ff7800',
                fillColor: '#ff7800',
                fillOpacity: 0.1,
                radius: 500
            }).addTo(map);
        }
        
        function showCoordinateResult(lat, lng, query) {
            // Remove previous search marker
            if (searchMarker) {
                map.removeLayer(searchMarker);
            }
            
            // Add new marker
            searchMarker = L.marker([lat, lng])
                .addTo(map)
                .bindPopup(`
                    <div class="search-result-popup">
                        <h4>Coordinates Found</h4>
                        <p><strong>Latitude:</strong> ${lat.toFixed(6)}</p>
                        <p><strong>Longitude:</strong> ${lng.toFixed(6)}</p>
                        <p><strong>Query:</strong> ${query}</p>
                    </div>
                `)
                .openPopup();
            
            // Add circle to highlight area
            L.circle([lat, lng], {
                color: '#4CAF50',
                fillColor: '#4CAF50',
                fillOpacity: 0.1,
                radius: 500
            }).addTo(map);
        }
        
        function addToSearchHistory(query) {
            // Add to beginning of array
            searchHistory.unshift({
                query: query,
                timestamp: new Date().toLocaleString()
            });
            
            // Keep only last MAX_SEARCH_HISTORY items
            if (searchHistory.length > MAX_SEARCH_HISTORY) {
                searchHistory.pop();
            }
            
            // Update search count display
            document.getElementById('search-count').textContent = searchHistory.length;
            
            // Optional: Save to localStorage
            try {
                localStorage.setItem('ardhiyetu_search_history', JSON.stringify(searchHistory));
            } catch (e) {
                console.log('Could not save search history to localStorage');
            }
        }
        
        function saveSearchAsFavorite(name, lat, lng) {
            const favoriteName = prompt('Enter a name for this favorite location:', name);
            if (!favoriteName) return;
            
            // Get existing favorites
            let favorites = [];
            try {
                favorites = JSON.parse(localStorage.getItem('ardhiyetu_favorite_locations') || '[]');
            } catch (e) {
                favorites = [];
            }
            
            // Add new favorite
            favorites.push({
                name: favoriteName,
                lat: lat,
                lng: lng,
                saved: new Date().toISOString()
            });
            
            // Save back to localStorage
            try {
                localStorage.setItem('ardhiyetu_favorite_locations', JSON.stringify(favorites));
                alert(`"${favoriteName}" saved to favorites!`);
            } catch (e) {
                alert('Could not save favorite. Local storage might be full.');
            }
        }
        
        function plotLands(lands, type) {
            let newMarkers = []; // Track new markers for this operation
            
            lands.forEach(land => {
                if (land.latitude && land.longitude) {
                    let iconColor = type === 'user' ? '#4CAF50' : '#2196F3';
                    let icon = L.divIcon({
                        html: `<div style="background-color: ${iconColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 10px rgba(0,0,0,0.5);"></div>`,
                        className: 'custom-marker',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });
                    
                    let marker = L.marker([land.latitude, land.longitude], { icon: icon })
                        .addTo(map)
                        .bindPopup(`
                            <div class="map-popup">
                                <h4>${land.parcel_no}</h4>
                                <p><strong>Owner:</strong> ${land.owner_name || 'You'}</p>
                                <p><strong>Location:</strong> ${land.location}</p>
                                <p><strong>Size:</strong> ${land.size} acres</p>
                                <p><strong>Status:</strong> ${land.status}</p>
                                <a href="land-details.php?id=${land.record_id}" class="btn small">View Details</a>
                            </div>
                        `);
                    
                    marker.landData = land;
                    markers.push(marker);
                    newMarkers.push(marker);
                }
            });
            
            document.getElementById('total-markers').textContent = markers.length;
            
            // Update statistics
            updateStatistics();
            
            // Only fit bounds if plotting specific lands, not when switching views
            if (newMarkers.length > 0) {
                let group = new L.featureGroup(newMarkers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }
        
        function clearMarkers() {
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];
            updateStatistics();
        }
        
        function updateSidebar(lands) {
            let html = '';
            lands.forEach(land => {
                html += `
                    <div class="parcel-item" data-id="${land.record_id}" 
                         data-lat="${land.latitude}" data-lng="${land.longitude}">
                        <h5>${land.parcel_no}</h5>
                        <p>${land.location}</p>
                        <p><small>${land.size} acres • ${land.status}</small></p>
                        <div class="parcel-actions">
                            <button class="btn small view-on-map" data-id="${land.record_id}">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn small get-directions" data-lat="${land.latitude}" data-lng="${land.longitude}">
                                <i class="fas fa-route"></i> Directions
                            </button>
                        </div>
                    </div>
                `;
            });
            
            document.getElementById('parcels-list').innerHTML = html || '<p>No lands found</p>';
            
            // Add event listeners
            document.querySelectorAll('.view-on-map').forEach(btn => {
                btn.addEventListener('click', function() {
                    const landId = this.dataset.id;
                    const land = lands.find(l => l.record_id == landId);
                    if (land && land.latitude) {
                        map.setView([land.latitude, land.longitude], 15);
                        highlightParcel(landId);
                    }
                });
            });
            
            document.querySelectorAll('.get-directions').forEach(btn => {
                btn.addEventListener('click', function() {
                    const lat = this.dataset.lat;
                    const lng = this.dataset.lng;
                    window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank');
                });
            });
        }
        
        function setActiveButton(btn) {
            document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
        
        function changeMapLayer(layer) {
            map.eachLayer(l => {
                if (l instanceof L.TileLayer) {
                    map.removeLayer(l);
                }
            });
            
            let tileUrl;
            switch(layer) {
                case 'satellite':
                    tileUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
                    break;
                case 'topographic':
                    tileUrl = 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png';
                    break;
                default:
                    tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
            }
            
            L.tileLayer(tileUrl).addTo(map);
        }
        
        function highlightParcel(landId) {
            document.querySelectorAll('.parcel-item').forEach(item => {
                item.classList.remove('parcel-active');
                if (item.dataset.id == landId) {
                    item.classList.add('parcel-active');
                    item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        }
        
        function saveCoordinates() {
            const parcelId = document.getElementById('coordinate-parcel').value;
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            
            if (!parcelId || !lat || !lng) {
                alert('Please fill all fields');
                return;
            }
            
            // Send AJAX request
            fetch('../../includes/ajax/save-coordinates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `record_id=${parcelId}&latitude=${lat}&longitude=${lng}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Coordinates saved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving coordinates');
            });
        }
        
        // Load search history from localStorage on page load
        try {
            const savedHistory = localStorage.getItem('ardhiyetu_search_history');
            if (savedHistory) {
                searchHistory = JSON.parse(savedHistory);
                document.getElementById('search-count').textContent = searchHistory.length;
            }
        } catch (e) {
            console.log('Could not load search history from localStorage');
        }
    </script>
</body>
</html>