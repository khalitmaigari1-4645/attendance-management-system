<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Check if session ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("No session ID provided");
}

$session_id = $_GET['id'];

// Get session details
$stmt = $pdo->prepare("SELECT s.* FROM sessions s WHERE s.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    die("Session not found");
}

echo "Session Code: " . $session['session_code'] . "<br>";

// Create qr_codes folder if it doesn't exist
$qr_folder = '../qr_codes/';
if (!file_exists($qr_folder)) {
    mkdir($qr_folder, 0777, true);
    echo "Created qr_codes folder<br>";
}

// Check if folder is writable
if (is_writable($qr_folder)) {
    echo "✅ qr_codes folder is writable<br>";
} else {
    echo "❌ qr_codes folder is NOT writable!<br>";
    echo "Run: sudo chmod 777 " . realpath($qr_folder) . "<br>";
}

// Generate QR Code
$session_code = $session['session_code'];
// $qr_data = "http://localhost/attendance/student/scan.php?code=" . $session_code;
$qr_data = getQRUrl($session_code);
$qr_filename = 'qr_' . $session_code . '.png';
$qr_filepath = $qr_folder . $qr_filename;

echo "QR Data: " . $qr_data . "<br>";
echo "QR Filepath: " . $qr_filepath . "<br>";

// Use Google API to generate QR
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
echo "QR URL: " . $qr_url . "<br>";

$qr_image = file_get_contents($qr_url);

if ($qr_image !== false) {
    echo "✅ QR image fetched successfully! Size: " . strlen($qr_image) . " bytes<br>";
    
    // Save the QR code
    $result = file_put_contents($qr_filepath, $qr_image);
    
    if ($result !== false) {
        echo "✅ File saved! Bytes written: " . $result . "<br>";
        
        // Check if file was created
        if (file_exists($qr_filepath)) {
            echo "✅ QR Code saved successfully!<br>";
            echo "File size: " . filesize($qr_filepath) . " bytes<br>";
            
            // Update session with QR image path
            $stmt = $pdo->prepare("UPDATE sessions SET qr_image = ? WHERE id = ?");
            $stmt->execute(['qr_codes/' . $qr_filename, $session_id]);
            
            echo "✅ Database updated!<br>";
            echo "<img src='../" . $qr_folder . $qr_filename . "'><br>";
            echo "<a href='sessions.php'>Go back to Sessions</a>";
        } else {
            echo "❌ File was not found after saving!";
        }
    } else {
        echo "❌ Failed to write file! Error: " . error_get_last()['message'];
    }
} else {
    echo "❌ Failed to get QR image from API";
}

// Show folder contents
echo "<br><br>Contents of qr_codes folder:<br>";
if (is_dir($qr_folder)) {
    $files = scandir($qr_folder);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . " (" . filesize($qr_folder . $file) . " bytes)<br>";
        }
    }
} else {
    echo "Folder does not exist!";
}
?>