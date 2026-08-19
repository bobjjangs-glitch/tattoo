<?php
/**
 * customer-delete.php
 * 고객 삭제 처리 (화면 없음, POST 요청만 처리 후 store.php로 리다이렉트)
 * 삭제는 되돌릴 수 없는 작업이므로 대표(owner) 또는 관리자 권한 직원(admin)만 허용
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
requireAdminRole($actor, $storeId); // owner/admin이 아니면 여기서 안내 화면을 띄우고 종료

$stmt = $pdo->prepare('SELECT id, name FROM ss_customers WHERE id = ? AND store_id = ? LIMIT 1');
$stmt->execute([$customerId, $storeId]);
$customer = $stmt->fetch();
if (!$customer) {
    header('Location: store.php?id=' . urlencode($storeId) . '&delete_error=1');
    exit;
}

try {
    $pdo->beginTransaction();

    // 고객을 삭제하기 전, 이 고객과 연결된 서명 완료 동의서 기록을 먼저 정리한다.
    // (서명 이미지 파일 자체는 남지만, DB상 참조가 끊긴 채로 남는 것을 막기 위함)
    $delDocs = $pdo->prepare('DELETE FROM ss_consent_documents WHERE customer_id = ? AND store_id = ?');
    $delDocs->execute([$customerId, $storeId]);

    $delCustomer = $pdo->prepare('DELETE FROM ss_customers WHERE id = ? AND store_id = ?');
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
