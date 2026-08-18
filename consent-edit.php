<?php
// consent-edit.php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/includes/diagram_helper.php';

if (file_exists(__DIR__ . '/api/utils/Uuid.php')) {
    require_once __DIR__ . '/api/utils/Uuid.php';
} else {
    function generateUuidV4() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

$user = requireLogin();

$diagramConfig = include __DIR__ . '/includes/diagram_config.php';
if (!is_array($diagramConfig) || empty($diagramConfig)) {
    http_response_code(500);
    die('시술 부위 도해 설정 파일(includes/diagram_config.php)을 불러오지 못했습니다.');
}

$db = getDbConnection();

// ── 파라미터 이름을 나머지 파일들(consent.php, consent-view.php)과 통일 ──
// id = 매장(store) ID, template_id = 동의서 템플릿 ID
$storeId    = $_GET['id'] ?? ($_POST['store_id'] ?? null);
$templateId = $_GET['template_id'] ?? ($_POST['template_id'] ?? null);

if (!$storeId) {
    http_response_code(400);
    die('store_id(id 파라미터)가 필요합니다.');
}

// ── 매장 소유권 검증: 로그인한 사용자가 이 매장의 주인이 아니면 접근 차단 ──
$storeCheckStmt = $db->prepare('SELECT id, name FROM ss_stores WHERE id = ? AND owner_id = ?');
$storeCheckStmt->execute([$storeId, $user['id']]);
$store = $storeCheckStmt->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없거나 접근 권한이 없습니다.');
}

$errorMessage = '';
$postValues = [
    'title' => '', 'industry' => '', 'content' => '', 'refund_policy' => '',
    'diagram_type' => 'none', 'checklist_items_json' => '{"groups":[]}',
];

// ── 저장 처리 (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title          = trim($_POST['title'] ?? '');
    $industry       = trim($_POST['industry'] ?? '');
    $content        = $_POST['content'] ?? '';
    $refundPolicy   = trim($_POST['refund_policy'] ?? '');
    $diagramTypeRaw = $_POST['diagram_type'] ?? 'none';
    $diagramType    = normalizeDiagramTypeKey($diagramTypeRaw, $diagramConfig);

    $checklistItemsJson   = $_POST['checklist_items_json']   ?? '{"groups":[]}';
    $agreementClausesJson = $_POST['agreement_clauses_json'] ?? '{"final_text":""}';

    // 검증 실패 시에도 die()로 끊지 않고, 입력값을 화면에 그대로 되돌려준다
    $postValues = [
        'title' => $title, 'industry' => $industry, 'content' => $content,
        'refund_policy' => $refundPolicy, 'diagram_type' => $diagramType,
        'checklist_items_json' => $checklistItemsJson,
    ];

    if ($title === '' || mb_strlen($title) > 255) {
        $errorMessage = '제목을 1~255자 이내로 입력해주세요.';
    } elseif ($industry === '' || mb_strlen($industry) > 20) {
        $errorMessage = '카테고리를 1~20자 이내로 입력해주세요.';
    }

    if ($errorMessage === '') {
        try {
            if ($templateId) {
                // 이 템플릿이 정말 이 매장 소유인지 다시 한번 확인 (URL 조작 방지)
                $chk = $db->prepare('SELECT id FROM ss_consent_templates WHERE id = ? AND store_id = ?');
                $chk->execute([$templateId, $storeId]);
                if (!$chk->fetch()) {
                    http_response_code(404);
                    die('수정하려는 동의서를 찾을 수 없습니다.');
                }
                $stmt = $db->prepare(
                    "UPDATE ss_consent_templates
                     SET title = :title, industry = :industry, content = :content,
                         checklist_items = :checklist_items, agreement_clauses = :agreement_clauses,
                         refund_policy = :refund_policy, diagram_type = :diagram_type,
                         version = version + 1
                     WHERE id = :id AND store_id = :store_id"
                );
                $stmt->execute([
                    ':title' => $title, ':industry' => $industry, ':content' => $content,
                    ':checklist_items' => $checklistItemsJson, ':agreement_clauses' => $agreementClausesJson,
                    ':refund_policy' => $refundPolicy ?: null, ':diagram_type' => $diagramType,
                    ':id' => $templateId, ':store_id' => $storeId,
                ]);
            } else {
                $newId = generateUuidV4();
                $stmt = $db->prepare(
                    "INSERT INTO ss_consent_templates
                     (id, store_id, industry, title, content, checklist_items, agreement_clauses,
                      refund_policy, diagram_type, version, is_active, created_at)
                     VALUES (:id, :store_id, :industry, :title, :content, :checklist_items, :agreement_clauses,
                             :refund_policy, :diagram_type, 1, 1, NOW())"
                );
                $stmt->execute([
                    ':id' => $newId, ':store_id' => $storeId, ':industry' => $industry,
                    ':title' => $title, ':content' => $content,
                    ':checklist_items' => $checklistItemsJson, ':agreement_clauses' => $agreementClausesJson,
                    ':refund_policy' => $refundPolicy ?: null, ':diagram_type' => $diagramType,
                ]);
                $templateId = $newId;
            }

            // 존재하지 않던 consent-manage.php 대신, 실제 목록 화면인 consent.php로 이동
            header('Location: consent.php?id=' . urlencode($storeId) . '&saved=1');
            exit;
        } catch (Throwable $e) {
            error_log('[consent-edit.php save] ' . $e->getMessage());
            $errorMessage = '저장 중 오류가 발생했습니다.';
        }
    }
}

// ── 기존 데이터 로드 (수정 모드, GET 또는 POST 검증 실패 후 재표시) ──
$template = [
    'id' => $templateId ?? '', 'title' => '', 'industry' => '', 'content' => '',
    'checklist_items' => '{"groups":[]}', 'agreement_clauses' => '{"final_text":""}',
    'refund_policy' => '', 'diagram_type' => 'none',
];

if ($templateId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $db->prepare("SELECT * FROM ss_consent_templates WHERE id = :id AND store_id = :store_id LIMIT 1");
    $stmt->execute([':id' => $templateId, ':store_id' => $storeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $template = $row;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 검증 실패 시 방금 입력했던 값으로 폼을 다시 채운다
    $template['title']           = $postValues['title'];
    $template['industry']        = $postValues['industry'];
    $template['content']         = $postValues['content'];
    $template['refund_policy']   = $postValues['refund_policy'];
    $template['diagram_type']    = $postValues['diagram_type'];
    $template['checklist_items'] = $postValues['checklist_items_json'];
}

$diagramTypeRawFromDb = $template['diagram_type'] ?? 'none';
$currentDiagramType    = normalizeDiagramTypeKey($diagramTypeRawFromDb, $diagramConfig);
$diagramMismatch       = diagramTypeMismatchDetected($diagramTypeRawFromDb, $currentDiagramType);

// 체크리스트 편집기 초기 데이터 (JS에 그대로 넘겨서 화면에 렌더링)
$checklistGroupsForEditor = [];
$decodedChecklist = json_decode($template['checklist_items'] ?? '{"groups":[]}', true);
if (is_array($decodedChecklist) && isset($decodedChecklist['groups']) && is_array($decodedChecklist['groups'])) {
    foreach ($decodedChecklist['groups'] as $g) {
        $items = [];
        foreach (($g['items'] ?? []) as $it) {
            $items[] = is_array($it) ? ($it['text'] ?? '') : (string)$it;
        }
        $checklistGroupsForEditor[] = [
            'group_title'  => $g['group_title'] ?? '',
            'group_note'   => $g['group_note'] ?? '',
            'required_all' => !empty($g['required_all']),
            'items'        => $items,
        ];
    }
}

$agreementClausesForEditor = json_decode($template['agreement_clauses'] ?? '{"final_text":""}', true);
$finalAgreeTextForEditor = is_array($agreementClausesForEditor) ? ($agreementClausesForEditor['final_text'] ?? '') : '';

$pageTitle = '동의서 수정';
require_once __DIR__ . '/includes/flow_head.php';
?>

<div class="consent-modal-page">
  <div class="consent-modal-topbar">
    <div class="consent-modal-title">동의서 수정</div>
    <a href="consent.php?id=<?= urlencode($storeId) ?>" class="consent-modal-close">&times;</a>
  </div>

  <div class="consent-modal-body">
    <?php if ($diagramMismatch): ?>
      <div class="alert alert-warning">
        ⚠ DB에 저장된 diagram_type 값(<code><?= htmlspecialchars($diagramTypeRawFromDb) ?></code>)이
        설정 목록과 일치하지 않아 자동으로 "<?= htmlspecialchars($diagramConfig[$currentDiagramType]['label']) ?>"로 보정했습니다.
        저장을 다시 눌러 값을 정리해주세요.
      </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
      <div class="alert-error"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <form method="post" action="consent-edit.php?id=<?= urlencode($storeId) ?><?= $templateId ? '&template_id=' . urlencode($templateId) : '' ?>" id="consentEditForm">
      <input type="hidden" name="store_id" value="<?= htmlspecialchars($storeId) ?>">
      <input type="hidden" name="template_id" value="<?= htmlspecialchars($template['id']) ?>">
      <input type="hidden" name="checklist_items_json" id="checklist_items_json" value="<?= htmlspecialchars($template['checklist_items']) ?>">
      <input type="hidden" name="agreement_clauses_json" id="agreement_clauses_json" value="<?= htmlspecialchars($template['agreement_clauses']) ?>">

      <label class="field-label">제목 *</label>
      <input type="text" name="title" value="<?= htmlspecialchars($template['title']) ?>" required class="field-input" maxlength="255">

      <label class="field-label">카테고리 *</label>
      <input type="text" name="industry" value="<?= htmlspecialchars($template['industry']) ?>" required class="field-input" maxlength="20" placeholder="예: 타투, 피부관리, 공통">

      <label class="field-label">내용</label>
      <textarea name="content" id="richContent" class="rich-editor-textarea"><?= htmlspecialchars($template['content']) ?></textarea>

      <label class="field-label">체크리스트 그룹</label>
      <div id="checklistGroupEditor" class="checklist-group-editor"></div>
      <button type="button" class="btn-mini" id="btn-add-group">+ 그룹 추가</button>

      <label class="field-label">최종 동의 문구</label>
      <input type="text" name="final_agree_text_display" id="finalAgreeTextInput" class="field-input"
             value="<?= htmlspecialchars($finalAgreeTextForEditor) ?>"
             placeholder="예: 위 내용을 모두 읽고 이해하였으며 시술에 동의합니다.">

      <label class="field-label">환불 정책</label>
      <textarea name="refund_policy" class="field-textarea"><?= htmlspecialchars($template['refund_policy']) ?></textarea>

      <div class="diagram-type-label">시술 부위 도해 선택</div>
      <div class="diagram-type-selector">
        <?php foreach ($diagramConfig as $key => $type):
              if ($key === 'none') continue;
              $isSelected = ($currentDiagramType === $key);
        ?>
          <label class="diagram-type-card<?= $isSelected ? ' is-selected' : '' ?>">
            <input type="radio" name="diagram_type" value="<?= htmlspecialchars($key) ?>"
                   <?= $isSelected ? 'checked' : '' ?>
                   onchange="onDiagramTypeChange(this.value)">
            <div class="diagram-type-thumb-wrap">
              <img src="<?= htmlspecialchars($type['thumb']) ?>"
                   alt="<?= htmlspecialchars($type['label']) ?>"
                   onerror="this.closest('.diagram-type-thumb-wrap').classList.add('thumb-broken')">
            </div>
            <span class="diagram-type-name"><?= htmlspecialchars($type['label']) ?></span>
          </label>
        <?php endforeach; ?>

        <label class="diagram-type-card<?= ($currentDiagramType === 'none') ? ' is-selected' : '' ?>">
          <input type="radio" name="diagram_type" value="none"
                 <?= ($currentDiagramType === 'none') ? 'checked' : '' ?>
                 onchange="onDiagramTypeChange(this.value)">
          <div class="diagram-type-thumb-wrap diagram-type-thumb-empty">없음</div>
          <span class="diagram-type-name">도해 없음</span>
        </label>
      </div>

      <div class="diagram-preview-box">
        <div class="diagram-preview-label">선택한 도해 미리보기</div>
        <div id="diagramPreviewArea">
          <?php
            $diagramType = $currentDiagramType;
            include __DIR__ . '/includes/diagram_render.php';
          ?>
        </div>
      </div>

      <div class="modal-footer-actions">
        <a href="consent.php?id=<?= urlencode($storeId) ?>" class="btn-secondary">취소</a>
        <button type="submit" class="btn-primary">저장</button>
      </div>
    </form>
  </div>
</div>

<script>
const DIAGRAM_CONFIG = <?= json_encode($diagramConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let checklistGroups = <?= json_encode($checklistGroupsForEditor, JSON_UNESCAPED_UNICODE) ?>;

function onDiagramTypeChange(typeKey) {
  const type = DIAGRAM_CONFIG[typeKey];
  const previewArea = document.getElementById('diagramPreviewArea');

  if (!type || !type.panels || type.panels.length === 0) {
    previewArea.innerHTML = '<p class="muted">이 동의서에는 시술 부위 도해가 없습니다.</p>';
    return;
  }

  let html = '<div class="body-diagram-multi-wrap">';
  type.panels.forEach(function (panel, pIdx) {
    const zones = panel.zones;
    const zoneCount = Array.isArray(zones) ? zones.length : 1;

    html += '<div class="body-diagram-frame is-preview-only" data-panel-index="' + pIdx + '" data-zone-count="' + zoneCount + '">'
          + '<img src="' + panel.image + '" class="body-diagram-photo" alt="시술 부위 도해" '
          + 'onerror="this.parentElement.classList.add(\'diagram-img-broken\')">';

    if (Array.isArray(zones)) {
      html += '<div class="body-diagram-divider-label">';
      zones.forEach(function (label) { html += '<span>' + label + '</span>'; });
      html += '</div>';
    }
    html += '</div>';
  });
  html += '</div>';
  previewArea.innerHTML = html;
}

// ===== 체크리스트 그룹 편집기 =====
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

function renderChecklistEditor() {
  const wrap = document.getElementById('checklistGroupEditor');
  wrap.innerHTML = '';

  checklistGroups.forEach(function (group, gIdx) {
    const groupBox = document.createElement('div');
    groupBox.className = 'checklist-group-box';
    groupBox.innerHTML = `
      <div class="checklist-group-row">
        <input type="text" class="field-input checklist-group-title-input" placeholder="그룹 제목"
               data-gidx="${gIdx}" value="${escapeHtml(group.group_title)}">
        <label class="checklist-required-toggle">
          <input type="checkbox" data-gidx="${gIdx}" class="checklist-required-checkbox" ${group.required_all ? 'checked' : ''}>
          전체 필수
        </label>
        <button type="button" class="btn-mini danger" data-gidx="${gIdx}" data-action="remove-group">그룹 삭제</button>
      </div>
      <input type="text" class="field-input checklist-group-note-input" placeholder="그룹 설명(선택)"
             data-gidx="${gIdx}" value="${escapeHtml(group.group_note)}">
      <div class="checklist-item-list" data-gidx="${gIdx}">
        ${group.items.map(function (item, iIdx) {
          return `<div class="checklist-item-row">
                    <input type="text" class="field-input checklist-item-input" placeholder="항목 내용"
                           data-gidx="${gIdx}" data-iidx="${iIdx}" value="${escapeHtml(item)}">
                    <button type="button" class="btn-mini danger" data-gidx="${gIdx}" data-iidx="${iIdx}" data-action="remove-item">삭제</button>
                  </div>`;
        }).join('')}
      </div>
      <button type="button" class="btn-mini" data-gidx="${gIdx}" data-action="add-item">+ 항목 추가</button>
    `;
    wrap.appendChild(groupBox);
  });

  wrap.querySelectorAll('.checklist-group-title-input').forEach(function (el) {
    el.addEventListener('input', function () { checklistGroups[this.dataset.gidx].group_title = this.value; });
  });
  wrap.querySelectorAll('.checklist-group-note-input').forEach(function (el) {
    el.addEventListener('input', function () { checklistGroups[this.dataset.gidx].group_note = this.value; });
  });
  wrap.querySelectorAll('.checklist-required-checkbox').forEach(function (el) {
    el.addEventListener('change', function () { checklistGroups[this.dataset.gidx].required_all = this.checked; });
  });
  wrap.querySelectorAll('.checklist-item-input').forEach(function (el) {
    el.addEventListener('input', function () { checklistGroups[this.dataset.gidx].items[this.dataset.iidx] = this.value; });
  });
  wrap.querySelectorAll('[data-action="remove-group"]').forEach(function (el) {
    el.addEventListener('click', function () { checklistGroups.splice(this.dataset.gidx, 1); renderChecklistEditor(); });
  });
  wrap.querySelectorAll('[data-action="remove-item"]').forEach(function (el) {
    el.addEventListener('click', function () {
      checklistGroups[this.dataset.gidx].items.splice(this.dataset.iidx, 1);
      renderChecklistEditor();
    });
  });
  wrap.querySelectorAll('[data-action="add-item"]').forEach(function (el) {
    el.addEventListener('click', function () {
      checklistGroups[this.dataset.gidx].items.push('');
      renderChecklistEditor();
    });
  });
}

document.getElementById('btn-add-group').addEventListener('click', function () {
  checklistGroups.push({ group_title: '', group_note: '', required_all: false, items: [''] });
  renderChecklistEditor();
});

function collectChecklistGroups() {
  return checklistGroups
    .filter(function (g) { return g.group_title.trim() !== '' || g.items.some(function (it) { return it.trim() !== ''; }); })
    .map(function (g) {
      return {
        group_title: g.group_title,
        group_note: g.group_note,
        required_all: !!g.required_all,
        items: g.items.filter(function (it) { return it.trim() !== ''; }),
      };
    });
}

renderChecklistEditor();

document.getElementById('consentEditForm').addEventListener('submit', function () {
  document.getElementById('checklist_items_json').value = JSON.stringify({ groups: collectChecklistGroups() });
  document.getElementById('agreement_clauses_json').value = JSON.stringify({
    final_text: document.getElementById('finalAgreeTextInput').value,
  });
});
</script>

<?php require_once __DIR__ . '/includes/flow_foot.php'; ?>
