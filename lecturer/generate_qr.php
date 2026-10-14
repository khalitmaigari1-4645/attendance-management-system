
<?php

require_once '../config/database.php';
require_once '../includes/functions.php';

// ============================================================
// CHECK LOGIN
// ============================================================

if (!isLoggedIn() || !isLecturer()) {
    redirect('../auth/login.php');
}


// ============================================================
// CHECK SESSION ID
// ============================================================

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid session ID";
    redirect('sessions.php');
}

$session_id = $_GET['id'];


// ============================================================
// GET SESSION
// Verify that this session belongs to the logged-in lecturer
// ============================================================

$stmt = $pdo->prepare("
    SELECT s.*
    FROM sessions s
    WHERE s.id = ?
    AND s.created_by = ?
");

$stmt->execute([
    $session_id,
    $_SESSION['user_id']
]);

$session = $stmt->fetch();

if (!$session) {
    $_SESSION['error'] =
        "Session not found or you don't have permission.";

    redirect('sessions.php');
}


// ============================================================
// LOAD LOCAL PHP QR CODE LIBRARY
// ============================================================

require_once '../phpqrcode/qrlib.php';


// ============================================================
// CREATE QR DIRECTORY
// ============================================================

$qr_folder = '../qr_codes/';

if (!is_dir($qr_folder)) {

    if (!mkdir($qr_folder, 0777, true)) {

        $_SESSION['error'] =
            "Unable to create QR code directory.";

        redirect('sessions.php');
    }
}


// ============================================================
// CREATE QR DATA
// ============================================================

$session_code = $session['session_code'];

/*
 * getQRUrl() uses your local LAN IP.
 *
 * Example:
 *
 * http://192.168.223.125/attendance/student/scan.php?code=SES...
 *
 * The QR itself is generated locally by phpqrcode.
 */

$qr_data = getQRUrl($session_code);


// ============================================================
// QR FILE NAME
// ============================================================

$qr_filename = 'qr_' . $session_code . '.png';

$qr_filepath = $qr_folder . $qr_filename;


// ============================================================
// DELETE OLD QR CODE
// ============================================================

if (file_exists($qr_filepath)) {

    if (!unlink($qr_filepath)) {

        $_SESSION['error'] =
            "Unable to replace existing QR code.";

        redirect('sessions.php');
    }
}


// ============================================================
// GENERATE QR CODE LOCALLY
// ============================================================

try {

    /*
     * QRcode::png()
     *
     * This does NOT use the internet.
     *
     * phpqrcode generates the PNG directly on your XAMPP
     * server.
     */

    QRcode::png(
        $qr_data,
        $qr_filepath,
        QR_ECLEVEL_L,
        10,
        2
    );


    // ========================================================
    // VERIFY QR FILE
    // ========================================================

    if (file_exists($qr_filepath) && filesize($qr_filepath) > 0) {

        // ====================================================
        // SAVE QR PATH TO DATABASE
        // ====================================================

        $stmt = $pdo->prepare("
            UPDATE sessions
            SET qr_image = ?
            WHERE id = ?
        ");

        $stmt->execute([
            'qr_codes/' . $qr_filename,
            $session_id
        ]);


        $_SESSION['success'] =
            "QR Code generated successfully!";


    } else {

        $_SESSION['error'] =
            "Failed to generate QR code.";

    }


} catch (Throwable $e) {

    $_SESSION['error'] =
        "QR generation error: " . $e->getMessage();
}


// ============================================================
// RETURN TO SESSIONS
// ============================================================

redirect('sessions.php');

?>
