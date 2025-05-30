<?php
session_start();

// Database connection
require_once 'Connection/sql_auth.php';

// Initialize notification preference if not set
if (!isset($_SESSION['notification_preference'])) {
    $_SESSION['notification_preference'] = 'opt-in'; // Default to opt-in
}

$message = '';
$message_type = '';
$redirect = false;

if (!isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = $_SESSION['reset_user_id'];
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
        $message = "Password must be 8+ characters and match!";
        $message_type = "error";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
            $message_type = "success";
            $redirect = true;
            unset($_SESSION['reset_user_id']);
        } else {
            $message = "Error updating password: " . $db->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Fetch unread message count for the logged-in user
$unreadMessageCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $unreadQuery = "SELECT COUNT(*) as unread_count 
                    FROM admin_replies 
                    WHERE (user_id = ? OR user_id IS NULL) 
                    AND read_status = 0";
    $stmt = $db->prepare($unreadQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unreadResult = $stmt->get_result();
    if ($unreadResult) {
        $unreadData = $unreadResult->fetch_assoc();
        $unreadMessageCount = $unreadData['unread_count'];
    }
    $stmt->close();
}

// Fetch notification count for new approved events since last notifications_viewed
$unreadNotificationCount = 0;
if (isset($_SESSION['user_id']) && $_SESSION['notification_preference'] === 'opt-in') {
    $user_id = $_SESSION['user_id'];
    $notificationQuery = "SELECT COUNT(*) as notification_count 
                         FROM events e 
                         WHERE e.start_datetime > NOW() 
                         AND e.status = 'approved' 
                         AND e.created_by_type IN ('system_admin', 'venue_admin') 
                         AND e.created_at > (SELECT COALESCE(notifications_viewed, '1970-01-01 00:00:00') 
                                             FROM users 
                                             WHERE user_id = ?)";
    $stmt = $db->prepare($notificationQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notificationResult = $stmt->get_result();
    if ($notificationResult) {
        $notificationData = $notificationResult->fetch_assoc();
        $unreadNotificationCount = $notificationData['notification_count'];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-color: #28a745;
            --error-color: #dc3545;
            --message-color: #28a745;
            --notification-color: #ff9500;
            --disabled-color: #666;
            --messenger-blue: #0084FF;
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
            flex-direction: column;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        header {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .logo img {
            height: 80px;
            vertical-align: middle;
            transition: transform 0.2s ease;
        }

        .logo img:hover {
            transform: scale(1.15);
        }

        .nav-links {
            display: flex;
            list-style: none;
            align-items: center;
        }

        .nav-links li {
            margin-left: 1.5rem;
            position: relative;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .notification-icon {
            color: white;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .notification-icon:hover {
            color: var(--notification-color);
        }

        .notification-icon .unread-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            border: 1px solid white;
        }

        .user-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-actions a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .user-actions a:hover {
            color: var(--primary-color);
        }

        .user-actions .welcome-message {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .btn, .profile-btn, .message-btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .profile-btn, .message-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .profile-btn {
            background: var(--primary-color);
        }

        .message-btn {
            background: var(--message-color);
            position: relative;
        }

        .message-btn .unread-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            border: 1px solid white;
        }

        .btn:hover, .profile-btn:hover, .message-btn:hover {
            transform: translateY(-2px);
        }

        .message-btn:hover {
            background: #219653;
        }

        .profile-btn:hover {
            background: #5a3de6;
        }

        .btn:disabled, .btn.disabled {
            background: var(--disabled-color);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-dropdown-btn {
            background: var(--messenger-blue);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-dropdown-btn:hover {
            background: #006bd6;
            transform: translateY(-2px);
        }

        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: var(--secondary-color);
            min-width: 120px;
            box-shadow: var(--shadow);
            z-index: 1;
            border-radius: 4px;
            margin-top: 0.2rem;
        }

        .user-dropdown-content a {
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .user-dropdown-content a:hover {
            background: var(--primary-color);
        }

        .user-dropdown:hover .user-dropdown-content {
            display: block;
        }

        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            position: relative;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .popup {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 500px;
            position: relative;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .popup-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .popup-close:hover {
            color: var(--accent-color);
        }

        h2 {
            text-align: center;
            color: var(--light-color);
            margin-bottom: 1.5rem;
            font-size: 2rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--light-color);
        }

        input {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.2);
        }

        .instruction {
            color: var(--error-color);
            font-size: 0.9rem;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            text-align: center;
            transition: opacity 0.3s ease;
        }

        .message-box {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 25px;
            border-radius: 8px;
            font-size: 1rem;
            text-align: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
            box-shadow: var(--shadow);
        }

        .message-success {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            font-weight: 700;
            font-size: 1.1rem;
            animation: pulse 1.5s infinite;
        }

        .message-error {
            color: var(--error-color);
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .redirect-message {
            text-align: center;
            margin-top: 1rem;
            font-size: 1rem;
            color: var(--light-color);
        }

        .redirect-message a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .redirect-message a:hover {
            color: var(--accent-color);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .footer-links a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 500;
            margin: 0 1rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .social-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .social-links span {
            font-weight: 500;
        }

        .social-links a {
            color: white;
            margin: 0 0.5rem;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
            }

            .nav-links li {
                margin: 0.5rem 0;
            }

            .user-actions {
                align-items: flex-start;
                margin-top: 1rem;
            }

            .user-actions .welcome-message {
                margin-bottom: 0.5rem;
            }

            .message-btn, .profile-btn {
                margin-left: 0;
                margin-bottom: 0.5rem;
            }

            .notification-icon {
                font-size: 1.1rem;
            }

            .notification-icon .unread-count {
                font-size: 0.65rem;
                padding: 1px 5px;
            }

            .popup {
                width: 90%;
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.7rem;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .footer-links {
                margin-bottom: 1rem;
            }

            .social-links {
                margin-top: 1rem;
            }

            .user-dropdown-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                width: 100%;
                justify-content: flex-start;
            }

            .user-dropdown-content {
                right: auto;
                left: 0;
                min-width: 100%;
            }

            .user-dropdown-content a {
                font-size: 0.85rem;
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .message-btn, .profile-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .message-btn .unread-count, .notification-icon .unread-count {
                font-size: 0.6rem;
                padding: 1px 4px;
                top: -6px;
                right: -6px;
            }

            .footer-links a {
                margin: 0 0.5rem;
            }

            .user-dropdown-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .user-dropdown-content a {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const newPasswordInput = document.querySelector('input[name="new_password"]');
            const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
            const resetBtn = document.querySelector('button[name="reset_password"]');
            const instruction = document.querySelector('.instruction');

            function checkPassword() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const isValid = newPassword.length >= 8 && newPassword === confirmPassword;

                if (!isValid) {
                    if (newPassword.length < 8) {
                        instruction.textContent = "Password must be at least 8 characters.";
                    } else if (newPassword !== confirmPassword) {
                        instruction.textContent = "Passwords do not match.";
                    }
                    instruction.style.opacity = 1;
                } else {
                    instruction.style.opacity = 0;
                }

                resetBtn.disabled = !isValid;
                resetBtn.classList.toggle('disabled', !isValid);
            }

            newPasswordInput.addEventListener('input', checkPassword);
            confirmPasswordInput.addEventListener('input', checkPassword);

            const closePopup = document.querySelector('.popup-close');
            if (closePopup) {
                closePopup.addEventListener('click', () => {
                    window.location.href = 'forgot_password.php';
                });
            }

            const messageBox = document.querySelector(".message-box");
            if (messageBox) {
                setTimeout(() => {
                    messageBox.style.transition = "opacity 0.5s ease-out";
                    messageBox.style.opacity = "0";
                    setTimeout(() => {
                        messageBox.remove();
                        if (messageBox.classList.contains("message-success")) {
                            const redirectMessage = document.createElement('div');
                            redirectMessage.className = 'redirect-message';
                            redirectMessage.innerHTML = 'Redirect to Login Now<br><a href="login.php">Login Here</a>';
                            document.querySelector('.popup').appendChild(redirectMessage);
                            // Removed auto-redirect setTimeout
                        }
                    }, 500);
                }, 3000);
            }
        });
    </script>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <img src="images/a2b.png" alt="EventHub Logo" loading="lazy" onerror="this.src=''; this.alt='EventHub';">
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="booking.php">Bookings</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="about.php">About</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li>
                        <a href="view_notification_event.php" class="notification-icon">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadNotificationCount > 0 && $_SESSION['notification_preference'] === 'opt-in'): ?>
                                <span class="unread-count"><?= $unreadNotificationCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])): ?>
                    <li><a href="admin.php">Admin Panel</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="welcome-message">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                    <a href="view_admin_replies.php" class="message-btn">
                        <i class="fas fa-envelope"></i> Messages
                        <?php if ($unreadMessageCount > 0): ?>
                            <span class="unread-count"><?= $unreadMessageCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="user_profile.php" class="profile-btn"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <div class="user-dropdown">
                        <button class="user-dropdown-btn"><i class="fas fa-user"></i> User</button>
                        <div class="user-dropdown-content">
                            <a href="login.php">Login</a>
                            <a href="register.php">Register</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="popup-overlay">
            <div class="popup">
                <span class="popup-close"><i class="fas fa-times"></i></span>
                <h2>Reset Your Password</h2>
                <?php if ($message): ?>
                    <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <label>Type Your New Password:</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                    <label>Confirm Your New Password:</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                    <div class="instruction"></div>
                    <button type="submit" name="reset_password" class="btn disabled" disabled>Reset</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <a href="terms_conditions"> Terms and conditions</a>
            <div class="footer-links">
                <a href="login.php">Back to Login</a>
                <a href="register.php">Register</a>
                <a href="contact.php">Need Help?</a>
            </div>
            <div class="social-links">
                <span>Connect with us:</span>
                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://whatsapp.com" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>
</body>
</html>
<?php $db->close(); ?>