<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/includes/session.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
