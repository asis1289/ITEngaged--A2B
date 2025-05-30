<?php
// sql_auth.php
$is_local = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']);
if ($is_local) {
    $host = 'localhost';
    $dbname = 'event_db';
    $username = 'root';
    $password = ''; // Adjust to your local MySQL password
} else {
    $host = 'localhost';
    $dbname = 'event_db';
    $username = 'i9808830_vcbx1';
    $password = 'D.VqKeNmtPDPBMTHMmB97';
}
$db = new mysqli($host, $username, $password, $dbname);
if ($db->connect_error) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "<h1>Database Connection Error</h1><p>We're experiencing technical difficulties. Please try again later. Error: " . htmlspecialchars($db->connect_error) . "</p>";
    exit;
}
$db->set_charset('utf8mb4');
?>