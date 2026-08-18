<?php
// includes/flow_head.php
// 사이드바 없는 "진행형(flow)" 화면 전용 공용 헤더
// consent-select.php, consent-sign.php 에서만 사용
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - SalonForm' : 'SalonForm' ?></title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
</head>
<body class="flow-body">
