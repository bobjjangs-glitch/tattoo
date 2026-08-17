<?php
$activePage = 'consent';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, industry FROM ss_stores WHERE id = ? AND owner_id = ?');
    $stmt->execute([$storeId, $user['id']]);
    $store = $stmt->fetch();

    if (!$store) {
        header('Location: dashboard.php');
        exit;
    }

    // 활성/비활성 토글 및 삭제 처리
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $templateId = $_POST['template_id'] ?? '';
        $action = $_POST['action'] ?? '';

        $checkStmt = $pdo->prepare('SELECT id, template_file_url FROM ss_consent_templates WHERE id = ? AND store_id = ?');
        $checkStmt->execute([$templateId, $storeId]);
        $target = $checkStmt->fetch();

        if ($target) {
            if ($action === 'toggle') {
                $pdo->prepare('UPDATE ss_consent_templates SET is_active = NOT is_active WHERE id = ?')
                    ->execute([$templateId]);
            } elseif ($action === 'delete') {
                $refCheck = $pdo->prepare('SELECT COUNT(*) FROM ss_consent_documents WHERE template_id = ?');
                $refCheck->execute([$templateId]);
                $refCount = (int)$refCheck->fetchColumn();

                if ($refCount > 0) {
                    $errorMessage = '이미 서명된 동의서가 있어 삭제할 수 없습니다. 비활성화만 가능합니다.';
                } else {
                    if (!empty($target['template_file_url'])) {
                        $filePath = $_SERVER['DOCUMENT_ROOT'] . $target['template_file_url'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    $pdo->prepare('DELETE FROM ss_consent_templates WHERE id = ?')->execute([$templateId]);
                }
            }
        }
        header('Location: consent.php?id=' . urlencode($storeId));
        exit;
    }

    $listStmt = $pdo->prepare('SELECT id, title, industry, version, is_active, template_file_url, created_at
                                FROM ss_consent_templates
                                WHERE store_id = ?
                                ORDER BY created_at DESC');
    $listStmt->execute([$storeId]);
    $templates = $listStmt->fetchAll();

    $pageTitle = $store['name'] . ' - 동의서 관리';
} catch (Throwable $e) {
    error_log('[consent.php] ' . $e->getMessage());
    http_response_code(500);
    $isError = true;
}

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="dashboard-layout">
    <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1>동의서 관리</h1>
            <a href="consent-edit.php?id=<?= htmlspecialchars($storeId) ?>" class="btn btn-primary">+ 새 동의서 만들기</a>
        </div>

        <?php if (!empty($isError)): ?>
            <div class="alert alert-error">동의서 목록을 불러오는 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.</div>
        <?php elseif (!empty($errorMessage)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <?php if (empty($isError) && empty($templates)): ?>
            <div class="coming-soon">
                <p>등록된 동의서 양식이 없습니다.</p>
                <p>먼저 동의서 양식을 만들어 주세요.</p>
            </div>
        <?php elseif (empty($isError)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>제목</th>
                        <th>업종</th>
                        <th>버전</th>
                        <th>첨부파일</th>
                        <th>상태</th>
                        <th>등록일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars($t['title']) ?></td>
                        <td><?= htmlspecialchars($t['industry']) ?></td>
                        <td>v<?= (int)$t['version'] ?></td>
                        <td>
                            <?php if (!empty($t['template_file_url'])): ?>
                                <a href="<?= htmlspecialchars($t['template_file_url']) ?>" target="_blank">다운로드</a>
                            <?php else: ?>
                                <span class="muted">없음</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $t['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                <?= $t['is_active'] ? '사용중' : '비활성' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($t['created_at']))) ?></td>
                        <td class="actions-cell">
                            <a href="consent-view.php?id=<?= htmlspecialchars($storeId) ?>&template_id=<?= htmlspecialchars($t['id']) ?>">보기</a>
                            <a href="consent-edit.php?id=<?= htmlspecialchars($storeId) ?>&template_id=<?= htmlspecialchars($t['id']) ?>">수정</a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="template_id" value="<?= htmlspecialchars($t['id']) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button type="submit" class="link-btn"><?= $t['is_active'] ? '비활성화' : '활성화' ?></button>
                            </form>
                            <form method="post" style="display:inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                <input type="hidden" name="template_id" value="<?= htmlspecialchars($t['id']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="link-btn link-danger">삭제</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
