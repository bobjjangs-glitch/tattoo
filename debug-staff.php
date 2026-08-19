<?php
// 임시 진단용 파일 — 원인 확인 후 반드시 삭제할 것
require_once __DIR__ . '/api/config/database.php';
header('Content-Type: text/html; charset=utf-8');

$pdo = getDbConnection();
$storeId = $_GET['store_id'] ?? '';

echo "<h2>1. ss_store_staff 테이블 존재 여부</h2>";
try {
    $check = $pdo->query("SHOW TABLES LIKE 'ss_store_staff'")->fetchAll();
    echo $check ? "<p style='color:green'>테이블 존재함</p>" : "<p style='color:red'>테이블이 존재하지 않음</p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>확인 실패: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>2. ss_store_staff 전체 데이터 (최근 20건)</h2>";
try {
    $rows = $pdo->query("SELECT id, store_id, name, email, role, is_active, created_at FROM ss_store_staff ORDER BY created_at DESC LIMIT 20")->fetchAll();
    if (!$rows) {
        echo "<p style='color:red'>데이터가 0건입니다.</p>";
    } else {
        echo "<table border='1' cellpadding='6'><tr><th>store_id</th><th>name</th><th>email</th><th>role</th><th>is_active</th><th>created_at</th></tr>";
        foreach ($rows as $r) {
            echo "<tr><td>{$r['store_id']}</td><td>{$r['name']}</td><td>{$r['email']}</td><td>{$r['role']}</td><td>{$r['is_active']}</td><td>{$r['created_at']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red'>조회 실패: " . htmlspecialchars($e->getMessage()) . "</p>";
}

if ($storeId !== '') {
    echo "<h2>3. 지금 로그인 시도 중인 store_id로 직접 조회</h2>";
    echo "<p>조회한 store_id: <b>" . htmlspecialchars($storeId) . "</b></p>";
    $stmt = $pdo->prepare("SELECT * FROM ss_store_staff WHERE store_id = ?");
    $stmt->execute([$storeId]);
    $matched = $stmt->fetchAll();
    echo $matched ? "<p style='color:green'>이 store_id로 " . count($matched) . "건 조회됨</p>" : "<p style='color:red'>이 store_id로는 0건 조회됨 → store_id가 서로 다름</p>";
}

echo "<h2>4. ss_stores 테이블에서 실제 매장 ID 목록</h2>";
$stores = $pdo->query("SELECT id, name FROM ss_stores")->fetchAll();
echo "<table border='1' cellpadding='6'><tr><th>id</th><th>name</th></tr>";
foreach ($stores as $s) {
    echo "<tr><td>{$s['id']}</td><td>{$s['name']}</td></tr>";
}
echo "</table>";
