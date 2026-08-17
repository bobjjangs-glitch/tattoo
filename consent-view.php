<?php
$activePage = 'consent';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
$templateId = $_GET['template_id'] ?? '';

if ($storeId === '' || $templateId === '') {
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name FROM ss_stores WHERE id = ? AND owner_id = ?');
    $stmt->execute([$storeId, $user['id']]);
    $store = $stmt->fetch();

    if (!$store) {
        header('Location: dashboard.php');
        exit;
    }

    $tStmt = $pdo->prepare('SELECT * FROM ss_consent_templates WHERE id = ? AND store_id = ?');
    $tStmt->execute([$templateId, $storeId]);
    $template = $tStmt->fetch();

    if (!$template) {
        header('Location: consent.php?id=' . urlencode($storeId));
        exit;
    }

    $checklistItems = json_decode($template['checklist_items'], true) ?: [];
    $agreementClauses = json_decode($template['agreement_clauses'], true) ?: [];

    $pageTitle = $store['name'] . ' - ' . $template['title'];
} catch (Throwable $e) {
    error_log('[consent-view.php] ' . $e->getMessage());
    http_response_code(500);
    $isError = true;
}

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="dashboard-layout">
    <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

    <main class="main-content">
        <?php if (!empty($isError)): ?>
            <div class="alert alert-error">동의서 정보를 불러오는 중 오류가 발생했습니다.</div>
        <?php else: ?>
            <div class="page-header">
                <h1><?= htmlspecialchars($template['title']) ?></h1>
                <div>
                    <a href="consent-edit.php?id=<?= htmlspecialchars($storeId) ?>&template_id=<?= htmlspecialchars($template['id']) ?>" class="btn btn-secondary">수정</a>
                    <a href="consent.php?id=<?= htmlspecialchars($storeId) ?>" class="btn btn-secondary">목록으로</a>
                </div>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success">저장되었습니다.</div>
            <?php endif; ?>

            <div class="detail-card">
                <p><strong>업종:</strong> <?= htmlspecialchars($template['industry']) ?></p>
                <p><strong>버전:</strong> v<?= (int)$template['version'] ?></p>
                <p><strong>상태:</strong> <?= $template['is_active'] ? '사용중' : '비활성' ?></p>

                <h3>체크리스트 항목</h3>
                <ul>
                    <?php foreach ($checklistItems as $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>

                <h3>동의 조항</h3>
                <?php foreach ($agreementClauses as $clause): ?>
                    <p><?= nl2br(htmlspecialchars($clause)) ?></p>
                <?php endforeach; ?>

                <h3>환불 정책</h3>
                <div class="rich-content"><?= $template['refund_policy'] ?? '<span class="muted">등록된 내용이 없습니다.</span>' ?></div>

                <h3>첨부 파일</h3>
                <?php if (!empty($template['template_file_url'])): ?>
                    <a href="<?= htmlspecialchars($template['template_file_url']) ?>" target="_blank" class="btn btn-outline">원본 파일 다운로드</a>
                <?php else: ?>
                    <p class="muted">첨부된 파일이 없습니다.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
