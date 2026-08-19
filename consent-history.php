<?php
/**
 * consent-history.php
 * 특정 고객이 서명한 "모든" 동의서 목록을 최신순으로 보여주는 화면
 * store.php처럼 대표/관리자/직원 모두 접근 가능 (requireStoreAccess 기준)
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();

$storeId    = $_GET['id'] ?? '';
$customerId = $_GET['customer_id'] ?? '';

if ($storeId === '' || $customerId === '') {
    http_response_code(400);
    die('필수 파라미터(id, customer_id)가 없습니다.');
}

$actor = requireStoreAccess($pdo, $storeId);

$stmt = $pdo->prepare('SELECT id, name FROM ss_stores WHERE id = ? LIMIT 1');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없습니다.');
}

$stmt = $pdo->prepare('SELECT id, name, phone_masked FROM ss_customers WHERE id = ? AND store_id = ? LIMIT 1');
$stmt->execute([$customerId, $storeId]);
$customer = $stmt->fetch();
if (!$customer) {
    http_response_code(404);
    die('고객 정보를 찾을 수 없습니다.');
}

// 해당 고객이 서명한 모든 동의서를 최신순으로 조회 (건수 제한 없음)
// ※ ss_consent_documents.staff_id 와 ss_store_staff.id 의 collation이 서로 달라
//   (utf8mb4_0900_ai_ci vs utf8mb4_unicode_ci) JOIN 시 PDOException이 발생했던 문제를
//   COLLATE 명시로 강제 통일해 해결함 (임시 조치 — 근본 해결은 DB 테이블 collation 통일 필요)
$stmt = $pdo->prepare(
    'SELECT d.id, d.template_snapshot, d.signed_at, d.created_at, d.staff_id, s.name AS staff_name
     FROM ss_consent_documents d
     LEFT JOIN ss_store_staff s
        ON s.id COLLATE utf8mb4_unicode_ci = d.staff_id COLLATE utf8mb4_unicode_ci
     WHERE d.store_id = ? AND d.customer_id = ?
     ORDER BY d.signed_at DESC, d.created_at DESC'
);
$stmt->execute([$storeId, $customerId]);
$documents = $stmt->fetchAll();

logAccess($pdo, $storeId, $actor, 'view_consent_history', 'customer', $customerId);

$pageTitle = htmlspecialchars($customer['name']) . '님 동의서 내역';
require_once __DIR__ . '/includes/flow_head.php';
?>
<div class="consent-flow-page">
    <div class="consent-flow-topbar">
        <a href="store.php?id=<?= urlencode($storeId) ?>" class="btn-back">‹ 위로</a>
        <div class="consent-flow-topbar-right">
            <a href="store.php?id=<?= urlencode($storeId) ?>" class="btn-exit">↪ 나가기</a>
        </div>
    </div>

    <div class="consent-flow-body">
        <h1 class="consent-flow-title"><?= htmlspecialchars($customer['name']) ?>님 동의서 내역</h1>
        <p class="consent-flow-subtitle">
            <?= htmlspecialchars($customer['phone_masked'] ?? '') ?> · 총 <?= count($documents) ?>건 서명
        </p>

        <div class="page-header" style="margin:20px 0;">
            <a href="consent-select.php?id=<?= urlencode($storeId) ?>&customer_id=<?= urlencode($customerId) ?>" class="btn-primary" style="width:auto;padding:11px 22px;">+ 새 동의서 작성</a>
        </div>

        <?php if (empty($documents)): ?>
            <div class="empty-state" style="padding:60px 20px;text-align:center;color:var(--text-sub);">
                아직 서명된 동의서가 없습니다.
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr><th>동의서 제목</th><th>서명일시</th><th>서명 처리자</th><th>보기</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                        <?php
                            $snap = json_decode($doc['template_snapshot'] ?? '{}', true) ?: [];
                            $title = $snap['title'] ?? '(제목 없음)';
                            $signedAt = $doc['signed_at'] ?? $doc['created_at'];
                            $processedBy = $doc['staff_id'] ? htmlspecialchars($doc['staff_name'] ?? '탈퇴한 직원') : '대표';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($title) ?></td>
                            <td><?= htmlspecialchars(date('Y.m.d H:i', strtotime($signedAt))) ?></td>
                            <td><?= $processedBy ?></td>
                            <td>
                                <a href="consent-document-view.php?id=<?= urlencode($storeId) ?>&document_id=<?= urlencode($doc['id']) ?>" class="btn-mini">보기</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/flow_foot.php'; ?>
