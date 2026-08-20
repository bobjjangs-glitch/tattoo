<?php
/**
 * consent-document-delete.php
 * 서명 완료된 동의서 1건 삭제 처리 (소프트 삭제 — deleted_at만 기록)
 * 법적 증빙 자료를 다루므로 서명 이미지 파일은 삭제하지 않고 그대로 보존한다.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();

$storeId    = $_POST['id'] ?? '';
$documentId = $_POST['document_id'] ?? '';
$customerId = $_POST['customer_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $storeId === '' || $documentId === '') {
    http_response_code(400);
    die('잘못된 요청입니다.');
}

$actor = requireStoreAccess($pdo, $storeId);
requireAdminRole($actor, $storeId);

$stmt = $pdo->prepare('SELECT * FROM ss_consent_documents WHERE id = ? AND store_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$documentId, $storeId]);
$document = $stmt->fetch();

if (!$document) {
    $fallback = $customerId !== ''
        ? 'consent-history.php?id=' . urlencode($storeId) . '&customer_id=' . urlencode($customerId) . '&doc_delete_error=1'
        : 'store.php?id=' . urlencode($storeId) . '&doc_delete_error=1';
    header('Location: ' . $fallback);
    exit;
}

$redirectCustomerId = $customerId !== '' ? $customerId : $document['customer_id'];

try {
    $template = json_decode($document['template_snapshot'] ?? '{}', true) ?: [];
    $docTitle = $template['title'] ?? '동의서';

    $del = $pdo->prepare('UPDATE ss_consent_documents SET deleted_at = NOW() WHERE id = ? AND store_id = ?');
    $del->execute([$documentId, $storeId]);

    logAccess($pdo, $storeId, $actor, 'delete_signed_consent', 'customer', $redirectCustomerId, $docTitle);

    header('Location: consent-history.php?id=' . urlencode($storeId) . '&customer_id=' . urlencode($redirectCustomerId) . '&doc_deleted=1');
    exit;
} catch (Throwable $e) {
    error_log('[consent document delete] ' . $e->getMessage());
    header('Location: consent-history.php?id=' . urlencode($storeId) . '&customer_id=' . urlencode($redirectCustomerId) . '&doc_delete_error=1');
    exit;
}
