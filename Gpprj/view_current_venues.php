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

// Fetch Venues
$venues = [];
$result = $db->query("SELECT v.venue_id, v.name, v.address, v.capacity, v.venue_admin_id, va.full_name AS venue_admin_name 
                      FROM venues v 
                      LEFT JOIN venue_admins va ON v.venue_admin_id = va.venue_admin_id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $venues[] = $row;
    }
} else {
    $message = "Error fetching venues: " . htmlspecialchars($db->error);
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Current Venues - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff; /* Vibrant purple */
            --secondary-color: #1e1e2f; /* Dark navy */
            --accent-color: #ff2e63; /* Bright pink */
            --light-color: #f5f5f5; /* Off-white */
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-bg: #28a745; /* Green for success */
            --error-bg: #ff2e63; /* Red for error */
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

        .venue-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .venue-table th, .venue-table td {
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .venue-table th {
            background: var(--primary-color);
        }

        .edit-btn, .remove-btn {
            background: var(--primary-color);
            color: var(--light-color);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
            margin: 0 0.2rem;
        }

        .edit-btn:hover {
            background: #5a3de6;
        }

        .remove-btn {
            background: #dc3545;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
            font-size: 1.1rem;
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

        /* Confirmation Modal Styles */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1001;
            justify-content: center;
            align-items: center;
            display: none;
        }

        .confirm-modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 400px;
            text-align: center;
            color: var(--light-color);
        }

        .confirm-modal-content p {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .confirm-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .confirm-btn, .cancel-btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s ease;
        }

        .confirm-btn {
            background: #dc3545;
            color: var(--light-color);
        }

        .confirm-btn:hover {
            background: #c82333;
        }

        .cancel-btn {
            background: var(--primary-color);
            color: var(--light-color);
        }

        .cancel-btn:hover {
            background: #5a3de6;
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

            .venue-table {
                font-size: 0.9rem;
            }

            .venue-table td .edit-btn, .venue-table td .remove-btn {
                padding: 0.4rem 0.8rem;
            }

            .confirm-modal-content {
                padding: 1rem;
            }

            .confirm-modal-content p {
                font-size: 1rem;
            }

            .confirm-btn, .cancel-btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.querySelector('.modal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const editButtons = document.querySelectorAll('.edit-btn');
            const removeButtons = document.querySelectorAll('.remove-btn');
            const confirmModal = document.getElementById('confirmModal');
            const confirmBtn = document.getElementById('confirmDelete');
            const cancelBtn = document.getElementById('cancelDelete');
            let venueIdToDelete = null;

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

            // Handle edit button
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const venueId = this.getAttribute('data-venue-id');
                    const row = this.closest('tr');
                    const venueData = {
                        venue_id: venueId,
                        name: row.cells[1].textContent,
                        address: row.cells[2].textContent,
                        capacity: row.cells[3].textContent,
                        venue_admin_id: row.cells[4].textContent.split(' (ID: ')[1]?.replace(')', '') || ''
                    };
                    window.location.href = `edit_venue.php?venue_id=${venueId}&name=${encodeURIComponent(venueData.name)}&address=${encodeURIComponent(venueData.address)}&capacity=${encodeURIComponent(venueData.capacity)}&venue_admin_id=${encodeURIComponent(venueData.venue_admin_id)}`;
                });
            });

            // Handle remove button
            removeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    venueIdToDelete = this.getAttribute('data-venue-id');
                    confirmModal.style.display = 'flex';
                });
            });

            // Handle confirm delete
            confirmBtn.addEventListener('click', function() {
                if (venueIdToDelete) {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'delete_venue.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            confirmModal.style.display = 'none';
                            if (xhr.status === 200) {
                                let response;
                                try {
                                    response = JSON.parse(xhr.responseText);
                                } catch (e) {
                                    showMessage('Error: Invalid server response.', 'message-error');
                                    return;
                                }
                                if (response.success) {
                                    showMessage('Venue deleted successfully!!', 'message-success');
                                    setTimeout(() => {
                                        window.location.href = 'view_current_venues.php';
                                    }, 2000); // Redirect after 2 seconds
                                } else {
                                    showMessage(response.message || 'Failed to delete venue.', 'message-error');
                                }
                            } else {
                                showMessage('Error deleting venue: Server responded with status ' + xhr.status, 'message-error');
                            }
                        }
                    };
                    xhr.send(`venue_id=${venueIdToDelete}&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>`);
                }
                venueIdToDelete = null;
            });

            // Handle cancel delete
            cancelBtn.addEventListener('click', function() {
                confirmModal.style.display = 'none';
                venueIdToDelete = null;
            });

            // Close confirm modal when clicking outside
            confirmModal.addEventListener('click', function(event) {
                if (event.target === confirmModal) {
                    confirmModal.style.display = 'none';
                    venueIdToDelete = null;
                }
            });

            // Utility function to show messages
            function showMessage(text, className) {
                const messageBox = document.createElement('div');
                messageBox.className = `message-box ${className}`;
                messageBox.textContent = text;
                document.body.appendChild(messageBox);
                setTimeout(() => messageBox.remove(), 3000); // Remove message after 3 seconds
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="modal">
            <div class="modal-content">
                <a href="manage_venues.php" class="close-modal" id="closeModalBtn">×</a>
                <h2>View Current Venues</h2>
                <?php if (empty($venues)): ?>
                    <p class="no-data-message">No Venue at this time, add new venue.</p>
                <?php else: ?>
                    <table class="venue-table">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Capacity</th>
                            <th>Venue Admin</th>
                            <th>Action</th>
                        </tr>
                        <?php foreach ($venues as $venue): ?>
                            <tr>
                                <td><?= $venue['venue_id'] ?></td>
                                <td><?= htmlspecialchars($venue['name']) ?></td>
                                <td><?= htmlspecialchars($venue['address']) ?></td>
                                <td><?= $venue['capacity'] ?></td>
                                <td><?= $venue['venue_admin_id'] ? htmlspecialchars($venue['venue_admin_name'] . " (ID: " . $venue['venue_admin_id'] . ")") : 'N/A' ?></td>
                                <td>
                                    <button class="edit-btn" data-venue-id="<?= $venue['venue_id'] ?>">Edit</button>
                                    <button class="remove-btn" data-venue-id="<?= $venue['venue_id'] ?>">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div class="confirm-modal" id="confirmModal">
            <div class="confirm-modal-content">
                <p>Are you sure you want to delete this venue?</p>
                <div class="confirm-buttons">
                    <button class="confirm-btn" id="confirmDelete">Yes</button>
                    <button class="cancel-btn" id="cancelDelete">Cancel</button>
                </div>
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