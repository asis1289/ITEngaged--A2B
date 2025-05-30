<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for user authentication
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit;
}

// Check user type and redirect if not authorized
$user_type = $_SESSION['user_type'];
if (!in_array($user_type, ['system_admin', 'venue_admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Database connection
require_once 'Connection/sql_auth.php';

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch venue data from URL parameters
$venue_id = isset($_GET['venue_id']) ? (int)$_GET['venue_id'] : 0;
$name = isset($_GET['name']) ? urldecode($_GET['name']) : '';
$address = isset($_GET['address']) ? urldecode($_GET['address']) : '';
$capacity = isset($_GET['capacity']) ? (int)$_GET['capacity'] : 0;
$venue_admin_id = isset($_GET['venue_admin_id']) ? (int)$_GET['venue_admin_id'] : null;
$image_path = null; // Initialize image_path as null
$latitude = null; // Initialize latitude
$longitude = null; // Initialize longitude
$poi_data = []; // Initialize array to hold points of interest

// Validate venue_id exists in the database
$venue_exists = false;
if ($venue_id > 0) {
    $query = "SELECT * FROM venues WHERE venue_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt === false) {
        $message = "Failed to prepare select statement: " . $db->error;
        $message_type = 'error';
    } else {
        $stmt->bind_param("i", $venue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $venue_exists = true;
            $venue = $result->fetch_assoc();
            $name = $venue['name'];
            $address = $venue['address'];
            $capacity = $venue['capacity'];
            $venue_admin_id = $venue['venue_admin_id'];
            $image_path = $venue['image_path'] ?? null;
            $latitude = $venue['latitude'] ?? null;
            $longitude = $venue['longitude'] ?? null;
        } else {
            $message = "Venue not found.";
            $message_type = 'error';
        }
        $stmt->close();
    }

    // Fetch existing points of interest from venue_layouts
    if ($venue_exists) {
        $query = "SELECT poi_id, poi_name, latitude, longitude FROM venue_layouts WHERE venue_id = ?";
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            $message = "Failed to prepare venue layout select statement: " . $db->error;
            $message_type = 'error';
        } else {
            $stmt->bind_param("i", $venue_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $poi_data[] = $row;
            }
            $stmt->close();
        }
    }
}

// Handle Edit Venue Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_venue'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token.";
        $message_type = 'error';
    } else {
        $venue_id = (int)$_POST['venue_id'];
        $name = $db->real_escape_string($_POST['name']);
        $address = $db->real_escape_string($_POST['address']);
        $capacity = (int)$_POST['capacity'];
        $venue_admin_id = $user_type === 'system_admin' && isset($_POST['venue_admin_id']) ? (int)$_POST['venue_admin_id'] : $user_id;
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        // Handle image upload
        $image_path_value = $image_path; // Start with the existing image_path
        if (!empty($_FILES['image']['name']) && empty($message)) {
            $target_dir = "images/venues/";
            $target_file = $target_dir . basename($_FILES['image']['name']);
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            $check = getimagesize($_FILES['image']['tmp_name']);
            if ($check === false) {
                $message = "File is not an image.";
                $message_type = 'error';
            } elseif (!in_array($imageFileType, $allowed_types)) {
                $message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                $message_type = 'error';
            } elseif ($_FILES['image']['size'] > 5000000) {
                $message = "Image size must be less than 5MB.";
                $message_type = 'error';
            } else {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_path_value = $target_file;
                } else {
                    $message = "Error uploading image: " . (function_exists('error_get_last') ? error_get_last()['message'] : 'Unknown error');
                    $message_type = 'error';
                }
            }
        }

        if ($capacity < 1) {
            $message = "Capacity must be a positive number.";
            $message_type = 'error';
        } else if (empty($message)) {
            if ($venue_id > 0 && $venue_exists) {
                // Start transaction to ensure data consistency
                $db->begin_transaction();

                try {
                    // Update the venue
                    $query = "UPDATE venues SET name = ?, address = ?, capacity = ?, venue_admin_id = ?, image_path = ?, latitude = ?, longitude = ? WHERE venue_id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt === false) {
                        throw new Exception("Failed to prepare update statement: " . $db->error);
                    }
                    $stmt->bind_param("ssiisddi", $name, $address, $capacity, $venue_admin_id, $image_path_value, $latitude, $longitude, $venue_id);
                    if (!$stmt->execute()) {
                        throw new Exception("Error executing update: " . $db->error);
                    }
                    $stmt->close();

                    // Handle multiple new points of interest
                    $new_poi_count = isset($_POST['poi_count']) ? (int)$_POST['poi_count'] : 1;
                    for ($i = 0; $i < $new_poi_count; $i++) {
                        $poi_name = !empty($_POST['new_poi_name_' . $i]) ? trim($_POST['new_poi_name_' . $i]) : null;
                        $poi_latitude = !empty($_POST['new_poi_latitude_' . $i]) ? (float)$_POST['new_poi_latitude_' . $i] : null;
                        $poi_longitude = !empty($_POST['new_poi_longitude_' . $i]) ? (float)$_POST['new_poi_longitude_' . $i] : null;

                        if ($poi_name && $poi_latitude !== null && $poi_longitude !== null) {
                            $query = "INSERT INTO venue_layouts (venue_id, poi_name, latitude, longitude) VALUES (?, ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            if ($stmt === false) {
                                throw new Exception("Failed to prepare venue layout insert statement: " . $db->error);
                            }
                            $stmt->bind_param("isdd", $venue_id, $poi_name, $poi_latitude, $poi_longitude);
                            if (!$stmt->execute()) {
                                throw new Exception("Error executing venue layout insert: " . $db->error);
                            }
                            $stmt->close();
                        }
                    }

                    // Refresh poi_data
                    $query = "SELECT poi_id, poi_name, latitude, longitude FROM venue_layouts WHERE venue_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->bind_param("i", $venue_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $poi_data = [];
                    while ($row = $result->fetch_assoc()) {
                        $poi_data[] = $row;
                    }
                    $stmt->close();

                    // Commit transaction
                    $db->commit();
                    $message = "Venue updated successfully!" . ($new_poi_count > 1 ? " " . $new_poi_count . " new points of interest added." : ($new_poi_count === 1 && $poi_name ? " New point of interest added." : ""));
                    $message_type = 'success';
                    $image_path = $image_path_value;
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollback();
                    $message = $e->getMessage();
                    $message_type = 'error';
                }
            } else {
                $message = "Invalid venue ID for update.";
                $message_type = 'error';
            }
        }
    }
}

// Fetch Venue Admins for Dropdown (for system_admin)
$venue_admins = [];
if ($user_type === 'system_admin') {
    $result = $db->query("SELECT va.venue_admin_id, va.full_name 
                          FROM venue_admins va 
                          JOIN users u ON va.username = u.username AND u.user_type = 'venue_admin'");
    if ($result === false) {
        $message = "Error fetching venue admins: " . $db->error;
        $message_type = 'error';
    } else {
        while ($row = $result->fetch_assoc()) {
            $venue_admins[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Venue - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-bg: #28a745;
            --error-bg: #ff2e63;
            --add-btn-bg: #28a745; /* Green background for Add Another button */
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('images/concert.jpg');
            background-size: cover;
            background-position: center;
            backdrop-filter: blur(5px);
            color: var(--light-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            text-align: center;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            display: flex;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 600px;
            text-align: center;
            position: relative;
            color: var(--light-color);
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .close-modal:hover {
            color: var(--accent-color);
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--light-color);
        }

        h3 {
            font-size: 1.2rem;
            margin: 1rem 0 0.5rem;
            color: var(--light-color);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            text-align: left;
        }

        input, select, textarea {
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            width: 100%;
            box-sizing: border-box;
        }

        input[type="file"] {
            padding: 0.5rem;
        }

        input::placeholder, textarea::placeholder, select {
            color: rgba(255, 255, 255, 0.7);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select option {
            background: var(--secondary-color);
            color: var(--light-color);
        }

        .image-preview {
            margin: 1rem 0;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 6px;
            object-fit: contain;
        }

        .hint {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.5rem;
            text-align: left;
        }

        button {
            background: var(--primary-color);
            color: var(--light-color);
            padding: 0.9rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 1.1rem;
        }

        button:hover {
            background: #5a3de6;
        }

        .add-poi-btn {
            background: var(--add-btn-bg);
            color: white;
            padding: 0.7rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 1rem;
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
        }

        .add-poi-btn:hover {
            background: #218838;
        }

        .message-box {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            z-index: 1002;
            box-shadow: var(--shadow);
            color: white;
            animation: fadeInOut 3s ease-in-out;
            max-width: 90%;
        }

        .message-success {
            background: var(--success-bg);
        }

        .message-error {
            background: var(--error-bg);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }

        @media (max-width: 768px) {
            .modal-content {
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            h3 {
                font-size: 1rem;
            }

            input, select, textarea, button {
                font-size: 0.9rem;
                padding: 0.7rem;
            }

            .message-box {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
            }

            .hint {
                font-size: 0.75rem;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.querySelector('.modal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const capacityInput = document.querySelector('input[name="capacity"]');
            const imageInput = document.querySelector('input[name="image"]');
            const imagePreview = document.getElementById('imagePreview');
            const poiContainer = document.getElementById('poi-container');
            const addPoiBtn = document.getElementById('add-poi-btn');

            // Show modal on load
            modal.style.display = 'flex';

            // Close modal with cross or outside click
            closeModalBtn.addEventListener('click', function() {
                window.location.href = 'view_current_venues.php';
            });

            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    window.location.href = 'view_current_venues.php';
                }
            });

            // Client-side validation for capacity input
            capacityInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value <= 0) this.value = '';
            });

            // Preview image on selection
            imageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `<img src="${e.target.result}" alt="Venue Image Preview">`;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Show existing image if available
            <?php if (!empty($image_path)): ?>
                imagePreview.innerHTML = `<img src="<?= htmlspecialchars($image_path) ?>" alt="Current Venue Image">`;
            <?php endif; ?>

            // Add new POI input fields
            let poiCount = 0;
            addPoiBtn.addEventListener('click', function() {
                const poiDiv = document.createElement('div');
                poiDiv.className = 'poi-group';
                poiDiv.innerHTML = `
                    <label for="new_poi_name_${poiCount}">Point of Interest Name:</label>
                    <input type="text" name="new_poi_name_${poiCount}" placeholder="e.g., Main Stage">
                    <label for="new_poi_latitude_${poiCount}">Latitude (Optional):</label>
                    <input type="number" step="any" name="new_poi_latitude_${poiCount}" placeholder="e.g., -37.814">
                    <label for="new_poi_longitude_${poiCount}">Longitude (Optional):</label>
                    <input type="number" step="any" name="new_poi_longitude_${poiCount}" placeholder="e.g., 144.96332">
                `;
                poiContainer.appendChild(poiDiv);
                poiCount++;
                document.querySelector('input[name="poi_count"]').value = poiCount;
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="modal">
            <div class="modal-content">
                <a href="view_current_venues.php" class="close-modal" id="closeModalBtn">×</a>
                <h2>Edit Venue</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="venue_id" value="<?= $venue_id ?>">
                    <input type="hidden" name="poi_count" value="0">
                    <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" placeholder="Venue Name" required>
                    <textarea name="address" placeholder="Address" required><?= htmlspecialchars($address) ?></textarea>
                    <input type="number" name="capacity" value="<?= $capacity ?>" placeholder="Capacity" required min="1" step="1">
                    <?php if ($latitude !== null && $longitude !== null): ?>
                        <label>Current Venue Coordinates: (Lat: <?= htmlspecialchars($latitude) ?>, Lon: <?= htmlspecialchars($longitude) ?>)</label>
                    <?php endif; ?>
                    <label for="latitude">Latitude (Optional):</label>
                    <input type="number" step="any" name="latitude" value="<?= htmlspecialchars($latitude ?? '') ?>" placeholder="e.g., -37.814">
                    <label for="longitude">Longitude (Optional):</label>
                    <input type="number" step="any" name="longitude" value="<?= htmlspecialchars($longitude ?? '') ?>" placeholder="e.g., 144.96332">
                    <div class="hint">
                        Latitude and longitude are optional. To update them, go to Google Maps, search for the venue's address, right-click on the location, and select the coordinates (e.g., -37.814, 144.96332). The first number is the latitude, the second is the longitude. You can update these later if needed.
                    </div>
                    <?php if ($user_type === 'system_admin'): ?>
                        <select name="venue_admin_id">
                            <option value="">Select Venue Admin (Optional)</option>
                            <?php foreach ($venue_admins as $admin): ?>
                                <option value="<?= $admin['venue_admin_id'] ?>" <?= $venue_admin_id == $admin['venue_admin_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['full_name']) ?> (ID: <?= $admin['venue_admin_id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="venue_admin_id" value="<?= $user_id ?>">
                    <?php endif; ?>
                    <label for="image">Image (Location of image: images/venues)</label>
                    <?php if (!empty($image_path)): ?>
                        <label>Current Image Path: <?= htmlspecialchars($image_path) ?></label>
                    <?php endif; ?>
                    <input type="file" name="image" id="image" accept="image/*">
                    <div class="image-preview" id="imagePreview"></div>

                    <!-- Venue Layout Fields -->
                    <h3>Venue Layout (Optional)</h3>
                    <div id="poi-container">
                        <!-- Initial POI fields will be added dynamically -->
                    </div>
                    <button type="button" id="add-poi-btn" class="add-poi-btn">Add Another Point of Interest</button>
                    <div class="hint">
                        Latitude and longitude are optional. To add them, go to Google Maps, search for the location within the venue, right-click on the spot, and select the coordinates (e.g., -37.814, 144.96332). The first number is the latitude, the second is the longitude. Use the venue's coordinates as a reference and adjust slightly for points of interest (e.g., add/subtract 0.0001). You can add these later if needed.
                    </div>
                    <?php if (!empty($poi_data)): ?>
                        <h4>Existing Points of Interest:</h4>
                        <ul style="text-align: left; list-style-type: none; padding-left: 0;">
                            <?php foreach ($poi_data as $poi): ?>
                                <li><?= htmlspecialchars($poi['poi_name']) ?> (Lat: <?= $poi['latitude'] ?? 'N/A' ?>, Lon: <?= $poi['longitude'] ?? 'N/A' ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <button type="submit" name="edit_venue">Update Venue</button>
                </form>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
</body>
</html>
<?php $db->close(); ?>