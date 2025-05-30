<?php
session_start();

// Enable error reporting for debugging (output will go to logs, not the page)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $db->prepare("SELECT user_id, full_name, password, user_type FROM users WHERE email = ?");
        if ($stmt === false) {
            $error = "Database prepare error: " . $db->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($user_id, $full_name, $hashed_password, $user_type);
            $stmt->fetch();
            
            if ($stmt->num_rows > 0 && password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['user_type'] = $user_type;
                $success = "Login successful. Redirecting to your homepage.";
            } else {
                $error = "Invalid email or password.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Event Hub</title>
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

        .login-container {
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

        input {
            padding: 1rem;
            border: none;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.25);
            color: var(--light-color);
            font-size: 1.2rem;
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.35);
            box-shadow: 0 0 0 3px var(--primary-color);
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.7);
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

        .register-text {
            color: var(--light-color);
            font-size: 1.1rem;
        }

        .register-link {
            font-size: 1.4rem;
            font-weight: 700;
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

            .login-container {
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

            .login-container {
                padding: 2rem;
                width: 90%;
            }

            h2 {
                font-size: 2rem;
            }

            input, .btn {
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

            .register-text {
                font-size: 1rem;
            }

            .register-link {
                font-size: 1.2rem;
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
                        window.location.href = 'index.php';
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
        <div class="login-container">
            <h2>Login to Event Hub</h2>
            <?php if (empty($success)): ?>
                <form method="POST" action="login.php">
                    <label>
                        Email
                        <input type="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </label>
                    <button type="submit" class="btn">Login</button>
                </form>
                <div class="links">
                    <a href="forgot_password.php">Forgot Password? Reset Here</a>
                    <div>
                        <span class="register-text">Don't have an account?</span>
                        <br>
                        <a href="register.php" class="register-link">Register Now</a>
                    </div>
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
            <a href="terms_conditions"> Terms and conditions</a>
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