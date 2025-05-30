<?php
session_start();

// Clear session data to prevent residual $success or $error
unset($_SESSION['success']);
unset($_SESSION['error']);

// Enable error reporting for debugging (output will go to logs, not the page)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

$error = '';
$success = '';
$show_form = true;

// Process form submission only if it's a fresh POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $country_code = trim($_POST['country_code'] ?? '');
    $phone_num = trim($_POST['phone_num'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($country_code) || empty($phone_num)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone_num)) {
        $error = "Phone number must be exactly 10 digits.";
    } else {
        // Check for duplicate username or email
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        if ($stmt === false) {
            $error = "Database prepare error: " . $db->error;
        } else {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username or email already exists.";
            }
            $stmt->close();
        }

        if (empty($error)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, phone_num, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            if ($stmt === false) {
                $error = "Database prepare error: " . $db->error;
            } else {
                $stmt->bind_param("sssss", $username, $email, $password_hash, $full_name, $phone_num);
                if ($stmt->execute()) {
                    $success = "Registration successful. Redirecting to login...";
                    $show_form = false; // Hide form after successful registration
                } else {
                    $error = "Registration failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Event Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
            --success-color: #28a745;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('images/concert.jpg');
            background-size: cover;
            background-position: center;
            backdrop-filter: blur(8px);
            color: var(--light-color);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }

        .message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 500px;
            z-index: 2000;
            text-align: center;
        }

        .error {
            color: var(--accent-color);
            font-size: 1.1rem;
            background: rgba(255, 46, 99, 0.25);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .success {
            color: white;
            background: var(--success-color);
            border-radius: 12px;
            padding: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
            opacity: 0;
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-bar {
            background: var(--secondary-color);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .nav-links a:hover {
            color: var(--primary-color);
            transform: scale(1.05);
        }

        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 2rem;
            position: relative;
        }

        .form-container {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.25);
            text-align: left;
            margin-right: 2rem;
            position: relative;
            z-index: 1;
            animation: slideInLeft 0.8s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        h2 {
            color: var(--light-color);
            margin-bottom: 2.5rem;
            font-size: 2.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.8rem;
        }

        label {
            display: flex;
            flex-direction: column;
            color: var(--light-color);
            font-size: 1.1rem;
            font-weight: 500;
        }

        input, select {
            padding: 1rem;
            border: none;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.25);
            color: var(--light-color);
            font-size: 1.2rem;
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        select {
            background: rgba(255, 255, 255, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        select option {
            background: var(--secondary-color);
            color: #ffffff;
        }

        input:focus, select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 3px var(--primary-color);
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .phone-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        .phone-group select {
            flex: 0 0 120px;
        }

        .phone-group input {
            flex: 1;
            min-width: 0;
        }

        .btn {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }

        .btn:hover {
            background: #5a3de6;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(107, 72, 255, 0.5);
        }

        .links {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 1.5rem;
            gap: 1rem;
            font-size: 1.1rem;
        }

        .links a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #5a3de6;
            text-decoration: underline;
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 500px;
            height: 500px;
        }

        .logo-container .hexagon-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            overflow: hidden;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: var(--shadow);
            animation: slideInRight 0.8s ease-out;
            transition: transform 0.3s ease;
        }

        .logo-container .hexagon-wrapper:hover {
            transform: scale(1.05);
        }

        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logo-container span {
            color: var(--light-color);
            font-size: 2rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .help-link {
            text-align: center;
            padding: 1.5rem 0;
            background: rgba(30, 30, 47, 0.9);
            color: var(--light-color);
            font-size: 1.1rem;
        }

        .help-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .help-link a:hover {
            color: #5a3de6;
            text-decoration: underline;
        }

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            text-align: center;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .social-links a {
            color: white;
            margin: 0 0.7rem;
            font-size: 1.4rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
        }

        @media (max-width: 1024px) {
            .main-content {
                flex-direction: column;
                gap: 2rem;
            }

            .form-container {
                margin-right: 0;
                margin-bottom: 2rem;
            }

            .logo-container {
                width: 400px;
                height: 400px;
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-direction: column;
                align-items: center;
                gap: 1.2rem;
            }

            .nav-links a {
                font-size: 1rem;
            }

            .main-content {
                padding: 1.5rem;
            }

            .form-container {
                padding: 2rem;
                width: 90%;
            }

            h2 {
                font-size: 2rem;
            }

            input, select, .btn {
                font-size: 1rem;
                padding: 0.8rem;
            }

            .error, .success {
                font-size: 0.9rem;
                padding: 0.8rem;
            }

            .links {
                gap: 1rem;
                margin-top: 1.5rem;
            }

            .logo-container {
                width: 300px;
                height: 300px;
            }

            .logo-container span {
                font-size: 1.5rem;
            }

            .help-link {
                padding: 1rem 0;
            }

            .footer-content {
                flex-direction: column;
                gap: 1rem;
            }

            .social-links {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Messages at the top -->
    <?php if (!empty($error) || !empty($success)): ?>
        <div class="message-container">
            <?php if (!empty($error)): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="success"><?= htmlspecialchars($success) ?></p>
                <script>
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 3000);
                </script>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="nav-bar">
        <div class="nav-container">
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="booking.php">Bookings</a></li>
                <li><a href="services.php">Services</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="form-container">
            <h2>Register for Event Hub</h2>
            <?php if ($show_form): ?>
                <form method="POST" action="register.php">
                    <label>
                        Username
                        <input type="text" name="username" placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </label>
                    <label>
                        Email
                        <input type="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" placeholder="Enter your password" pattern=".{8,}" title="Password must be at least 8 characters" required>
                    </label>
                    <label>
                        Full Name
                        <input type="text" name="full_name" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    </label>
                    <label>
                        Phone Number
                        <div class="phone-group">
                            <select name="country_code" required>
                                <option value="+61" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+61') ? 'selected' : '' ?>>Australia (+61)</option>
                                <option value="+1" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+1') ? 'selected' : '' ?>>United States (+1)</option>
                                <option value="+44" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+44') ? 'selected' : '' ?>>United Kingdom (+44)</option>
                                <option value="+91" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+91') ? 'selected' : '' ?>>India (+91)</option>
                                <option value="+81" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+81') ? 'selected' : '' ?>>Japan (+81)</option>
                                <option value="+86" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+86') ? 'selected' : '' ?>>China (+86)</option>
                                <option value="+33" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+33') ? 'selected' : '' ?>>France (+33)</option>
                                <option value="+49" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+49') ? 'selected' : '' ?>>Germany (+49)</option>
                                <option value="+55" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+55') ? 'selected' : '' ?>>Brazil (+55)</option>
                                <option value="+27" <?= (isset($_POST['country_code']) && $_POST['country_code'] === '+27') ? 'selected' : '' ?>>South Africa (+27)</option>
                            </select>
                            <input type="text" name="phone_num" placeholder="Enter 10-digit phone number" pattern="[0-9]{10}" value="<?= htmlspecialchars($_POST['phone_num'] ?? '') ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                        </div>
                    </label>
                    <button type="submit" class="btn">Register</button>
                </form>
                <div class="links">
                    <span class="register-text">Already have an account?</span>
                    <br>
                    <a href="login.php" class="register-link">Login Now</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="logo-container">
            <a href="index.php">
                <div class="hexagon-wrapper">
                    <img src="images/logoreg.jpg" alt="Event Hub Logo" onerror="this.src=''; this.alt='Event Hub';">
                </div>
            </a>
            <span>Event Hub</span>
        </div>
    </div>

    <!-- Help Link -->
    <div class="help-link">
        <a href="contact.php">Need Help?</a>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <div class="social-links">
                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://whatsapp.com" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>
</body>
</html>
<?php $db->close(); ?>