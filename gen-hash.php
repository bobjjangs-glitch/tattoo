<?php
// gen-hash.php - 임시용, 사용 후 즉시 삭제
$plainPassword = 'ssy201029@@';
echo password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 12]);