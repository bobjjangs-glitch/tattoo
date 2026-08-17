<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - SalonForm' : 'SalonForm'; ?></title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
</head>
<body>
