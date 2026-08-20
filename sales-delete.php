<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('허용되지 않은 요청입니다.');
}

$pdo = getDbConnection();
$storeId = $_POST['id'] ?? '';
$saleId = $_POST['sale_id'] ?? '';

if ($storeId === '' || $saleId === '') {
    http_response_code(400);
    die('필수 파라미터가 없습니다.');
}

$actor = requireStoreAccess($pdo, $storeId);
requireAdminRole($actor, $storeId); // 대표/관리자만 매출 삭제 가능

$stmt = $pdo->prepare('SELECT id, amount FROM ss_sales WHERE id = ? AND store_id = ?');
$stmt->execute([$saleId, $storeId]);
$sale = $stmt->fetch();
if (!$sale) {
    http_response_code(404);
    header('Location: sales.php?id=' . urlencode($storeId) . '&delete_error=1');
    exit;
}

try {
    $del = $pdo->prepare('DELETE FROM ss_sales WHERE id = ? AND store_id = ?');
    $del->execute([$saleId, $storeId]);
    logAccess($pdo, $storeId, $actor, 'delete_sale', 'sale', $saleId, number_format((int)$sale['amount']) . '원');
    header('Location: sales.php?id=' . urlencode($storeId) . '&deleted=1');
    exit;
} catch (Throwable $e) {
    error_log('[sales delete] ' . $e->getMessage());
    header('Location: sales.php?id=' . urlencode($storeId) . '&delete_error=1');
    exit;
}
