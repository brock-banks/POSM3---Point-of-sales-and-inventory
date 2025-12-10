<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

auth_logout();
header('Location: /POSM3/public/login.php');
exit;