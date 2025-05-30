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

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && isset($_POST['user_id'])) {
    $user_id_to_change = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long!";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match!";
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = '$hashed_password' WHERE user_id = $user_id_to_change";
        if ($db->query($query)) {
            $message = "Password changed successfully!";
            $message_type = 'success';
        } else {
            $message = "Error changing password: " . $db->error;
            $message_type = 'error';
        }
    }
    header("Location: change_password.php?user_id=$user_id_to_change");
    exit;
}

// Fetch User Details
$user = [];
if (isset($_GET['user_id'])) {
    $user_id_to_fetch = (int)$_GET['user_id'];
    $result = $db->query("SELECT user_id, username, full_name, user_type FROM users WHERE user_id = $user_id_to_fetch");
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
    <title>Change Password - EventHub</title>
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

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        input {
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .change-password-btn {
            background: var(--primary-color);
            color: var(--light-color);
            padding: 0.9rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 1.1rem;
            width: 100%;
        }

        .change-password-btn:hover {
            background: #5a3de6;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .modal-content p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .modal-btn {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .modal-btn.confirm {
            background: var(--success-bg);
            color: var(--light-color);
        }

        .modal-btn.confirm:hover {
            background: #218838;
        }

        .modal-btn.cancel {
            background: var(--secondary-color);
            color: var(--light-color);
        }

        .modal-btn.cancel:hover {
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

            input {
                font-size: 0.9rem;
                padding: 0.7rem;
            }

            .change-password-btn {
                font-size: 1rem;
                padding: 0.8rem;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .modal-content p {
                font-size: 1rem;
            }

            .modal-btn {
                padding: 0.5rem 1rem;
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
        <h2>Change Password</h2>
        <?php if (!empty($user)): ?>
            <form method="POST" id="changePasswordForm">
                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                <input type="password" name="new_password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                <button type="button" class="change-password-btn" onclick="showConfirmationModal()">Change Password</button>
            </form>
        <?php else: ?>
            <p class="message-error">User not found.</p>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal" id="confirmationModal">
        <div class="modal-content">
            <p>Are you sure you want to change the password for <?= htmlspecialchars($user['full_name'] ?? '') ?>?</p>
            <div class="modal-buttons">
                <button type="submit" form="changePasswordForm" name="change_password" class="modal-btn confirm">Yes</button>
                <button type="button" class="modal-btn cancel" onclick="closeConfirmationModal()">Cancel</button>
            </div>
        </div>
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
            const modal = document.getElementById('confirmationModal');
            if (event.target === modal) {
                closeConfirmationModal();
            }
        };

        function showConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            modal.style.display = 'flex';
        }

        function closeConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            modal.style.display = 'none';
        }
    </script>
</body>
</html>
<?php $db->close(); ?>