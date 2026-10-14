<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Include QR code library
require_once '../phpqrcode/qrlib.php';

// Check if session ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid session ID";
    redirect('sessions.php');
}

$session_id = $_GET['id'];

// Get session details
$stmt = $pdo->prepare("SELECT s.* FROM sessions s WHERE s.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    $_SESSION['error'] = "Session not found";
    redirect('sessions.php');
}

// Create qr_codes folder if it doesn't exist
$qr_folder = '../qr_codes/';
if (!file_exists($qr_folder)) {
    mkdir($qr_folder, 0777, true);
}

// Generate QR Code using phpqrcode (Local - No Internet Required)
$session_code = $session['session_code'];
// $qr_data = "http://localhost/attendance/student/scan.php?code=" . $session_code;
$qr_data = getQRUrl($session_code);
$qr_filename = 'qr_' . $session_code . '.png';
$qr_filepath = $qr_folder . $qr_filename;

// Delete old QR if exists
if (file_exists($qr_filepath)) {
    unlink($qr_filepath);
}

// Create QR code using phpqrcode
try {
    QRcode::png($qr_data, $qr_filepath, QR_ECLEVEL_L, 10);
    
    // Check if QR was created
    if (file_exists($qr_filepath)) {
        // Update session with QR image path
        $stmt = $pdo->prepare("UPDATE sessions SET qr_image = ? WHERE id = ?");
        $stmt->execute(['qr_codes/' . $qr_filename, $session_id]);
        $_SESSION['success'] = "QR Code generated successfully! (Local)";
    } else {
        $_SESSION['error'] = "Failed to generate QR code.";
    }
} catch(Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

redirect('sessions.php');
?>