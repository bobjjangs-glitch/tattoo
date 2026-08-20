<?php
require_once __DIR__ . '/includes/admin_auth.php';
unset($_SESSION['admin_id'], $_SESSION['admin_email'], $_SESSION['admin_name']);
header('Location: index.php');
exit;
