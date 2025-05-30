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

// Handle Add Venue Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_venue'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token.";
        $message_type = 'error';
    } else {
        $name = $db->real_escape_string($_POST['name']);
        $address = $db->real_escape_string($_POST['address']);
        $capacity = (int)$_POST['capacity'];
        $venue_admin_id = $user_type === 'system_admin' ? (int)$_POST['venue_admin_id'] : $user_id;
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        // Venue layout data (optional, multiple POIs)
        $poi_names = !empty($_POST['poi_names']) ? $_POST['poi_names'] : [];
        $poi_latitudes = !empty($_POST['poi_latitudes']) ? $_POST['poi_latitudes'] : [];
        $poi_longitudes = !empty($_POST['poi_longitudes']) ? $_POST['poi_longitudes'] : [];

        if ($capacity < 1) {
            $message = "Capacity must be a positive number.";
            $message_type = 'error';
        } else {
            // Handle image upload
            $image_path = null;
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
                        $image_path = $target_file;
                    } else {
                        $message = "Error uploading image.";
                        $message_type = 'error';
                    }
                }
            }

            if (empty($message)) {
                // Start transaction to ensure data consistency
                $db->begin_transaction();

                try {
                    // Insert the venue into the database
                    $query = "INSERT INTO venues (name, address, capacity, venue_admin_id, image_path, latitude, longitude) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt === false) {
                        throw new Exception("Failed to prepare venue insert statement: " . $db->error);
                    }
                    $stmt->bind_param("sssisdd", $name, $address, $capacity, $venue_admin_id, $image_path, $latitude, $longitude);
                    if (!$stmt->execute()) {
                        throw new Exception("Error executing venue insert: " . $db->error);
                    }
                    $venue_id = $db->insert_id; // Get the new venue_id
                    $stmt->close();

                    // Insert venue layout data if provided
                    $poi_count = 0;
                    for ($i = 0; $i < count($poi_names); $i++) {
                        $poi_name = !empty($poi_names[$i]) ? trim($poi_names[$i]) : null;
                        $poi_latitude = !empty($poi_latitudes[$i]) ? (float)$poi_latitudes[$i] : null;
                        $poi_longitude = !empty($poi_longitudes[$i]) ? (float)$poi_longitudes[$i] : null;

                        // Insert only if all fields are provided
                        if ($poi_name && $poi_latitude !== null && $poi_longitude !== null) {
                            $query = "INSERT INTO venue_layouts (venue_id, poi_name, latitude, longitude) 
                                      VALUES (?, ?, ?, ?)";
                            $stmt = $db->prepare($query);
                            if ($stmt === false) {
                                throw new Exception("Failed to prepare venue layout insert statement: " . $db->error);
                            }
                            $stmt->bind_param("isdd", $venue_id, $poi_name, $poi_latitude, $poi_longitude);
                            if (!$stmt->execute()) {
                                throw new Exception("Error executing venue layout insert: " . $db->error);
                            }
                            $stmt->close();
                            $poi_count++;
                        }
                    }

                    // Commit transaction
                    $db->commit();
                    $message = "Venue added successfully";
                    if ($poi_count > 0) {
                        $message .= " with new point of interest";
                        if ($poi_count > 1) {
                            $message .= "s";
                        }
                    }
                    $message .= ".";
                    $message_type = 'success';
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $db->rollback();
                    $message = $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// Fetch Venue Admins for Dropdown
$venue_admins = [];
if ($user_type === 'system_admin' || $user_type === 'venue_admin') {
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
    <title>Add Venue - EventHub</title>
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
            color: rgba(255, 255, 244, 0.8);
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

        .add-another-btn {
            background: #28a745;
            padding: 0.5rem;
            font-size: 0.9rem;
        }

        .add-another-btn:hover {
            background: #218838;
        }

        .poi-entry {
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
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
            const poiContainer = document.getElementById('poiContainer');
            const addAnotherBtn = document.getElementById('addAnotherBtn');

            // Show modal on load
            modal.style.display = 'flex';

            // Close modal with cross sign
            closeModalBtn.addEventListener('click', function() {
                window.location.href = 'manage_venues.php';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    window.location.href = 'manage_venues.php';
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

            // Add another POI entry
            let poiCount = 1;
            addAnotherBtn.addEventListener('click', function() {
                poiCount++;
                const newEntry = document.createElement('div');
                newEntry.className = 'poi-entry';
                newEntry.innerHTML = `
                    <label for="poi_names_${poiCount}">Point of Interest Name:</label>
                    <input type="text" name="poi_names[]" id="poi_names_${poiCount}" placeholder="e.g., Main Stage">
                    <label for="poi_latitudes_${poiCount}">Latitude (Optional):</label>
                    <input type="number" step="any" name="poi_latitudes[]" id="poi_latitudes_${poiCount}" placeholder="e.g., -37.814">
                    <label for="poi_longitudes_${poiCount}">Longitude (Optional):</label>
                    <input type="number" step="any" name="poi_longitudes[]" id="poi_longitudes_${poiCount}" placeholder="e.g., 144.96332">
                    <div class="hint">
                        Latitude and longitude are optional. To add them, go to Google Maps, search for the location within the venue, right-click on the spot, and select the coordinates (e.g., -37.814, 144.96332). The first number is the latitude, the second is the longitude. Use the venue's coordinates as a reference and adjust slightly for points of interest (e.g., add/subtract 0.0001). You can add these later if needed.
                    </div>
                `;
                poiContainer.appendChild(newEntry);
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="modal">
            <div class="modal-content">
                <a href="manage_venues.php" class="close-modal" id="closeModalBtn">×</a>
                <h2>Add New Venue</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="text" name="name" placeholder="Venue Name" required>
                    <textarea name="address" placeholder="Address" required></textarea>
                    <input type="number" name="capacity" placeholder="Capacity" required min="1" step="1">
                    <label for="latitude">Latitude (Optional):</label>
                    <input type="number" step="any" name="latitude" placeholder="e.g., -37.814">
                    <label for="longitude">Longitude (Optional):</label>
                    <input type="number" step="any" name="longitude" placeholder="e.g., 144.96332">
                    <div class="hint">
                        Latitude and longitude are optional. To add them, go to Google Maps, search for the venue's address, right-click on the location, and select the coordinates (e.g., -37.814, 144.96332). The first number is the latitude, the second is the longitude. You can add these later if needed.
                    </div>
                    <?php if ($user_type === 'venue_admin'): ?>
                        <select name="venue_admin_id" disabled>
                            <?php foreach ($venue_admins as $admin): ?>
                                <option value="<?= $admin['venue_admin_id'] ?>" <?= $admin['venue_admin_id'] == $user_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['full_name']) ?> (ID: <?= $admin['venue_admin_id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="venue_admin_id" value="<?= $user_id ?>">
                    <?php elseif ($user_type === 'system_admin'): ?>
                        <select name="venue_admin_id" required>
                            <option value="">Select Venue Admin</option>
                            <?php foreach ($venue_admins as $admin): ?>
                                <option value="<?= $admin['venue_admin_id'] ?>">
                                    <?= htmlspecialchars($admin['full_name']) ?> (ID: <?= $admin['venue_admin_id'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <label for="image">Image (Location of image: images/venues)</label>
                    <input type="file" name="image" id="image" accept="image/*">
                    <div class="image-preview" id="imagePreview"></div>

                    <!-- Venue Layout Fields -->
                    <h3>Venue Layout (Optional)</h3>
                    <div id="poiContainer">
                        <div class="poi-entry">
                            <label for="poi_names_1">Point of Interest Name:</label>
                            <input type="text" name="poi_names[]" id="poi_names_1" placeholder="e.g., Main Stage">
                            <label for="poi_latitudes_1">Latitude (Optional):</label>
                            <input type="number" step="any" name="poi_latitudes[]" id="poi_latitudes_1" placeholder="e.g., -37.814">
                            <label for="poi_longitudes_1">Longitude (Optional):</label>
                            <input type="number" step="any" name="poi_longitudes[]" id="poi_longitudes_1" placeholder="e.g., 144.96332">
                            <div class="hint">
                                Latitude and longitude are optional. To add them, go to Google Maps, search for the location within the venue, right-click on the spot, and select the coordinates (e.g., -37.814, 144.96332). The first number is the latitude, the second is the longitude. Use the venue's coordinates as a reference and adjust slightly for points of interest (e.g., add/subtract 0.0001). You can add these later if needed.
                            </div>
                        </div>
                    </div>
                    <button type="button" id="addAnotherBtn" class="add-another-btn">Add Another Point of Interest</button>

                    <button type="submit" name="add_venue">Add Venue</button>
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