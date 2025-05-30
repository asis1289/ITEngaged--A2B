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
if ($user_type !== 'system_admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Database connection
require_once 'Connection/sql_auth.php';

// Fetch Users
$users = [];
$result = $db->query("SELECT user_id, username, full_name, email, user_type FROM users");
if ($result) while ($row = $result->fetch_assoc()) $users[] = $row;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - EventHub</title>
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
            --danger-color: #dc3545;
            --highlight-bg: #ffeb3b;
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
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 900px;
            text-align: center;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close-container {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .close-container:hover {
            color: var(--accent-color);
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--light-color);
        }

        .search-bar {
            margin-bottom: 1.5rem;
        }

        .search-bar input {
            padding: 0.8rem;
            width: 100%;
            max-width: 300px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            box-sizing: border-box;
        }

        .search-bar input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .user-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .user-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .user-card.highlight {
            background: var(--highlight-bg);
            font-weight: bold;
            animation: highlightFade 2s ease-out forwards;
        }

        @keyframes highlightFade {
            0% { background: var(--highlight-bg); }
            100% { background: var(--glass-bg); }
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .user-card.system-admin {
            background: rgba(107, 72, 255, 0.2);
            border-color: rgba(107, 72, 255, 0.5);
        }

        .user-info {
            flex: 1;
        }

        .user-info h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }

        .user-info p {
            margin: 0.2rem 0;
            font-size: 0.95rem;
        }

        .user-actions-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .view-details-btn {
            background: #17a2b8;
            color: var(--light-color);
        }

        .view-details-btn:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .edit-btn {
            background: #007bff;
            color: var(--light-color);
        }

        .edit-btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .delete-btn {
            background: var(--danger-color);
            color: var(--light-color);
        }

        .delete-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .change-password-btn {
            background: var(--success-bg);
            color: var(--light-color);
        }

        .change-password-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
            text-align: center;
        }

        .back-btn {
            display: inline-block;
            margin-top: 2rem;
            background: var(--secondary-color);
            color: var(--light-color);
            padding: 0.9rem 2rem;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #16182a;
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
            .user-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .user-actions-buttons {
                width: 100%;
                justify-content: space-between;
            }

            .action-btn {
                width: 48%;
                text-align: center;
            }

            .search-bar input {
                max-width: 100%;
            }

            .message-box {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
                max-width: 90%;
            }
        }

        @media (max-width: 480px) {
            .action-btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="close-container" id="closeContainerBtn">×</a>
        <h2>Current Users</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search by name, username, or email..." onkeyup="searchUsers()">
        </div>
        <div class="user-list" id="userList">
            <?php if (empty($users)): ?>
                <p class="no-data-message">No users available to manage.</p>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <div class="user-card <?= $user['user_type'] === 'system_admin' ? 'system-admin' : '' ?>" id="user-<?= $user['user_id'] ?>">
                        <div class="user-info">
                            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                            <p>Username: <?= htmlspecialchars($user['username']) ?></p>
                            <p>Email: <?= htmlspecialchars($user['email']) ?></p>
                            <p>Type: <?= htmlspecialchars($user['user_type']) ?></p>
                        </div>
                        <div class="user-actions-buttons">
                            <button class="action-btn view-details-btn" onclick="openViewDetails(<?= $user['user_id'] ?>)">View Details</button>
                            <?php if ($user['user_type'] !== 'system_admin'): ?>
                                <button class="action-btn edit-btn" onclick="openEditModal(<?= $user['user_id'] ?>)">Edit</button>
                                <button class="action-btn delete-btn" onclick="openDeleteModal(<?= $user['user_id'] ?>)">Delete</button>
                                <button class="action-btn change-password-btn" onclick="openChangePasswordModal(<?= $user['user_id'] ?>)">Change Password</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="admin.php" class="back-btn">Back to Admin Panel</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <script>
        // Close container with close button
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'admin.php';
        });

        // Close container if clicking outside
        window.onclick = function(event) {
            const container = document.querySelector('.container');
            if (event.target === container) {
                window.location.href = 'admin.php';
            }
        };

        function openViewDetails(userId) {
            window.location.href = `view_details_user.php?user_id=${userId}`;
        }

        function openEditModal(userId) {
            window.location.href = `edit_user.php?user_id=${userId}`;
        }

        function openDeleteModal(userId) {
            window.location.href = `delete_user.php?user_id=${userId}`;
        }

        function openChangePasswordModal(userId) {
            window.location.href = `change_password.php?user_id=${userId}`;
        }

        function searchUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const userCards = document.querySelectorAll('.user-card');
            let found = false;

            userCards.forEach(card => {
                const userId = card.id.replace('user-', '');
                const fullName = card.querySelector('h3').textContent.toLowerCase();
                const username = card.querySelector('p:nth-child(2)').textContent.replace('Username: ', '').toLowerCase();
                const email = card.querySelector('p:nth-child(3)').textContent.replace('Email: ', '').toLowerCase();

                if (fullName.includes(searchTerm) || username.includes(searchTerm) || email.includes(searchTerm)) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.classList.add('highlight');
                    found = true;
                    setTimeout(() => card.classList.remove('highlight'), 2000); // Remove highlight after 2 seconds
                } else {
                    card.classList.remove('highlight');
                }
            });

            if (!found && searchTerm) {
                alert(`No user found matching "${searchTerm}".`);
            }
        }
    </script>
</body>
</html>
<?php $db->close(); ?>