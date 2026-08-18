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

$diagramConfig = include __DIR__ . '/includes/diagram_config.php';
if (!is_array($diagramConfig) || empty($diagramConfig)) {
    // diagram_config.php 파일 자체가 없거나 문법 오류로 빈 배열/false를 반환한 경우
    // → 여기서 die로 명확히 알려야 조용히 사라지는 사고를 막을 수 있음
    http_response_code(500);
    die('시술 부위 도해 설정 파일(includes/diagram_config.php)을 불러오지 못했습니다. 파일 존재 여부와 문법을 확인해주세요.');
}

$db = getDbConnection();

$templateId = $_GET['id'] ?? ($_POST['id'] ?? null);
$storeId    = $_GET['store_id'] ?? ($_POST['store_id'] ?? null);

if (!$storeId) {
    http_response_code(400);
    die('store_id가 필요합니다.');
}

// ── 저장 처리 (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title         = trim($_POST['title'] ?? '');
    $industry      = trim($_POST['industry'] ?? '');
    $content       = $_POST['content'] ?? '';
    $refundPolicy  = trim($_POST['refund_policy'] ?? '');
    $diagramTypeRaw = $_POST['diagram_type'] ?? 'none';

    // 정규화 후 화이트리스트 검증 — 라디오 버튼 외부에서 임의 값이 들어와도 안전하게 처리
    $diagramType = normalizeDiagramTypeKey($diagramTypeRaw, $diagramConfig);

    if ($title === '' || $industry === '') {
        http_response_code(422);
        die('제목과 업종은 필수 입력 항목입니다.');
    }

    $checklistItemsJson   = $_POST['checklist_items_json']   ?? '{"groups":[]}';
    $agreementClausesJson = $_POST['agreement_clauses_json'] ?? '{"final_text":""}';

    if ($templateId) {
        $stmt = $db->prepare(
            "UPDATE ss_consent_templates
             SET title = :title, industry = :industry, content = :content,
                 checklist_items = :checklist_items, agreement_clauses = :agreement_clauses,
                 refund_policy = :refund_policy, diagram_type = :diagram_type,
                 version = version + 1
             WHERE id = :id AND store_id = :store_id"
        );
        $stmt->execute([
            ':title'             => $title,
            ':industry'          => $industry,
            ':content'           => $content,
            ':checklist_items'   => $checklistItemsJson,
            ':agreement_clauses' => $agreementClausesJson,
            ':refund_policy'     => $refundPolicy ?: null,
            ':diagram_type'      => $diagramType, // 정규화된 값만 저장 — 앞으로 DB에는 공백/오타가 절대 들어가지 않음
            ':id'                => $templateId,
            ':store_id'          => $storeId,
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
            ':id'                => $newId,
            ':store_id'          => $storeId,
            ':industry'          => $industry,
            ':title'             => $title,
            ':content'           => $content,
            ':checklist_items'   => $checklistItemsJson,
            ':agreement_clauses' => $agreementClausesJson,
            ':refund_policy'     => $refundPolicy ?: null,
            ':diagram_type'      => $diagramType,
        ]);
        $templateId = $newId;
    }

    header('Location: consent-manage.php?store_id=' . urlencode($storeId) . '&saved=1');
    exit;
}

// ── 기존 데이터 로드 (수정 모드) ───────────────────────────────
$template = [
    'id' => '', 'title' => '', 'industry' => '', 'content' => '',
    'checklist_items' => '{"groups":[]}', 'agreement_clauses' => '{"final_text":""}',
    'refund_policy' => '', 'diagram_type' => 'none',
];

if ($templateId) {
    $stmt = $db->prepare("SELECT * FROM ss_consent_templates WHERE id = :id AND store_id = :store_id LIMIT 1");
    $stmt->execute([':id' => $templateId, ':store_id' => $storeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $template = $row;
    }
}

$diagramTypeRawFromDb = $template['diagram_type'] ?? 'none';
$currentDiagramType    = normalizeDiagramTypeKey($diagramTypeRawFromDb, $diagramConfig);
$diagramMismatch       = diagramTypeMismatchDetected($diagramTypeRawFromDb, $currentDiagramType);

require_once __DIR__ . '/includes/flow_head.php';
?>

<div class="consent-modal-page">
  <div class="consent-modal-topbar">
    <div class="consent-modal-title">동의서 수정</div>
  </div>

  <?php if ($diagramMismatch): ?>
    <div class="alert alert-warning">
      ⚠ DB에 저장된 diagram_type 값(<code><?= htmlspecialchars($diagramTypeRawFromDb) ?></code>)이
      설정 목록과 정확히 일치하지 않아 자동으로 "<?= htmlspecialchars($diagramConfig[$currentDiagramType]['label']) ?>"로 보정했습니다.
      저장을 다시 눌러 값을 정리해주세요.
    </div>
  <?php endif; ?>

  <form method="post" action="consent-edit.php" id="consentEditForm">
    <input type="hidden" name="id" value="<?= htmlspecialchars($template['id']) ?>">
    <input type="hidden" name="store_id" value="<?= htmlspecialchars($storeId) ?>">
    <input type="hidden" name="checklist_items_json" id="checklist_items_json" value="<?= htmlspecialchars($template['checklist_items']) ?>">
    <input type="hidden" name="agreement_clauses_json" id="agreement_clauses_json" value="<?= htmlspecialchars($template['agreement_clauses']) ?>">

    <label class="field-label">제목 *</label>
    <input type="text" name="title" value="<?= htmlspecialchars($template['title']) ?>" required class="field-input">

    <label class="field-label">카테고리 *</label>
    <input type="text" name="industry" value="<?= htmlspecialchars($template['industry']) ?>" required class="field-input">

    <label class="field-label">내용</label>
    <textarea name="content" id="richContent" class="rich-editor-textarea"><?= htmlspecialchars($template['content']) ?></textarea>

    <div id="checklistGroupEditor" class="checklist-group-editor">
      <!-- 기존 편집기 JS가 이 영역에 그룹/항목 입력 UI를 렌더링 -->
    </div>

    <label class="field-label">환불 정책</label>
    <textarea name="refund_policy" class="field-textarea"><?= htmlspecialchars($template['refund_policy']) ?></textarea>

    <!-- ── 도해 선택 영역 (5종 + 없음) ── -->
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

    <!-- 도해 미리보기 (선택한 타입에 맞춰 즉시 갱신, 읽기 전용) -->
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
      <button type="button" class="btn-cancel" onclick="history.back()">취소</button>
      <button type="submit" class="btn-primary">저장</button>
    </div>
  </form>
</div>

<script>
const DIAGRAM_CONFIG = <?= json_encode($diagramConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

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

document.getElementById('consentEditForm').addEventListener('submit', function () {
  if (typeof collectChecklistGroups === 'function') {
    document.getElementById('checklist_items_json').value = JSON.stringify({
      groups: collectChecklistGroups()
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/flow_foot.php'; ?>
