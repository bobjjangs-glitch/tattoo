<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();

if (!($pdo instanceof PDO)) {
    http_response_code(500);
    error_log('[consent-sign.php] getDbConnection()이 PDO 인스턴스를 반환하지 않았습니다.');
    exit('DB 연결에 실패했습니다. 관리자에게 문의해 주세요.');
}

if (file_exists(__DIR__ . '/api/utils/Uuid.php')) {
    require_once __DIR__ . '/api/utils/Uuid.php';
}

if (!function_exists('generateUuidV4')) {
    function generateUuidV4() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// ── 접근 권한 체크: 이 파라미터 이름은 "storeAppointmentId"지만 실제로는 매장 ID(storeId)임 ──
$storeAppointmentId = $_GET['id'] ?? ($_POST['id'] ?? '');
if ($storeAppointmentId === '') {
    http_response_code(400);
    exit('필수 파라미터(id)가 없습니다.');
}
$actor = requireStoreAccess($pdo, $storeAppointmentId);

/**
 * checklist_items 컬럼에 저장된 JSON을 정규화해서 title/note/required_all/items 형태로 통일한다.
 * consent-edit.php가 저장하는 {"groups":[{group_title, group_note, required_all, items}]} 구조와
 * 예전 방식(껍데기 없는 단순 배열, title/note 키) 구조를 모두 흡수한다.
 * 이 함수를 거치지 않고 $template['checklist_items']를 직접 json_decode 하면 안 된다.
 */
function normalizeChecklistGroupsForSign($rawJson) {
    $decoded = json_decode($rawJson ?? '[]', true);
    if (!is_array($decoded)) {
        return [];
    }

    $rawGroups = (isset($decoded['groups']) && is_array($decoded['groups']))
        ? $decoded['groups']
        : $decoded;

    $normalized = [];
    foreach ($rawGroups as $g) {
        if (!is_array($g)) {
            continue;
        }
        $normalized[] = [
            'title'        => $g['title'] ?? $g['group_title'] ?? '',
            'note'         => $g['note'] ?? $g['group_note'] ?? '',
            'required_all' => !empty($g['required_all']),
            'items'        => is_array($g['items'] ?? null) ? $g['items'] : [],
        ];
    }
    return $normalized;
}

$diagramConfig = require __DIR__ . '/includes/diagram_config.php';

$templateId = $_GET['template_id'] ?? ($_POST['template_id'] ?? '');
$customerId = $_GET['customer_id'] ?? ($_POST['customer_id'] ?? '');

if ($templateId === '' || $customerId === '') {
    http_response_code(400);
    exit('필수 파라미터(template_id, customer_id)가 없습니다.');
}

$stmt = $pdo->prepare('SELECT * FROM ss_consent_templates WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$templateId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$template) {
    http_response_code(404);
    exit('동의서 템플릿을 찾을 수 없습니다.');
}

// 템플릿이 실제로 이 매장 소속인지 추가 확인 (다른 매장 템플릿 ID로 접근 시도 방지)
if ($template['store_id'] !== $storeAppointmentId) {
    http_response_code(403);
    exit('해당 매장의 동의서 템플릿이 아닙니다.');
}

$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ? LIMIT 1');
$stmt->execute([$template['store_id']]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM ss_customers WHERE id = ? AND store_id = ? LIMIT 1');
$stmt->execute([$customerId, $template['store_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    http_response_code(404);
    exit('고객 정보를 찾을 수 없습니다.');
}

$checklistGroups = normalizeChecklistGroupsForSign($template['checklist_items'] ?? null);

$diagramType = $template['diagram_type'] ?? 'none';
$maxMarkers = 6;
$readOnly = false;
$existingMarkers = [];

$errors = [];

// ===== 제출 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {

    foreach ($checklistGroups as $gIndex => $group) {
        if (!empty($group['required_all'])) {
            $checkedItems = $_POST["checklist_g{$gIndex}"] ?? [];
            $requiredCount = count($group['items'] ?? []);
            if (count($checkedItems) < $requiredCount) {
                $errors[] = htmlspecialchars($group['title'] ?? '필수 항목') . '을 모두 체크해 주세요.';
            }
        }
    }

    if (!empty($template['final_agree_text']) && empty($_POST['final_agree'])) {
        $errors[] = '최종 동의 항목에 체크해 주세요.';
    }

    $signatureData = $_POST['signature_data'] ?? '';
    if ($signatureData === '' || strpos($signatureData, 'data:image/png;base64,') !== 0) {
        $errors[] = '서명을 입력해 주세요.';
    }

    if (empty($errors)) {
        $base64 = str_replace('data:image/png;base64,', '', $signatureData);
        $binaryData = base64_decode($base64);
        $signatureUuid = generateUuidV4();
        $signatureDir = __DIR__ . '/uploads/signatures/';
        if (!is_dir($signatureDir)) {
            mkdir($signatureDir, 0775, true);
        }
        $signaturePath = $signatureDir . $signatureUuid . '.png';
        file_put_contents($signaturePath, $binaryData);
        $signatureUrl = '/tattoo/uploads/signatures/' . $signatureUuid . '.png';

        $checklistAnswers = [];
        foreach ($checklistGroups as $gIndex => $group) {
            $checklistAnswers[$gIndex] = $_POST["checklist_g{$gIndex}"] ?? [];
        }
        $checklistAnswers['_final_agree'] = !empty($_POST['final_agree']);

        $bodyMarkers = [];
        $totalMarkerCount = 0;
        foreach ($_POST as $key => $value) {
            if (preg_match('/^body_markers_p\d+_z\d+$/', $key)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $bodyMarkers[$key] = $decoded;
                    $totalMarkerCount += count($decoded);
                }
            }
        }
        if ($totalMarkerCount > $maxMarkers) {
            $errors[] = "표시 가능한 부위는 최대 {$maxMarkers}개입니다.";
        } else {
            $checklistAnswers['_body_markers'] = $bodyMarkers;

            $documentId = generateUuidV4();
            $insertStmt = $pdo->prepare('
                INSERT INTO ss_consent_documents
                    (id, store_id, customer_id, template_id, staff_id, template_snapshot, checklist_answers, signature_image_url, signed_at, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ');
            $insertStmt->execute([
                $documentId,
                $template['store_id'],
                $customerId,
                $templateId,
                $actor['actor_type'] === 'staff' ? $actor['actor_id'] : null,
                json_encode($template, JSON_UNESCAPED_UNICODE),
                json_encode($checklistAnswers, JSON_UNESCAPED_UNICODE),
                $signatureUrl,
            ]);

            logAccess($pdo, $template['store_id'], $actor, 'sign_consent', 'customer', $customerId, $template['title'] ?? '');

            header('Location: /tattoo/store.php?id=' . urlencode($store['id']) . '&signed=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($template['title']) ?></title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
</head>
<body class="flow-body">

<div class="consent-modal-page">
    <div class="consent-modal-topbar">
        <h2 class="consent-modal-title"><?= htmlspecialchars($template['title']) ?></h2>
        <a href="javascript:history.back()" class="consent-modal-close">&times;</a>
    </div>

    <div class="consent-modal-body">
        <?php if (!empty($errors)): ?>
            <div class="alert-warning">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" id="consentSignForm">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="template_id" value="<?= htmlspecialchars($templateId) ?>">
            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customerId) ?>">
            <input type="hidden" name="id" value="<?= htmlspecialchars($storeAppointmentId) ?>">
            <input type="hidden" name="signature_data" id="signatureDataInput" value="">

            <div class="rich-content">
                <?= $template['content'] ?? '' ?>
            </div>

            <?php foreach ($checklistGroups as $groupIndex => $group): ?>
                <div class="consent-section">
                    <div class="consent-section-header-row">
                        <div class="consent-section-title"><?= htmlspecialchars($group['title'] ?? '') ?></div>
                    </div>
                    <?php if (!empty($group['note'])): ?>
                        <p class="consent-section-note"><?= htmlspecialchars($group['note']) ?></p>
                    <?php endif; ?>
                    <?php foreach (($group['items'] ?? []) as $itemLabel): ?>
                        <label class="checklist-row">
                            <input type="checkbox" name="checklist_g<?= $groupIndex ?>[]" value="<?= htmlspecialchars($itemLabel) ?>">
                            <span><?= htmlspecialchars($itemLabel) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php include __DIR__ . '/includes/diagram_render.php'; ?>

            <?php if (!empty($template['final_agree_text'])): ?>
                <div class="consent-final-agree">
                    <label class="checklist-row">
                        <input type="checkbox" name="final_agree" value="1" id="finalAgreeCheckbox">
                        <span><?= htmlspecialchars($template['final_agree_text']) ?></span>
                    </label>
                </div>
            <?php endif; ?>

            <div class="consent-section">
                <div class="consent-section-title">서명</div>
                <canvas id="signature-pad"></canvas>
                <div class="signature-actions">
                    <button type="button" class="btn-mini" id="clearSignatureBtn">서명 지우기</button>
                </div>
            </div>

            <button type="submit" class="btn-primary btn-full" id="submitBtn" disabled>동의하고 제출하기</button>
        </form>
    </div>
</div>

<script>
const canvas = document.getElementById('signature-pad');
const ctx = canvas.getContext('2d');
let isDrawing = false;
let hasSignature = false;

function resizeCanvas() {
    const ratio = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * ratio;
    canvas.height = 180 * ratio;
    ctx.scale(ratio, ratio);
    ctx.strokeStyle = '#222';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    if (e.touches && e.touches[0]) {
        return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
    }
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
}

function startDraw(e) {
    isDrawing = true;
    hasSignature = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
    validateForm();
}
function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
}
function endDraw() { isDrawing = false; }

canvas.addEventListener('mousedown', startDraw);
canvas.addEventListener('mousemove', draw);
canvas.addEventListener('mouseup', endDraw);
canvas.addEventListener('touchstart', startDraw);
canvas.addEventListener('touchmove', draw);
canvas.addEventListener('touchend', endDraw);

document.getElementById('clearSignatureBtn').addEventListener('click', function () {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSignature = false;
    validateForm();
});

function validateForm() {
    const finalAgree = document.getElementById('finalAgreeCheckbox');
    const finalAgreeOk = !finalAgree || finalAgree.checked;

    document.getElementById('submitBtn').disabled = !(hasSignature && finalAgreeOk);
}

document.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
    cb.addEventListener('change', validateForm);
});
validateForm();

document.getElementById('consentSignForm').addEventListener('submit', function () {
    document.getElementById('signatureDataInput').value = canvas.toDataURL('image/png');
});
</script>

</body>
</html>
