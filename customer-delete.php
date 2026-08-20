<?php
/**
 * customer-delete.php
 * 고객 삭제 처리 (소프트 삭제 — deleted_at만 기록, 실제 데이터는 보존)
 * 삭제는 대표(owner) 또는 관리자 권한 직원(admin)만 허용
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();

$storeId    = $_POST['id'] ?? '';
$customerId = $_POST['customer_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $storeId === '' || $customerId === '') {
    http_response_code(400);
    die('잘못된 요청입니다.');
}

$actor = requireStoreAccess($pdo, $storeId);
requireAdminRole($actor, $storeId);

$stmt = $pdo->prepare('SELECT id, name FROM ss_customers WHERE id = ? AND store_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$customerId, $storeId]);
$customer = $stmt->fetch();
if (!$customer) {
    header('Location: store.php?id=' . urlencode($storeId) . '&delete_error=1');
    exit;
}

try {
    $pdo->beginTransaction();

    // 연결된 서명 완료 동의서도 함께 소프트 삭제 (실제 데이터는 보존됨)
    $delDocs = $pdo->prepare('UPDATE ss_consent_documents SET deleted_at = NOW() WHERE customer_id = ? AND store_id = ? AND deleted_at IS NULL');
    $delDocs->execute([$customerId, $storeId]);

    $delCustomer = $pdo->prepare('UPDATE ss_customers SET deleted_at = NOW() WHERE id = ? AND store_id = ?');
    $delCustomer->execute([$customerId, $storeId]);

    $pdo->commit();

    logAccess($pdo, $storeId, $actor, 'delete_customer', 'customer', $customerId, $customer['name']);

    header('Location: store.php?id=' . urlencode($storeId) . '&deleted=1');
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[customer delete] ' . $e->getMessage());
    header('Location: store.php?id=' . urlencode($storeId) . '&delete_error=1');
    exit;
}
