<?php
$activePage = 'consent';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';

$storeId = $_GET['id'] ?? '';
$templateId = $_GET['template_id'] ?? ($_POST['template_id'] ?? '');
$errors = [];
$existing = null;

if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name, industry FROM ss_stores WHERE id = ? AND owner_id = ?');
    $stmt->execute([$storeId, $_SESSION['user_id']]);
    $store = $stmt->fetch();

    if (!$store) {
        header('Location: dashboard.php');
        exit;
    }

    if ($templateId !== '') {
        $existStmt = $pdo->prepare('SELECT * FROM ss_consent_templates WHERE id = ? AND store_id = ?');
        $existStmt->execute([$templateId, $storeId]);
        $existing = $existStmt->fetch();
        if (!$existing) {
            header('Location: consent.php?id=' . urlencode($storeId));
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $industry = trim($_POST['industry'] ?? $store['industry']);
        $checklistItemsRaw = $_POST['checklist_items'] ?? [];
        $agreementClausesRaw = $_POST['agreement_clauses'] ?? [];
        $refundPolicyRaw = $_POST['refund_policy'] ?? '';

        // 유효성 검사
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = '제목을 1~255자 이내로 입력해주세요.';
        }
        $checklistItems = array_values(array_filter(array_map('trim', $checklistItemsRaw), fn($v) => $v !== ''));
        if (empty($checklistItems)) {
            $errors[] = '체크리스트 항목을 1개 이상 입력해주세요.';
        }
        $agreementClauses = array_values(array_filter(array_map('trim', $agreementClausesRaw), fn($v) => $v !== ''));
        if (empty($agreementClauses)) {
            $errors[] = '동의 조항을 1개 이상 입력해주세요.';
        }

        // 리치 텍스트 XSS 방지 - 허용 태그만 남김
        $allowedTags = '<b><i><u><strong><em><ul><ol><li><br><p>';
        $refundPolicy = strip_tags($refundPolicyRaw, $allowedTags);

        // 파일 업로드 처리 (선택)
        $templateFileUrl = $existing['template_file_url'] ?? null;
        if (!empty($_FILES['template_file']['name'])) {
            $file = $_FILES['template_file'];
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $maxSize = 10 * 1024 * 1024;

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = '파일 업로드 중 오류가 발생했습니다.';
            } elseif (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'PDF, JPG, PNG 파일만 업로드할 수 있습니다.';
            } elseif ($file['size'] > $maxSize) {
                $errors[] = '파일 크기는 10MB를 초과할 수 없습니다.';
            } else {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/tattoo/uploads/consent-templates/';
                $newFileName = Uuid::v4() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
                    // 기존 파일 있으면 삭제
                    if (!empty($templateFileUrl)) {
                        $oldPath = $_SERVER['DOCUMENT_ROOT'] . $templateFileUrl;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $templateFileUrl = '/tattoo/uploads/consent-templates/' . $newFileName;
                } else {
                    $errors[] = '파일 저장에 실패했습니다.';
                }
            }
        }

        if (empty($errors)) {
            $checklistJson = json_encode($checklistItems, JSON_UNESCAPED_UNICODE);
            $clausesJson = json_encode($agreementClauses, JSON_UNESCAPED_UNICODE);

            if ($existing) {
                $upd = $pdo->prepare('UPDATE ss_consent_templates
                    SET title = ?, industry = ?, checklist_items = ?, agreement_clauses = ?,
                        refund_policy = ?, template_file_url = ?, version = version + 1
                    WHERE id = ?');
                $upd->execute([$title, $industry, $checklistJson, $clausesJson, $refundPolicy, $templateFileUrl, $existing['id']]);
                $templateId = $existing['id'];
            } else {
                $newId = Uuid::v4();
                $ins = $pdo->prepare('INSERT INTO ss_consent_templates
                    (id, store_id, industry, title, checklist_items, agreement_clauses, refund_policy, template_file_url, version, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)');
                $ins->execute([$newId, $storeId, $industry, $title, $checklistJson, $clausesJson, $refundPolicy, $templateFileUrl]);
                $templateId = $newId;
            }

            header('Location: consent-view.php?id=' . urlencode($storeId) . '&template_id=' . urlencode($templateId) . '&saved=1');
            exit;
        }
    }

    // 폼 초기값
    $formTitle = $_POST['title'] ?? ($existing['title'] ?? '');
    $formIndustry = $_POST['industry'] ?? ($existing['industry'] ?? $store['industry']);
    $formChecklist = $_POST['checklist_items'] ?? ($existing ? json_decode($existing['checklist_items'], true) : ['']);
    $formClauses = $_POST['agreement_clauses'] ?? ($existing ? json_decode($existing['agreement_clauses'], true) : ['']);
    $formRefund = $_POST['refund_policy'] ?? ($existing['refund_policy'] ?? '');
    $formFileUrl = $existing['template_file_url'] ?? null;

    $pageTitle = $store['name'] . ' - ' . ($existing ? '동의서 수정' : '새 동의서 만들기');
} catch (Throwable $e) {
    error_log('[consent-edit.php] ' . $e->getMessage());
    http_response_code(500);
    $isError = true;
}

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="dashboard-layout">
    <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><?= $existing ? '동의서 수정' : '새 동의서 만들기' ?></h1>
            <a href="consent.php?id=<?= htmlspecialchars($storeId) ?>" class="btn btn-secondary">목록으로</a>
        </div>

        <?php if (!empty($isError)): ?>
            <div class="alert alert-error">페이지를 불러오는 중 오류가 발생했습니다.</div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="consent-form">
                <input type="hidden" name="template_id" value="<?= htmlspecialchars($templateId) ?>">

                <div class="form-group">
                    <label>제목</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($formTitle) ?>" maxlength="255" required>
                </div>

                <div class="form-group">
                    <label>업종</label>
                    <input type="text" name="industry" value="<?= htmlspecialchars($formIndustry) ?>" maxlength="20" required>
                </div>

                <div class="form-group">
                    <label>체크리스트 항목</label>
                    <div id="checklist-wrap">
                        <?php foreach ($formChecklist as $item): ?>
                        <div class="dynamic-row">
                            <input type="text" name="checklist_items[]" value="<?= htmlspecialchars($item) ?>" placeholder="예: 시술 전 음주 여부 확인">
                            <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">삭제</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline" onclick="addRow('checklist-wrap','checklist_items[]','예: 항목을 입력하세요')">+ 항목 추가</button>
                </div>

                <div class="form-group">
                    <label>동의 조항</label>
                    <div id="clauses-wrap">
                        <?php foreach ($formClauses as $clause): ?>
                        <div class="dynamic-row">
                            <textarea name="agreement_clauses[]" rows="2" placeholder="동의 조항 내용을 입력하세요"><?= htmlspecialchars($clause) ?></textarea>
                            <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">삭제</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-outline" onclick="addTextareaRow('clauses-wrap','agreement_clauses[]','동의 조항 내용을 입력하세요')">+ 조항 추가</button>
                </div>

                <div class="form-group">
                    <label>환불 정책 (서식 있음)</label>
                    <div class="rich-toolbar">
                        <button type="button" onclick="document.execCommand('bold')"><b>B</b></button>
                        <button type="button" onclick="document.execCommand('italic')"><i>I</i></button>
                        <button type="button" onclick="document.execCommand('insertUnorderedList')">목록</button>
                    </div>
                    <div id="refund-editor" class="rich-editor" contenteditable="true"><?= $formRefund ?></div>
                    <input type="hidden" name="refund_policy" id="refund-hidden">
                </div>

                <div class="form-group">
                    <label>동의서 원본 파일 첨부 (PDF/JPG/PNG, 최대 10MB)</label>
                    <div class="upload-box">
                        <input type="file" name="template_file" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if (!empty($formFileUrl)): ?>
                            <p class="muted">현재 첨부파일: <a href="<?= htmlspecialchars($formFileUrl) ?>" target="_blank">다운로드</a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" onclick="syncRefundEditor()">저장</button>
            </form>
        <?php endif; ?>
    </main>
</div>

<script>
function addRow(wrapId, name, placeholder) {
    const wrap = document.getElementById(wrapId);
    const row = document.createElement('div');
    row.className = 'dynamic-row';
    row.innerHTML = `<input type="text" name="${name}" placeholder="${placeholder}">
                      <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">삭제</button>`;
    wrap.appendChild(row);
}
function addTextareaRow(wrapId, name, placeholder) {
    const wrap = document.getElementById(wrapId);
    const row = document.createElement('div');
    row.className = 'dynamic-row';
    row.innerHTML = `<textarea name="${name}" rows="2" placeholder="${placeholder}"></textarea>
                      <button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">삭제</button>`;
    wrap.appendChild(row);
}
function syncRefundEditor() {
    document.getElementById('refund-hidden').value = document.getElementById('refund-editor').innerHTML;
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
