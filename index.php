<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once('config.inc.php');

//process logins: native
if (isset($_POST['username']) && isset($_POST['password'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    foreach ($login['native'] as $v) {
        // Check if password is hashed (starts with $2y$ for bcrypt)
        $isPasswordValid = false;
        if (isset($v['password_hash'])) {
            // New hashed password format
            $isPasswordValid = password_verify($password, $v['password_hash']);
        } elseif (isset($v['password'])) {
            // Legacy plaintext password (for backward compatibility)
            // WARNING: This is insecure and should be migrated to password_hash
            $isPasswordValid = hash_equals($v['password'], $password);
        }

        if ($v['user'] == $username && $isPasswordValid) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['active'] = true;
            $_SESSION['method'] = 'native';
            $_SESSION['user'] = $username;
            $_SESSION['login_time'] = time();
            break;
        }
    }
}
if (!isset($_SESSION['active']) || $_SESSION['active'] == false) {
    header("Location: login.php");
    exit;
} else {
    //include('fail2ban.php');
    header("Location: fail2ban.php");
    exit;
}
