<?php
session_start();

// Destroy all session data
$_SESSION = [];
session_unset();
session_destroy();

// Redirect back to login
header("Location: /Capstone-defense/auth/login.php");
exit;
?>
