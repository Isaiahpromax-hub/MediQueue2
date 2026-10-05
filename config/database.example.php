<?php

/**
 * MediQueue database configuration example.
 *
 * Copy this file to database.php and enter your
 * actual credentials when setting up the project.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3000');
define('DB_NAME', 'mediqueue2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SMS_GATEWAY_URL', '');
define('SMS_API_KEY', '');
define('SMS_SENDER', 'MediQueue');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');
define('SMTP_FROM_NAME', 'MediQueue');

define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define(
    'GOOGLE_REDIRECT_URI',
    'http://localhost:8081/MediQueue2/api/google_auth.php'
);