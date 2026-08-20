<?php
/**
 * consent-document-delete.php
 * 서명 완료된 동의서 1건 삭제 처리 (화면 없음, POST 요청만 처리 후 consent-history.php로 리다이렉트)
 * 법적 증빙 자료를 지우는 작업이므로 대표(owner) 또는 관리자 권한 직원(admin)만 허용
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
requireAdminRole($actor, $storeId); // owner/admin이 아니면 여기서 안내 화면을 띄우고 종료

$stmt = $pdo->prepare('SELECT * FROM ss_consent_documents WHERE id = ? AND store_id = ? LIMIT 1');
$stmt->execute([$documentId, $storeId]);
$document = $stmt->fetch();

if (!$document) {
    // 이미 삭제됐거나 잘못된 ID인 경우, customer_id가 있으면 그 고객 내역으로, 없으면 고객 목록으로
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

    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM ss_consent_documents WHERE id = ? AND store_id = ?');
    $del->execute([$documentId, $storeId]);
    $pdo->commit();

    // DB 레코드 삭제 후, 남아있는 서명 이미지 파일도 정리 (실패해도 화면은 죽지 않게 방어)
    if (!empty($document['signature_image_url'])) {
        $relPath = ltrim(str_replace('/tattoo/', '', $document['signature_image_url']), '/');
        $filePath = __DIR__ . '/' . $relPath;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    logAccess($pdo, $storeId, $actor, 'delete_signed_consent', 'customer', $redirectCustomerId, $docTitle);

    header('Location: consent-history.php?id=' . urlencode($storeId) . '&customer_id=' . urlencode($redirectCustomerId) . '&doc_deleted=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[consent document delete] ' . $e->getMessage());
    header('Location: consent-history.php?id=' . urlencode($storeId) . '&customer_id=' . urlencode($redirectCustomerId) . '&doc_delete_error=1');
    exit;
}
