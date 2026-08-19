<?php
require_once __DIR__ . '/includes/session.php';
$storeId = $_SESSION['staff_store_id'] ?? '';
unset($_SESSION['staff_id'], $_SESSION['staff_store_id'], $_SESSION['staff_name']);
header('Location: staff-login.php' . ($storeId ? '?store_id=' . urlencode($storeId) : ''));
exit;
