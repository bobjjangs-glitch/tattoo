<?php
// gen-hash.php — 사용 후 반드시 즉시 삭제할 것
$newPassword = 'ssy201029@@'; // 원하는 새 관리자 비밀번호로 바꿔서 사용
echo password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
