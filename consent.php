<?php
$activePage = 'consent';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, industry FROM ss_stores WHERE id = ? AND owner_id = ?');
$stmt->execute([$storeId, $user['id']]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없거나 접근 권한이 없습니다.');
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $templateId = $_POST['template_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $industry = trim($_POST['industry'] ?? '');
        $content = $_POST['content'] ?? '';
        $diagramType = $_POST['diagram_type'] ?? 'none';

        $errors = [];
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = '제목을 1~255자 이내로 입력해주세요.';
        }
        if ($industry === '' || mb_strlen($industry) > 20) {
            $errors[] = '카테고리를 입력해주세요.';
        }

        $allowedTags = '<h1><h2><h3><b><i><u><s><strong><em><ul><ol><li><br><p><div><span><a>';
        $safeContent = strip_tags($content, $allowedTags);

        if (empty($errors)) {
            try {
                if ($templateId !== '') {
                    $checkStmt = $pdo->prepare('SELECT id FROM ss_consent_templates WHERE id = ? AND store_id = ?');
                    $checkStmt->execute([$templateId, $storeId]);
                    if ($checkStmt->fetch()) {
                        $upd = $pdo->prepare('UPDATE ss_consent_templates
                            SET title = ?, industry = ?, content = ?, diagram_type = ?, version = version + 1
                            WHERE id = ?');
                        $upd->execute([$title, $industry, $safeContent, $diagramType, $templateId]);
                    }
                } else {
                    $newId = Uuid::v4();
                    $ins = $pdo->prepare('INSERT INTO ss_consent_templates
                        (id, store_id, industry, title, content, diagram_type, version, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, 1, 1)');
                    $ins->execute([$newId, $storeId, $industry, $title, $safeContent, $diagramType]);
                }
                header('Location: consent.php?id=' . urlencode($storeId) . '&saved=1');
                exit;
            } catch (Throwable $e) {
                error_log('[consent.php save] ' . $e->getMessage());
                $errorMessage = '저장 중 오류가 발생했습니다.';
            }
        } else {
            $errorMessage = implode(' ', $errors);
        }
    } elseif ($action === 'toggle') {
        $templateId = $_POST['template_id'] ?? '';
        $chk = $pdo->prepare('SELECT id FROM ss_consent_templates WHERE id = ? AND store_id = ?');
        $chk->execute([$templateId, $storeId]);
        if ($chk->fetch()) {
            $pdo->prepare('UPDATE ss_consent_templates SET is_active = NOT is_active WHERE id = ?')->execute([$templateId]);
        }
        header('Location: consent.php?id=' . urlencode($storeId));
        exit;
    } elseif ($action === 'delete') {
        $templateId = $_POST['template_id'] ?? '';
        $chk = $pdo->prepare('SELECT id, template_file_url FROM ss_consent_templates WHERE id = ? AND store_id = ?');
        $chk->execute([$templateId, $storeId]);
        $target = $chk->fetch();
        if ($target) {
            $refCheck = $pdo->prepare('SELECT COUNT(*) FROM ss_consent_documents WHERE template_id = ?');
            $refCheck->execute([$templateId]);
            if ((int)$refCheck->fetchColumn() > 0) {
                $errorMessage = '이미 서명된 동의서가 있어 삭제할 수 없습니다. 비활성화만 가능합니다.';
            } else {
                if (!empty($target['template_file_url'])) {
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . $target['template_file_url'];
                    if (file_exists($filePath)) { unlink($filePath); }
                }
                $pdo->prepare('DELETE FROM ss_consent_templates WHERE id = ?')->execute([$templateId]);
            }
        }
        if ($errorMessage === '') {
            header('Location: consent.php?id=' . urlencode($storeId));
            exit;
        }
    }
}

$listStmt = $pdo->prepare('SELECT id, title, industry, content, diagram_type, template_file_url, version, is_active, created_at
                            FROM ss_consent_templates
                            WHERE store_id = ?
                            ORDER BY created_at DESC');
$listStmt->execute([$storeId]);
$templates = $listStmt->fetchAll();

$industries = [];
foreach ($templates as $t) {
    $industries[$t['industry']] = ($industries[$t['industry']] ?? 0) + 1;
}

function consentBadgeClass(string $industry): string {
    $map = [
        '타투' => 'cat-tattoo',
        '공통' => 'cat-common',
        '피부관리' => 'cat-skin',
    ];
    return $map[$industry] ?? '';
}

$pageTitle = htmlspecialchars($store['name']) . ' 동의서 관리';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
    <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

    <main class="main-content">
        <header class="dashboard-header">
            <span><?php echo htmlspecialchars($user['name'] ?? ''); ?>님</span>
        </header>

        <div class="page-content">
            <div class="page-header">
                <h1 class="page-title">동의서 관리</h1>
                <button type="button" class="btn-primary" style="width:auto;padding:11px 22px;" onclick="openCreateModal()">+ 새 동의서 작성</button>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert-success">저장되었습니다.</div>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <div class="alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="filter-chips">
                <button type="button" class="filter-chip active" data-filter="all" onclick="filterByIndustry('all', this)">전체 <?php echo count($templates); ?></button>
                <?php foreach ($industries as $ind => $cnt): ?>
                    <button type="button" class="filter-chip" data-filter="<?php echo htmlspecialchars($ind); ?>" onclick="filterByIndustry('<?php echo htmlspecialchars($ind, ENT_QUOTES); ?>', this)">
                        <?php echo htmlspecialchars($ind); ?> <?php echo $cnt; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if (empty($templates)): ?>
                <div class="coming-soon">
                    <p>등록된 동의서 양식이 없습니다.</p>
                    <p>+ 새 동의서 작성 버튼을 눌러 첫 동의서를 만들어보세요.</p>
                </div>
            <?php else: ?>
                <div class="consent-card-list" id="consentCardList">
                    <?php foreach ($templates as $t): ?>
                        <div class="consent-card" data-industry="<?php echo htmlspecialchars($t['industry']); ?>">
                            <div class="consent-card-info">
                                <div class="consent-card-title"><?php echo htmlspecialchars($t['title']); ?></div>
                                <div class="consent-card-meta">
                                    <span class="category-badge <?php echo consentBadgeClass($t['industry']); ?>"><?php echo htmlspecialchars($t['industry']); ?></span>
                                    <span class="consent-card-date"><?php echo htmlspecialchars(date('Y. n. j.', strtotime($t['created_at']))); ?></span>
                                    <?php if (!$t['is_active']): ?><span class="category-badge">비활성</span><?php endif; ?>
                                </div>
                            </div>
                            <div class="consent-card-actions">
                                <button type="button" class="icon-btn" title="보기" onclick="openViewModal('<?php echo htmlspecialchars($t['id']); ?>')">👁️</button>
                                <button type="button" class="icon-btn" title="수정" onclick="openEditModal('<?php echo htmlspecialchars($t['id']); ?>')">✏️</button>
                                <form method="post" style="display:inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($t['id']); ?>">
                                    <button type="submit" class="icon-btn danger" title="삭제">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- 생성/수정 모달 -->
<div class="modal-overlay" id="consentModal" style="display:none;">
    <div class="modal-box modal-lg" style="position:relative;">
        <button type="button" class="modal-close-btn" onclick="closeModal()">✕</button>
        <h2 class="modal-title" id="modalTitle">동의서 작성</h2>
        <form method="post" id="consentForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="template_id" id="formTemplateId" value="">

            <div class="form-group">
                <label>제목 *</label>
                <input type="text" name="title" id="formTitle" maxlength="255" required>
            </div>

            <div class="form-group">
                <label>카테고리 *</label>
                <input type="text" name="industry" id="formIndustry" maxlength="20" placeholder="예: 타투, 피부관리, 공통" required>
            </div>

            <div class="form-group">
                <label>내용 *</label>
                <div class="editor-toolbar">
                    <button type="button" onclick="ex('formatBlock','H3')">H</button>
                    <button type="button" onclick="ex('bold')"><b>B</b></button>
                    <button type="button" onclick="ex('italic')"><i>I</i></button>
                    <button type="button" onclick="ex('underline')"><u>U</u></button>
                    <button type="button" onclick="ex('strikeThrough')"><s>S</s></button>
                    <button type="button" onclick="ex('justifyLeft')">≡</button>
                    <button type="button" onclick="ex('justifyCenter')">≣</button>
                    <button type="button" onclick="ex('justifyRight')">≡</button>
                    <button type="button" onclick="ex('insertUnorderedList')">•</button>
                    <button type="button" onclick="ex('insertOrderedList')">1.</button>
                    <button type="button" onclick="insertLink()">🔗</button>
                </div>
                <div class="editor-body" id="contentEditor" contenteditable="true" data-placeholder="동의서 내용을 입력해주세요"></div>
                <input type="hidden" name="content" id="contentHidden">
            </div>

            <div class="form-group">
                <label>시술 부위 도식</label>
                <select name="diagram_type" id="formDiagram" class="diagram-select">
                    <option value="none">사용하지 않음</option>
                    <option value="body_front_back">전신 (앞/뒤)</option>
                    <option value="face">얼굴</option>
                    <option value="hand_foot">손/발</option>
                </select>
                <p class="diagram-select-hint">고객이 동의서 작성 시 시술 부위를 체크박스로 표시할 수 있습니다.</p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert-error"><?php echo htmlspecialchars($errorMessage); ?></div>
            <?php endif; ?>

            <div class="modal-actions-row">
                <button type="button" class="btn-secondary" onclick="closeModal()">취소</button>
                <button type="submit" class="btn-primary" onclick="return syncContent()">저장</button>
            </div>
        </form>
    </div>
</div>

<!-- 읽기 전용 보기 모달 -->
<div class="modal-overlay" id="viewModal" style="display:none;">
    <div class="modal-box modal-lg" style="position:relative;">
        <button type="button" class="modal-close-btn" onclick="document.getElementById('viewModal').style.display='none'">✕</button>
        <h2 class="modal-title" id="viewTitle"></h2>
        <p style="font-size:12px;color:var(--text-sub);margin-bottom:16px;" id="viewMeta"></p>
        <div class="sign-content-box" id="viewContent"></div>
    </div>
</div>

<script>
const templates = <?php echo json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

function ex(cmd, val) {
    document.execCommand(cmd, false, val || null);
    document.getElementById('contentEditor').focus();
}
function insertLink() {
    const url = prompt('링크 주소를 입력하세요');
    if (url) { ex('createLink', url); }
}
function syncContent() {
    document.getElementById('contentHidden').value = document.getElementById('contentEditor').innerHTML;
    return true;
}
function openCreateModal() {
    document.getElementById('modalTitle').textContent = '새 동의서 작성';
    document.getElementById('formTemplateId').value = '';
    document.getElementById('formTitle').value = '';
    document.getElementById('formIndustry').value = '';
    document.getElementById('contentEditor').innerHTML = '';
    document.getElementById('formDiagram').value = 'none';
    document.getElementById('consentModal').style.display = 'flex';
}
function openEditModal(id) {
    const t = templates.find(x => x.id === id);
    if (!t) return;
    document.getElementById('modalTitle').textContent = '동의서 수정';
    document.getElementById('formTemplateId').value = t.id;
    document.getElementById('formTitle').value = t.title;
    document.getElementById('formIndustry').value = t.industry;
    document.getElementById('contentEditor').innerHTML = t.content || '';
    document.getElementById('formDiagram').value = t.diagram_type || 'none';
    document.getElementById('consentModal').style.display = 'flex';
}
function openViewModal(id) {
    const t = templates.find(x => x.id === id);
    if (!t) return;
    document.getElementById('viewTitle').textContent = t.title;
    document.getElementById('viewMeta').textContent = t.industry + ' · v' + t.version + ' · ' + t.created_at;
    document.getElementById('viewContent').innerHTML = t.content || '<span class="muted">내용이 없습니다.</span>';
    document.getElementById('viewModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('consentModal').style.display = 'none';
}
function filterByIndustry(industry, btn) {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.consent-card').forEach(card => {
        card.style.display = (industry === 'all' || card.dataset.industry === industry) ? 'flex' : 'none';
    });
}
<?php if ($errorMessage !== '' && ($_POST['action'] ?? '') === 'save'): ?>
window.addEventListener('DOMContentLoaded', () => {
    document.getElementById('formTemplateId').value = '<?php echo htmlspecialchars($_POST['template_id'] ?? ''); ?>';
    document.getElementById('formTitle').value = '<?php echo htmlspecialchars(addslashes($_POST['title'] ?? '')); ?>';
    document.getElementById('formIndustry').value = '<?php echo htmlspecialchars(addslashes($_POST['industry'] ?? '')); ?>';
    document.getElementById('contentEditor').innerHTML = <?php echo json_encode($_POST['content'] ?? '', JSON_HEX_TAG); ?>;
    document.getElementById('modalTitle').textContent = '<?php echo ($_POST['template_id'] ?? '') !== '' ? '동의서 수정' : '새 동의서 작성'; ?>';
    document.getElementById('consentModal').style.display = 'flex';
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
