<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['user_type'] !== 'system_admin' && $user['user_type'] !== 'venue_admin') {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Tickets - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-bg: #1b1b2f;
            --accent-color: #ff2e63;
            --text-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --success-bg: rgba(40, 167, 69, 0.9);
            --success-border: #28a745;
            --error-bg: rgba(220, 53, 53, 0.9); /* Red theme for errors */
            --error-border: #dc3545;
            --scan-line: #6b48ff;
            --focus-pulse: rgba(107, 72, 255, 0.5);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('images/2025.jpg');
            background-size: cover;
            background-position: center;
            backdrop-filter: blur(6px);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 90%;
            max-width: 700px;
            text-align: center;
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
        }

        .scanner {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            background: #000;
        }

        #video {
            width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }

        .scan-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 20px solid rgba(0, 0, 0, 0.6);
            box-sizing: border-box;
            pointer-events: none;
            background: radial-gradient(circle, transparent 20%, rgba(0, 0, 0, 0.5) 60%);
            display: none;
            z-index: 10;
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 3px;
            background: var(--scan-line);
            box-shadow: 0 0 10px var(--scan-line);
            animation: scan 1.5s linear infinite;
        }

        .focus-pulse {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: transparent;
            border: 3px solid var(--focus-pulse);
            transform: translate(-50%, -50%);
            animation: pulse 0.8s ease-in-out 2;
            pointer-events: none;
            z-index: 15;
            display: none;
        }

        @keyframes scan {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }

        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            100% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
        }

        #result {
            margin: 1.5rem 0;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: bold;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .success {
            background: var(--success-bg);
            border: 2px solid var(--success-border);
            color: var(--text-color);
            animation: pop 0.4s ease;
        }

        .error {
            background: var(--error-bg); /* Red theme */
            border: 2px solid var(--error-border); /* Red border */
            color: #fff; /* White text for contrast */
            animation: shake 0.4s ease;
        }

        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: var(--text-color);
            padding: 0.8rem 1.8rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            margin: 0.5rem;
            cursor: pointer;
            border: none;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .btn:hover {
            background: #5a3de6;
            transform: translateY(-3px);
        }

        #stop-btn {
            display: none;
        }

        .zoom-control {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .zoom-control label {
            font-size: 1rem;
            color: var(--text-color);
        }

        .zoom-control input[type="range"] {
            width: 150px;
            cursor: pointer;
        }

        .progress-bar {
            width: 100%;
            height: 5px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            margin-top: 1rem;
            overflow: hidden;
            display: none;
        }

        .progress {
            height: 100%;
            background: var(--primary-color);
            width: 0;
            transition: width 1.5s linear;
        }

        @keyframes pop {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
                width: 95%;
            }

            h2 {
                font-size: 1.6rem;
            }

            .scanner {
                max-width: 100%;
            }

            #result {
                font-size: 1.1rem;
            }

            .btn {
                padding: 0.7rem 1.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
    <script src="https://unpkg.com/@zxing/library@latest"></script>
    <script>
        let video = null;
        let codeReader = null;
        let isScanning = false;
        let lastScanned = null;
        let stream = null;
        let startBtn = null;
        let stopBtn = null;
        let resultDiv = null;
        let videoContainer = null;
        let scanOverlay = null;
        let focusPulse = null;
        let zoomLevel = 1;
        let lastScanTime = 0;
        const DEBOUNCE_TIME = 1000; // 1 second debounce

        function startScanner() {
            if (isScanning) {
                console.warn("Scanner already running");
                return;
            }
            isScanning = true;

            console.log("Starting scanner");
            setTimeout(() => {
                startBtn.style.display = "none";
                stopBtn.style.display = "inline-block";
                resultDiv.textContent = "Position QR code in view. Ensure good lighting and hold steady...";
                scanOverlay.style.display = "block";
                console.log("UI updated: Stop Scan visible, Scan Overlay visible");
            }, 0);

            video = document.createElement("video");
            videoContainer.innerHTML = "";
            videoContainer.appendChild(video);

            const isMobile = window.innerWidth <= 768;
            const constraints = {
                video: {
                    facingMode: isMobile ? "environment" : "user",
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                    focusMode: "continuous",
                    zoom: zoomLevel
                }
            };

            codeReader = new ZXing.BrowserMultiFormatReader(null, {
                hints: new Map([
                    [ZXing.DecodeHintType.TRY_HARDER, true],
                    [ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.QR_CODE]]
                ])
            });

            codeReader.decodeFromVideoDevice(null, video, (result, err) => {
                if (result) {
                    const currentTime = Date.now();
                    if (result.text !== lastScanned && (currentTime - lastScanTime) > DEBOUNCE_TIME) {
                        console.log("QR code detected, raw content:", result.text);
                        lastScanned = result.text;
                        lastScanTime = currentTime;
                        triggerFocusAnimation();
                        processScan(result.text);
                    }
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error("Scan error:", err);
                    showResult("Error scanning QR code: " + err.message, "error");
                    stopScanner();
                }
            }).catch(err => {
                console.error("Camera access error:", err);
                showResult("Error accessing camera: " + err.message, "error");
                stopScanner();
            });

            const zoomSlider = document.getElementById("zoom-slider");
            zoomSlider.addEventListener("input", (e) => {
                zoomLevel = parseFloat(e.target.value);
                if (stream) {
                    const track = stream.getVideoTracks()[0];
                    const capabilities = track.getCapabilities();
                    if ('zoom' in capabilities) {
                        track.applyConstraints({ advanced: [{ zoom: zoomLevel }] })
                            .then(() => console.log("Zoom set to:", zoomLevel))
                            .catch(err => console.error("Zoom error:", err));
                    } else {
                        console.warn("Zoom not supported on this device");
                    }
                }
            });
        }

        function stopScanner() {
            if (!isScanning) {
                console.warn("Scanner not running");
                return;
            }
            isScanning = false;

            console.log("Stopping scanner");
            setTimeout(() => {
                startBtn.style.display = "inline-block";
                stopBtn.style.display = "none";
                resultDiv.textContent = "Click Start Scan to begin";
                scanOverlay.style.display = "none";
                console.log("UI updated: Start Scan visible, Stop Scan hidden, Scan Overlay hidden");
            }, 0);

            if (codeReader) {
                codeReader.reset();
                codeReader = null;
            }
            if (video) {
                const tracks = video.srcObject ? video.srcObject.getTracks() : [];
                tracks.forEach(track => {
                    track.stop();
                    console.log("Stopped track:", track.label);
                });
                video.srcObject = null;
                video.pause();
                videoContainer.innerHTML = '<div id="video"></div>';
                video = null;
                console.log("Video element cleared");
            }
            hideProgressBar();
        }

        function processScan(bookingId) {
            console.log("Sending booking_id to server:", bookingId);
            resultDiv.textContent = "Processing scan...";
            showProgressBar();
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "process_scan.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    hideProgressBar();
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            showResult(response.message, response.status);
                            if (response.status === "success") {
                                playSuccessSound();
                                setTimeout(() => {
                                    resultDiv.textContent = "";
                                    startScanner();
                                }, 3000); // Display success message for 3 seconds
                            }
                        } catch (e) {
                            console.error("JSON parse error:", e);
                            showResult("Error parsing response: " + e.message, "error");
                        }
                    } else {
                        console.error("Request failed, status:", xhr.status);
                        showResult("Request failed: Server error (Status " + xhr.status + ")", "error");
                    }
                }
            };
            xhr.onerror = function() {
                console.error("Network error");
                hideProgressBar();
                showResult("Request failed: Network error", "error");
            };
            xhr.send("booking_id=" + encodeURIComponent(bookingId));
        }

        function showResult(message, status) {
            resultDiv.textContent = message;
            resultDiv.className = "result";
            if (status) {
                resultDiv.classList.add(status);
            }
        }

        function playSuccessSound() {
            const audio = new Audio('sounds/success_beep.mp3'); // Use local sound file
            audio.play().then(() => {
                console.log("Sound played successfully");
            }).catch(err => {
                console.error("Sound play error:", err);
                resultDiv.innerHTML = "✓ " + resultDiv.textContent;
            });
        }

        function showProgressBar() {
            const progressBar = document.querySelector(".progress-bar");
            const progress = document.querySelector(".progress");
            progressBar.style.display = "block";
            progress.style.width = "0";
            setTimeout(() => {
                progress.style.width = "100";
                setTimeout(hideProgressBar, 1500);
            }, 50);
        }

        function hideProgressBar() {
            const progressBar = document.querySelector(".progress-bar");
            progressBar.style.display = "none";
        }

        function triggerFocusAnimation() {
            focusPulse.style.display = "block";
            focusPulse.offsetWidth; // Force animation restart
        }

        document.addEventListener("DOMContentLoaded", function() {
            startBtn = document.getElementById("start-btn");
            stopBtn = document.getElementById("stop-btn");
            resultDiv = document.getElementById("result");
            videoContainer = document.getElementById("video");
            scanOverlay = document.querySelector(".scan-overlay");
            focusPulse = document.querySelector(".focus-pulse");
            startBtn.addEventListener("click", startScanner);
            stopBtn.addEventListener("click", stopScanner);
            resultDiv.textContent = "Click Start Scan to begin";
            console.log("Page loaded, Start Scan visible");
        });

        document.addEventListener("visibilitychange", () => {
            if (document.hidden && isScanning) {
                stopScanner();
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h2>Scan Event Ticket</h2>
        <div class="scanner">
            <div id="video"></div>
            <div class="scan-overlay">
                <div class="scan-line"></div>
                <div class="focus-pulse"></div>
            </div>
        </div>
        <div class="zoom-control">
            <label for="zoom-slider">Zoom:</label>
            <input type="range" id="zoom-slider" min="1" max="10" step="0.1" value="1">
        </div>
        <div id="result" class="result">Click Start Scan to begin</div>
        <div class="progress-bar">
            <div class="progress"></div>
        </div>
        <button id="start-btn" class="btn">Start Scan</button>
        <button id="stop-btn" class="btn">Stop Scan</button>
        <a href="admin.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
<?php $db->close(); ?>