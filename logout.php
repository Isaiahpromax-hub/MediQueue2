<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    logAudit(getUserId(), 'logout', 'User logged out');
}

session_unset();
session_destroy();
header('Location: login.php');
exit;
