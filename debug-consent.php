<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

echo "<h3>1. 현재 세션 상태</h3><pre>";
print_r($_SESSION);
echo "</pre>";

$storeId = $_GET['store_id'] ?? '';
echo "<h3>2. 조회할 store_id</h3><pre>" . htmlspecialchars($storeId) . "</pre>";

$pdo = getDbConnection();

echo "<h3>3. 오너 세션 체크</h3>";
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id FROM ss_stores WHERE id = ? AND owner_id = ?');
    $stmt->execute([$storeId, $_SESSION['user_id']]);
    $ownerMatch = $stmt->fetch();
    echo $ownerMatch ? "오너로 일치함 (owner_id=" . htmlspecialchars($_SESSION['user_id']) . ")" : "오너 세션은 있지만 이 매장 소유주가 아님";
} else {
    echo "오너 세션(user_id) 없음";
}

echo "<h3>4. 직원 세션 체크</h3>";
if (isset($_SESSION['staff_id'])) {
    echo "staff_id: " . htmlspecialchars($_SESSION['staff_id']) . "<br>";
    echo "staff_store_id (세션): " . htmlspecialchars($_SESSION['staff_store_id'] ?? '') . "<br>";
    echo "storeId (현재 요청): " . htmlspecialchars($storeId) . "<br>";
    echo "두 값 일치 여부: " . (($_SESSION['staff_store_id'] ?? '') === $storeId ? "일치함" : "불일치!") . "<br>";

    $stmt = $pdo->prepare('SELECT id, name, role, is_active FROM ss_store_staff WHERE id = ? AND store_id = ?');
    $stmt->execute([$_SESSION['staff_id'], $storeId]);
    $staff = $stmt->fetch();
    echo "<pre>DB 조회 결과: ";
    print_r($staff);
    echo "</pre>";
} else {
    echo "직원 세션(staff_id) 없음";
}

echo "<h3>5. staff_auth.php 파일 정보</h3>";
$f = __DIR__ . '/includes/staff_auth.php';
echo "수정 시각: " . date('Y-m-d H:i:s', filemtime($f)) . " / 크기: " . filesize($f) . " bytes<br>";
echo "requireAdminRole 함수 존재 여부: " . (function_exists('requireAdminRole') ? '없음(정의 전)' : '아직 로드 안 됨') . "<br>";
require_once __DIR__ . '/includes/staff_auth.php';
echo "staff_auth.php 로드 후 requireAdminRole 존재: " . (function_exists('requireAdminRole') ? '있음' : '없음(!!)');
