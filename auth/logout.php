<?php
session_start();

// Store logout message
$_SESSION['logout_msg'] = "You have been logged out successfully.";

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>