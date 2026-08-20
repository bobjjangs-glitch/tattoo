<?php
/**
 * consent-document-view.php
 * 고객이 실제로 서명 완료한 동의서 1건을 조회하는 화면
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();

$storeId    = $_GET['id'] ?? '';
$documentId = $_GET['document_id'] ?? '';

if ($storeId === '' || $documentId === '') {
    http_response_code(400);
    die('필수 파라미터(id, document_id)가 없습니다.');
}

$actor = requireStoreAccess($pdo, $storeId);
$canDelete = in_array($actor['role'], ['owner', 'admin'], true); // 서명 기록 삭제는 되돌릴 수 없는 작업이라 대표/관리자만 허용

$stmt = $pdo->prepare('SELECT id, name FROM ss_stores WHERE id = ? LIMIT 1');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없습니다.');
}

$stmt = $pdo->prepare('SELECT * FROM ss_consent_documents WHERE id = ? AND store_id = ? LIMIT 1');
$stmt->execute([$documentId, $storeId]);
$document = $stmt->fetch();
if (!$document) {
    http_response_code(404);
    die('동의서 기록을 찾을 수 없습니다.');
}

$stmt = $pdo->prepare('SELECT id, name, phone_masked FROM ss_customers WHERE id = ? LIMIT 1');
$stmt->execute([$document['customer_id']]);
$customer = $stmt->fetch();

// 서명 당시의 동의서 내용은 template_snapshot에 그대로 저장되어 있으므로,
// 이후에 템플릿이 수정/삭제되어도 서명 당시 그 내용 그대로 보여줄 수 있다.
$template = json_decode($document['template_snapshot'] ?? '{}', true) ?: [];
$checklistAnswers = json_decode($document['checklist_answers'] ?? '{}', true) ?: [];

/**
 * checklist_items 안의 그룹 구조를 정규화 (consent-sign.php와 동일한 로직 재사용)
 */
function normalizeChecklistGroupsForView($rawJson) {
    $decoded = json_decode($rawJson ?? '[]', true);
    if (!is_array($decoded)) {
        return [];
    }
    $rawGroups = (isset($decoded['groups']) && is_array($decoded['groups']))
        ? $decoded['groups']
        : $decoded;

    $normalized = [];
    foreach ($rawGroups as $g) {
        if (!is_array($g)) continue;
        $normalized[] = [
            'title'        => $g['title'] ?? $g['group_title'] ?? '',
            'note'         => $g['note'] ?? $g['group_note'] ?? '',
            'required_all' => !empty($g['required_all']),
            'items'        => is_array($g['items'] ?? null) ? $g['items'] : [],
        ];
    }
    return $normalized;
}

$checklistGroups = normalizeChecklistGroupsForView($template['checklist_items'] ?? null);

$diagramType     = $template['diagram_type'] ?? 'none';
$existingMarkers = $checklistAnswers['_body_markers'] ?? [];
$readOnly        = true;
$maxMarkers      = 6;

logAccess($pdo, $storeId, $actor, 'view_signed_consent', 'customer', $document['customer_id'], $template['title'] ?? '');

$pageTitle = ($template['title'] ?? '동의서') . ' - 서명 내역';
require_once __DIR__ . '/includes/flow_head.php';
?>
<div class="consent-flow-page">
    <div class="consent-flow-topbar">
        <a href="consent-history.php?id=<?= urlencode($storeId) ?>&customer_id=<?= urlencode($document['customer_id']) ?>" class="btn-back">‹ 위로</a>
        <div class="consent-flow-topbar-right">
            <a href="store.php?id=<?= urlencode($storeId) ?>" class="btn-exit">↪ 나가기</a>
        </div>
    </div>

    <div class="consent-flow-body">
        <h1 class="consent-flow-title"><?= htmlspecialchars($template['title'] ?? '동의서') ?></h1>
        <p class="consent-flow-subtitle">
            <?= htmlspecialchars($customer['name'] ?? '알 수 없음') ?>님 ·
            서명일 <?= htmlspecialchars(date('Y.m.d H:i', strtotime($document['signed_at'] ?? $document['created_at']))) ?>
        </p>

        <div class="consent-content-box">
            <?= $template['content'] ?? '<span class="muted">내용이 없습니다.</span>' ?>
        </div>

        <?php foreach ($checklistGroups as $groupIndex => $group): ?>
            <div class="consent-section">
                <div class="consent-section-header-row">
                    <div class="consent-section-title"><?= htmlspecialchars($group['title'] ?? '') ?></div>
                </div>
                <?php if (!empty($group['note'])): ?>
                    <p class="consent-section-note"><?= htmlspecialchars($group['note']) ?></p>
                <?php endif; ?>
                <?php $checkedItems = $checklistAnswers[$groupIndex] ?? []; ?>
                <?php foreach (($group['items'] ?? []) as $itemLabel): ?>
                    <?php $checked = in_array($itemLabel, $checkedItems, true); ?>
                    <label class="checklist-row">
                        <input type="checkbox" disabled <?= $checked ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($itemLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php include __DIR__ . '/includes/diagram_render.php'; ?>

        <?php if (!empty($template['final_agree_text'])): ?>
            <div class="consent-final-agree">
                <label class="checklist-row">
                    <input type="checkbox" disabled <?= !empty($checklistAnswers['_final_agree']) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($template['final_agree_text']) ?></span>
                </label>
            </div>
        <?php endif; ?>

        <div class="consent-section">
            <div class="consent-section-title">고객 서명</div>
            <?php if (!empty($document['signature_image_url'])): ?>
                <div class="signature-view-box">
                    <img src="<?= htmlspecialchars($document['signature_image_url']) ?>" alt="서명 이미지" style="max-width:100%;border:1px solid var(--border);border-radius:8px;background:#fff;">
                </div>
            <?php else: ?>
                <p class="muted">저장된 서명 이미지가 없습니다.</p>
            <?php endif; ?>
        </div>

        <?php if ($canDelete): ?>
            <div class="consent-section" style="border-top:1px solid var(--border);margin-top:24px;padding-top:20px;">
                <button type="button" class="btn-danger-outline" onclick="document.getElementById('deleteDocModal').style.display='flex'">
                    🗑 이 동의서 삭제
                </button>
                <p style="font-size:12px;color:var(--text-sub);margin-top:8px;">
                    삭제하면 법적 증빙 자료로서의 서명 기록이 영구적으로 사라집니다. 신중히 결정해주세요.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canDelete): ?>
<div class="modal-overlay" id="deleteDocModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title" style="color:var(--danger,#dc3545);">이 동의서를 삭제하시겠습니까?</h2>
    <p style="font-size:13px;color:var(--text-sub);margin-bottom:16px;">
        <?= htmlspecialchars($customer['name'] ?? '고객') ?>님의 "<?= htmlspecialchars($template['title'] ?? '동의서') ?>" 서명 기록과 서명 이미지가 영구적으로 삭제됩니다.<br>
        <strong>이 작업은 되돌릴 수 없습니다.</strong>
    </p>
    <form method="post" action="consent-document-delete.php">
      <input type="hidden" name="id" value="<?= htmlspecialchars($storeId) ?>">
      <input type="hidden" name="document_id" value="<?= htmlspecialchars($documentId) ?>">
      <input type="hidden" name="customer_id" value="<?= htmlspecialchars($document['customer_id']) ?>">
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('deleteDocModal').style.display='none'">취소</button>
        <button type="submit" class="btn-danger-outline" style="flex:1;">삭제 확정</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/flow_foot.php'; ?>
