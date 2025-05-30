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

// Fetch User Details
$user = [];
if (isset($_GET['user_id'])) {
    $user_id_to_fetch = (int)$_GET['user_id'];
    $result = $db->query("SELECT user_id, username, full_name, email, phone_num, user_type FROM users WHERE user_id = $user_id_to_fetch");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User Details - EventHub</title>
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
            max-width: 500px;
            text-align: center;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
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

        .user-details {
            text-align: left;
            margin-bottom: 1.5rem;
        }

        .user-details p {
            font-size: 1rem;
            margin: 0.5rem 0;
        }

        .user-details p strong {
            color: var(--primary-color);
        }

        .back-btn {
            display: inline-block;
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
            .container {
                padding: 1.5rem;
            }

            .user-details p {
                font-size: 0.9rem;
            }

            .back-btn {
                padding: 0.7rem 1.5rem;
                font-size: 0.9rem;
            }

            .message-box {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="manage_users.php" class="close-container" id="closeContainerBtn">×</a>
        <h2>User Details</h2>
        <?php if (!empty($user)): ?>
            <div class="user-details">
                <p><strong>User ID:</strong> <?= htmlspecialchars($user['user_id']) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($user['full_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email'] ?? 'N/A') ?></p>
                <p><strong>Phone Number:</strong> <?= htmlspecialchars($user['phone_num'] ?? 'N/A') ?></p>
                <p><strong>User Type:</strong> <?= htmlspecialchars($user['user_type']) ?></p>
            </div>
            <a href="manage_users.php" class="back-btn">Back to Manage Users</a>
        <?php else: ?>
            <p class="message-error">User not found.</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <script>
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'manage_users.php';
        });

        window.onclick = function(event) {
            const container = document.querySelector('.container');
            if (event.target === container) {
                window.location.href = 'manage_users.php';
            }
        };
    </script>
</body>
</html>
<?php $db->close(); ?>