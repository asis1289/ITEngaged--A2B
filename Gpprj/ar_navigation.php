<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for user authentication
session_start();


// Database connection
require_once 'Connection/sql_auth.php';

// Fetch venue and POI data based on venue_id from URL
$venue_id = isset($_GET['venue_id']) ? (int)$_GET['venue_id'] : 0;
$venue_data = [];
$poi_data = [];
$coordinates_missing = false;

if ($venue_id > 0) {
    $query = "SELECT name, latitude, longitude FROM venues WHERE venue_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt === false) {
        echo "<h1>Error</h1><p>Failed to prepare venue query: " . htmlspecialchars($db->error) . "</p>";
        exit;
    }
    $stmt->bind_param("i", $venue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $venue_data = $result->fetch_assoc();
        // Check if latitude or longitude is missing
        if (is_null($venue_data['latitude']) || is_null($venue_data['longitude'])) {
            $coordinates_missing = true;
        }
    } else {
        echo "<h1>Error</h1><p>Venue with ID $venue_id not found.</p>";
        exit;
    }
    $stmt->close();

    if (!$coordinates_missing) {
        $query = "SELECT poi_id, poi_name, latitude, longitude FROM venue_layouts WHERE venue_id = ?";
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            echo "<h1>Error</h1><p>Failed to prepare POI query: " . htmlspecialchars($db->error) . "</p>";
            exit;
        }
        $stmt->bind_param("i", $venue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $poi_data[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AR Navigation - EventHub</title>
    <script src="https://aframe.io/releases/1.3.0/aframe.min.js"></script>
    <script src="https://raw.githack.com/AR-js-org/AR.js/3.4.5/aframe/build/aframe-ar.js"></script>
    <script src="ar_navigation.js" defer></script>
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; overflow: hidden; background: #000; }
        #ar-container { width: 100%; height: 100vh; display: none; }
        #loading { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 1.2rem; text-align: center; }
        #gps-prompt { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); background: rgba(0, 0, 0, 0.8); color: white; padding: 10px 20px; border-radius: 5px; z-index: 1000; display: none; }
        #laptop-warning { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255, 255, 255, 0.9); color: black; padding: 20px; border-radius: 10px; text-align: center; max-width: 90%; z-index: 1000; }
        #stop-ar-btn { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: #ff0000; color: white; padding: 10px 20px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; z-index: 1000; }
        #stop-ar-btn:hover { background: #cc0000; }
        #poi-selector { position: absolute; top: 60px; left: 50%; transform: translateX(-50%); background: rgba(0, 0, 0, 0.8); color: white; padding: 10px; border-radius: 5px; z-index: 1000; width: 90%; max-width: 300px; }
        #poi-selector select { width: 100%; padding: 5px; background: #333; color: white; border: none; border-radius: 5px; }
        #error-message { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255, 0, 0, 0.9); color: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 90%; z-index: 1000; }
        @media (hover: none) and (pointer: coarse) { /* Mobile devices */
            #gps-prompt { display: block; }
            #laptop-warning { display: none; }
        }
    </style>
</head>
<body>
    <?php if ($coordinates_missing): ?>
        <div id="error-message">
            <h2>No Navigation Coordinates</h2>
            <p>No navigation coordinates have been created for this venue: <?= htmlspecialchars($venue_data['name']) ?>.</p>
            <p>Please update the venue with latitude and longitude coordinates to enable AR navigation.</p>
            <p><a href="index.php" style="color: #fff; text-decoration: underline;">Return to Home</a></p>
        </div>
    <?php else: ?>
        <div id="loading">
            Loading AR Navigation... Please wait.
        </div>
        <div id="ar-container">
            <a-scene vr-mode-ui="enabled: false" arjs="sourceType: webcam; videoTexture: true; debugUIEnabled: false" renderer="antialias: true; alpha: true">
                <a-camera gps-new-camera="gpsMinDistance: 5"></a-camera>
                <!-- Venue marker will be added dynamically by JS -->
                <!-- POI markers will be added dynamically by JS -->
            </a-scene>
        </div>
        <div id="gps-prompt">
            Please enable GPS on your mobile device (Android/iOS) for AR navigation to work. Go to Settings > Location, turn it on, and refresh this page.
        </div>
        <div id="laptop-warning">
            <h2>AR Navigation Not Supported on Laptops/Computer</h2>
            <p>This AR navigation feature requires GPS and camera access, which are typically available on mobile devices (Android/iOS). Please use a mobile device to experience AR navigation.</p>
            <p><a href="index.php" style="color: #007bff; text-decoration: none;">Return to Home</a></p>
        </div>
        <div id="poi-selector">
            <label for="poi-select" style="margin-bottom: 5px; display: block;">Navigate to:</label>
            <select id="poi-select">
                <option value="all">Show All Points of Interest</option>
                <?php foreach ($poi_data as $poi): ?>
                    <option value="<?= htmlspecialchars($poi['poi_id']) ?>">
                        <?= htmlspecialchars($poi['poi_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button id="stop-ar-btn" style="display: none;">Stop AR Navigation</button>

        <script>
            // Pass PHP data to JavaScript
            const venueData = <?php echo json_encode($venue_data); ?>;
            const poiData = <?php echo json_encode($poi_data); ?>;
        </script>
    <?php endif; ?>
</body>
</html>
<?php $db->close(); ?>