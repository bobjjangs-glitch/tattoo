<?php
$activePage = 'consent';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';
require_once __DIR__ . '/includes/diagram_helper.php';
require_once __DIR__ . '/includes/plan_guard.php';

$pdo = getDbConnection();

$diagramConfig = include __DIR__ . '/includes/diagram_config.php';
if (!is_array($diagramConfig) || empty($diagramConfig)) {
    http_response_code(500);
    die('시술 부위 도해 설정 파일을 불러오지 못했습니다.');
}

$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$actor = requireStoreAccess($pdo, $storeId);
requireAdminRole($actor); // 동의서 양식 관리는 대표 또는 관리자 권한 직원만 허용

$stmt = $pdo->prepare('SELECT id, name, industry, plan_status, trial_ends_at FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없거나 접근 권한이 없습니다.');
}

enforcePlanAccess($pdo, $store);

logAccess($pdo, $storeId, $actor, 'view_consent_templates');

$errorMessage = '';

// ── 체크리스트 JSON 정규화 (그룹 배열 형태로 항상 통일) ──
function normalizeChecklistGroupsForSave(string $rawJson): string {
    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded) || !isset($decoded['groups']) || !is_array($decoded['groups'])) {
        return json_encode(['groups' => []], JSON_UNESCAPED_UNICODE);
    }
    $groups = [];
    foreach ($decoded['groups'] as $g) {
        $items = array_values(array_filter(array_map('trim', $g['items'] ?? []), fn($v) => $v !== ''));
        if (($g['group_title'] ?? '') === '' && empty($items)) continue;
        $groups[] = [
            'group_title'  => trim($g['group_title'] ?? ''),
            'group_note'   => trim($g['group_note'] ?? ''),
            'required_all' => !empty($g['required_all']),
            'items'        => $items,
        ];
    }
    return json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $templateId    = $_POST['template_id'] ?? '';
        $title         = trim($_POST['title'] ?? '');
        $industry      = trim($_POST['industry'] ?? '');
        $content       = $_POST['content'] ?? '';
        $diagramTypeRaw = $_POST['diagram_type'] ?? 'none';
        $diagramType   = normalizeDiagramTypeKey($diagramTypeRaw, $diagramConfig);
        $refundPolicy  = trim($_POST['refund_policy'] ?? '');
        $checklistJson = normalizeChecklistGroupsForSave($_POST['checklist_items_json'] ?? '{"groups":[]}');
        $finalAgreeText = trim($_POST['final_agree_text'] ?? '위 내용을 충분히 읽고 이해하였으며, 시술에 동의합니다.');
        $agreementJson = json_encode(['final_text' => $finalAgreeText], JSON_UNESCAPED_UNICODE);

        $errors = [];
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = '제목을 1~255자 이내로 입력해주세요.';
        }
        if ($industry === '' || mb_strlen($industry) > 20) {
            $errors[] = '카테고리를 입력해주세요.';
        }

        $allowedTags = '<h1><h2><h3><h4><b><i><u><s><strong><em><ul><ol><li><br><p><div><span><a>';
        $safeContent = strip_tags($content, $allowedTags);

        if (empty($errors)) {
            try {
                if ($templateId !== '') {
                    $checkStmt = $pdo->prepare('SELECT id FROM ss_consent_templates WHERE id = ? AND store_id = ?');
                    $checkStmt->execute([$templateId, $storeId]);
                    if ($checkStmt->fetch()) {
                        $upd = $pdo->prepare('UPDATE ss_consent_templates
                            SET title = ?, industry = ?, content = ?, checklist_items = ?,
                                agreement_clauses = ?, refund_policy = ?, diagram_type = ?,
                                version = version + 1
                            WHERE id = ?');
                        $upd->execute([$title, $industry, $safeContent, $checklistJson,
                            $agreementJson, $refundPolicy ?: null, $diagramType, $templateId]);
                    }
                    logAccess($pdo, $storeId, $actor, 'update_consent_template', 'consent_template', $templateId, $title);
                } else {
                    $newId = Uuid::v4();
                    $ins = $pdo->prepare('INSERT INTO ss_consent_templates
                        (id, store_id, industry, title, content, checklist_items, agreement_clauses,
                         refund_policy, diagram_type, version, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())');
                    $ins->execute([$newId, $storeId, $industry, $title, $safeContent, $checklistJson,
                        $agreementJson, $refundPolicy ?: null, $diagramType]);
                    logAccess($pdo, $storeId, $actor, 'create_consent_template', 'consent_template', $newId, $title);
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
            logAccess($pdo, $storeId, $actor, 'toggle_consent_template', 'consent_template', $templateId);
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
                logAccess($pdo, $storeId, $actor, 'delete_consent_template', 'consent_template', $templateId);
            }
        }
        if ($errorMessage === '') {
            header('Location: consent.php?id=' . urlencode($storeId));
            exit;
        }
    }
}

$listStmt = $pdo->prepare('SELECT id, title, industry, content, checklist_items, agreement_clauses,
                                   refund_policy, diagram_type, template_file_url, version, is_active, created_at
                            FROM ss_consent_templates
                            WHERE store_id = ?
                            ORDER BY created_at DESC');
$listStmt->execute([$storeId]);
$templatesRaw = $listStmt->fetchAll();

// diagram_type을 화면/JS로 넘기기 전에 반드시 정규화 — 구형 값이 남아있어도 항상 유효한 키로 보정
$templates = array_map(function ($t) use ($diagramConfig) {
    $t['diagram_type'] = normalizeDiagramTypeKey($t['diagram_type'] ?? 'none', $diagramConfig);
    return $t;
}, $templatesRaw);

$industries = [];
foreach ($templates as $t) {
    $industries[$t['industry']] = ($industries[$t['industry']] ?? 0) + 1;
}

function consentBadgeClass(string $industry): string {
    $map = ['타투' => 'cat-tattoo', '공통' => 'cat-common', '피부관리' => 'cat-skin'];
    return $map[$industry] ?? '';
}

$actorRole = $actor['role'];
$pageTitle = htmlspecialchars($store['name']) . ' 동의서 관리';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
    <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

    <main class="main-content">
        <header class="dashboard-header">
            <span><?php echo htmlspecialchars($actor['actor_name'] ?? ''); ?>님</span>
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

<!-- ===== 생성/수정 모달 ===== -->
<div class="modal-overlay" id="consentModal" style="display:none;">
    <div class="modal-box modal-lg" style="position:relative;">
        <button type="button" class="modal-close-btn" onclick="closeModal()">✕</button>
        <h2 class="modal-title" id="modalTitle">동의서 작성</h2>
        <form method="post" id="consentForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="template_id" id="formTemplateId" value="">
            <input type="hidden" name="checklist_items_json" id="checklistItemsJson" value='{"groups":[]}'>

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
                    <button type="button" onclick="ex('formatBlock','H4')">H</button>
                    <button type="button" onclick="ex('bold')"><b>B</b></button>
                    <button type="button" onclick="ex('italic')"><i>I</i></button>
                    <button type="button" onclick="ex('underline')"><u>U</u></button>
                    <button type="button" onclick="ex('insertUnorderedList')">•</button>
                    <button type="button" onclick="ex('insertOrderedList')">1.</button>
                </div>
                <div class="editor-body" id="contentEditor" contenteditable="true" data-placeholder="동의서 본문(1. 시술 전 필수사항, 2. 위험성 ... 형태로 작성)"></div>
                <input type="hidden" name="content" id="contentHidden">
            </div>

            <div class="form-group">
                <label>체크리스트 그룹 (오늘 예정된 시술 / 사전 확인 사항 등)</label>
                <div id="checklistGroupEditor" class="checklist-group-editor"></div>
                <button type="button" class="btn-mini" id="btnAddGroup">+ 그룹 추가</button>
            </div>

            <div class="form-group">
                <label>최종 동의 문구</label>
                <input type="text" name="final_agree_text" id="formFinalAgree"
                       placeholder="예: 위 내용을 충분히 읽고 이해하였으며, 시술에 동의합니다.">
            </div>

            <div class="form-group">
                <label>환불 정책 (선택)</label>
                <textarea name="refund_policy" id="formRefundPolicy" class="field-textarea" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label>시술 부위 표시</label>
                <div class="diagram-type-selector" id="diagramTypeSelector"></div>
                <div class="diagram-preview-box">
                    <div class="diagram-preview-label">미리보기</div>
                    <div id="diagramPreviewArea"></div>
                </div>
                <input type="hidden" name="diagram_type" id="formDiagram" value="none">
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

<!-- ===== 읽기 전용 보기 모달 ===== -->
<div class="modal-overlay" id="viewModal" style="display:none;">
    <div class="modal-box modal-lg" style="position:relative;">
        <button type="button" class="modal-close-btn" onclick="document.getElementById('viewModal').style.display='none'">✕</button>
        <h2 class="modal-title" id="viewTitle"></h2>
        <div id="viewBody"></div>
    </div>
</div>

<script>
const templates = <?php echo json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
const DIAGRAM_CONFIG = <?php echo json_encode($diagramConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

let checklistGroups = [];

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function ex(cmd, val) {
    document.execCommand(cmd, false, val || null);
    document.getElementById('contentEditor').focus();
}
function syncContent() {
    document.getElementById('contentHidden').value = document.getElementById('contentEditor').innerHTML;
    document.getElementById('checklistItemsJson').value = JSON.stringify({ groups: collectChecklistGroups() });
    return true;
}

function renderChecklistEditor() {
    const wrap = document.getElementById('checklistGroupEditor');
    wrap.innerHTML = '';
    checklistGroups.forEach((group, gIdx) => {
        const box = document.createElement('div');
        box.className = 'checklist-group-box';
        box.innerHTML = `
            <div class="checklist-group-row">
                <input type="text" class="field-input" placeholder="그룹 제목 (예: 오늘 예정된 시술)" data-gidx="${gIdx}" data-role="title" value="${escapeHtml(group.group_title)}">
                <label class="checklist-required-toggle">
                    <input type="checkbox" data-gidx="${gIdx}" data-role="required" ${group.required_all ? 'checked' : ''}> 전체 필수
                </label>
                <button type="button" class="btn-mini danger" data-gidx="${gIdx}" data-action="remove-group">그룹 삭제</button>
            </div>
            <input type="text" class="field-input checklist-group-note-input" placeholder="그룹 설명(선택)" data-gidx="${gIdx}" data-role="note" value="${escapeHtml(group.group_note)}">
            <div class="checklist-item-list">
                ${group.items.map((item, iIdx) => `
                    <div class="checklist-item-row">
                        <input type="text" class="field-input" placeholder="항목 내용" data-gidx="${gIdx}" data-iidx="${iIdx}" data-role="item" value="${escapeHtml(item)}">
                        <button type="button" class="btn-mini danger" data-gidx="${gIdx}" data-iidx="${iIdx}" data-action="remove-item">삭제</button>
                    </div>`).join('')}
            </div>
            <button type="button" class="btn-mini" data-gidx="${gIdx}" data-action="add-item">+ 항목 추가</button>
        `;
        wrap.appendChild(box);
    });

    wrap.querySelectorAll('[data-role="title"]').forEach(el => el.addEventListener('input', function () { checklistGroups[this.dataset.gidx].group_title = this.value; }));
    wrap.querySelectorAll('[data-role="note"]').forEach(el => el.addEventListener('input', function () { checklistGroups[this.dataset.gidx].group_note = this.value; }));
    wrap.querySelectorAll('[data-role="required"]').forEach(el => el.addEventListener('change', function () { checklistGroups[this.dataset.gidx].required_all = this.checked; }));
    wrap.querySelectorAll('[data-role="item"]').forEach(el => el.addEventListener('input', function () { checklistGroups[this.dataset.gidx].items[this.dataset.iidx] = this.value; }));
    wrap.querySelectorAll('[data-action="remove-group"]').forEach(el => el.addEventListener('click', function () { checklistGroups.splice(this.dataset.gidx, 1); renderChecklistEditor(); }));
    wrap.querySelectorAll('[data-action="remove-item"]').forEach(el => el.addEventListener('click', function () { checklistGroups[this.dataset.gidx].items.splice(this.dataset.iidx, 1); renderChecklistEditor(); }));
    wrap.querySelectorAll('[data-action="add-item"]').forEach(el => el.addEventListener('click', function () { checklistGroups[this.dataset.gidx].items.push(''); renderChecklistEditor(); }));
}
document.getElementById('btnAddGroup').addEventListener('click', () => {
    checklistGroups.push({ group_title: '', group_note: '', required_all: false, items: [''] });
    renderChecklistEditor();
});
function collectChecklistGroups() {
    return checklistGroups
        .filter(g => g.group_title.trim() !== '' || g.items.some(it => it.trim() !== ''))
        .map(g => ({ group_title: g.group_title, group_note: g.group_note, required_all: !!g.required_all, items: g.items.filter(it => it.trim() !== '') }));
}

function renderDiagramSelector(selectedKey) {
    const wrap = document.getElementById('diagramTypeSelector');
    wrap.innerHTML = '';
    Object.keys(DIAGRAM_CONFIG).forEach(key => {
        const type = DIAGRAM_CONFIG[key];
        const isSelected = (key === selectedKey);
        const card = document.createElement('label');
        card.className = 'diagram-type-card' + (isSelected ? ' is-selected' : '');
        card.innerHTML = `
            <input type="radio" name="diagram_type_radio" value="${key}" ${isSelected ? 'checked' : ''}>
            <div class="diagram-type-thumb-wrap ${type.thumb ? '' : 'diagram-type-thumb-empty'}">
                ${type.thumb ? `<img src="${type.thumb}" alt="${type.label}" onerror="this.closest('.diagram-type-thumb-wrap').classList.add('thumb-broken')">` : '없음'}
            </div>
            <span class="diagram-type-name">${escapeHtml(type.label)}</span>
        `;
        card.querySelector('input').addEventListener('change', function () {
            document.querySelectorAll('#diagramTypeSelector .diagram-type-card').forEach(c => c.classList.remove('is-selected'));
            card.classList.add('is-selected');
            document.getElementById('formDiagram').value = key;
            renderDiagramPreview(key);
        });
        wrap.appendChild(card);
    });
}
function renderDiagramPreview(typeKey) {
    const type = DIAGRAM_CONFIG[typeKey];
    const area = document.getElementById('diagramPreviewArea');
    if (!type || !type.panels || type.panels.length === 0) {
        area.innerHTML = '<p class="muted">이 동의서에는 시술 부위 도해가 없습니다.</p>';
        return;
    }
    area.innerHTML = buildDiagramHtml(type.panels);
}
function buildDiagramHtml(panels) {
    let html = '<div class="body-diagram-multi-wrap">';
    panels.forEach(panel => {
        const zones = panel.zones;
        html += `<div class="body-diagram-frame${Array.isArray(zones) ? ' has-zones' : ''}">
            <img src="${panel.image}" class="body-diagram-photo" alt="시술 부위 도해" onerror="this.parentElement.classList.add('diagram-img-broken')">
            <div class="body-diagram-grid"></div>`;
        if (Array.isArray(zones)) {
            html += '<div class="body-diagram-divider-label">' + zones.map(z => `<span>${escapeHtml(z)}</span>`).join('') + '</div>';
        }
        html += '</div>';
    });
    html += '</div>';
    return html;
}

function openCreateModal() {
    document.getElementById('modalTitle').textContent = '새 동의서 작성';
    document.getElementById('formTemplateId').value = '';
    document.getElementById('formTitle').value = '';
    document.getElementById('formIndustry').value = '';
    document.getElementById('contentEditor').innerHTML = '';
    document.getElementById('formFinalAgree').value = '위 내용을 충분히 읽고 이해하였으며, 시술에 동의합니다.';
    document.getElementById('formRefundPolicy').value = '';
    document.getElementById('formDiagram').value = 'none';
    checklistGroups = [
        { group_title: '오늘 예정된 시술', group_note: '', required_all: false, items: ['신규 타투', '리터치', '커버업·수정', '컬러 작업'] },
        { group_title: '사전에 알려야 할 상태', group_note: '해당하는 항목이 있으면 반드시 알려주세요.', required_all: true, items: [''] },
    ];
    renderChecklistEditor();
    renderDiagramSelector('none');
    renderDiagramPreview('none');
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

    const checklist = safeParse(t.checklist_items, { groups: [] });
    checklistGroups = (checklist.groups || []).map(g => ({
        group_title: g.group_title || '', group_note: g.group_note || '',
        required_all: !!g.required_all, items: (g.items && g.items.length) ? g.items.slice() : [''],
    }));
    if (checklistGroups.length === 0) checklistGroups = [{ group_title: '', group_note: '', required_all: false, items: [''] }];
    renderChecklistEditor();

    const agreement = safeParse(t.agreement_clauses, { final_text: '' });
    document.getElementById('formFinalAgree').value = agreement.final_text || '위 내용을 충분히 읽고 이해하였으며, 시술에 동의합니다.';
    document.getElementById('formRefundPolicy').value = t.refund_policy || '';

    const diagramKey = t.diagram_type || 'none';
    document.getElementById('formDiagram').value = diagramKey;
    renderDiagramSelector(diagramKey);
    renderDiagramPreview(diagramKey);

    document.getElementById('consentModal').style.display = 'flex';
}

function safeParse(jsonStr, fallback) {
    try { const v = JSON.parse(jsonStr || 'null'); return v && typeof v === 'object' ? v : fallback; }
    catch (e) { return fallback; }
}

function openViewModal(id) {
    const t = templates.find(x => x.id === id);
    if (!t) return;
    document.getElementById('viewTitle').textContent = t.title;

    const checklist = safeParse(t.checklist_items, { groups: [] });
    const agreement = safeParse(t.agreement_clauses, { final_text: '' });
    const diagramType = DIAGRAM_CONFIG[t.diagram_type] ? t.diagram_type : 'none';
    const diagramInfo = DIAGRAM_CONFIG[diagramType];
    const diagramOn = diagramType !== 'none';

    let html = `<p class="consent-view-meta">${escapeHtml(t.industry)} · v${t.version} · ${escapeHtml((t.created_at || '').substring(0, 10))}</p>`;

    if ((checklist.groups || []).length > 0) {
        html += '<div class="consent-section"><div class="consent-section-title">시술 항목 및 고객 상태 확인</div>';
        checklist.groups.forEach(g => {
            html += `<div class="consent-checklist-box">
                <div class="consent-checklist-box-title">${escapeHtml(g.group_title)}${g.required_all ? '<span class="badge-required">필수</span>' : ''}</div>`;
            if (g.group_note) html += `<div class="consent-checklist-box-note">${escapeHtml(g.group_note)}</div>`;
            (g.items || []).forEach(item => {
                html += `<label class="consent-checklist-item"><input type="checkbox" disabled> ${escapeHtml(item)}</label>`;
            });
            html += '</div>';
        });
        html += '</div>';
    }

    html += `<div class="consent-content-box">${t.content || '<span class="muted">내용이 없습니다.</span>'}</div>`;

    if (agreement.final_text) {
        html += `<div class="consent-final-agree-box"><label class="consent-checklist-item"><input type="checkbox" disabled> ${escapeHtml(agreement.final_text)}</label></div>`;
    }

    html += `<div class="consent-section">
        <div class="consent-section-header-row">
            <div class="diagram-section-title">시술 부위 표시</div>
            <span class="diagram-toggle-badge ${diagramOn ? 'is-on' : 'is-off'}">${diagramOn ? 'ON' : 'OFF'}</span>
        </div>
        <div class="consent-section-note">시술 부위를 클릭하여 위치나 범위를 표시해 주세요. (최대 6개)</div>`;
    html += diagramOn ? buildDiagramHtml(diagramInfo.panels) : '<p class="muted">이 동의서에는 시술 부위 도해가 없습니다.</p>';
    html += '</div>';

    document.getElementById('viewBody').innerHTML = html;
    document.getElementById('viewModal').style.display = 'flex';
}

function closeModal() { document.getElementById('consentModal').style.display = 'none'; }
function filterByIndustry(industry, btn) {
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.consent-card').forEach(card => {
        card.style.display = (industry === 'all' || card.dataset.industry === industry) ? 'flex' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
