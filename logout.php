<?php
// Initialize the session.
session_start();

// Load session management functions
require_once('session.inc.php');

// Safely destroy session and clean up cookies (including remember me)
session_destroy_safely();

// Redirect to login page
header("Location: index.php");
exit;
