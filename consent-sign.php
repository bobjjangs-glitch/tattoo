<?php
require_once __DIR__ . '/includes/session.php';
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

$diagramConfig = require __DIR__ . '/includes/diagram_config.php';

$templateId = $_GET['template_id'] ?? ($_POST['template_id'] ?? '');
$customerId = $_GET['customer_id'] ?? ($_POST['customer_id'] ?? '');
$storeAppointmentId = $_GET['id'] ?? ($_POST['id'] ?? '');

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

$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ? LIMIT 1');
$stmt->execute([$template['store_id']]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM ss_customers WHERE id = ? LIMIT 1');
$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    http_response_code(404);
    exit('고객 정보를 찾을 수 없습니다.');
}

$checklistGroups = json_decode($template['checklist_items'] ?? '[]', true) ?: [];
$diagramType = $template['diagram_type'] ?? 'none';
$maxMarkers = 6;
$readOnly = false;
$existingMarkers = [];

$errors = [];

// ===== 제출 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {

    // 필수 그룹 체크 검증
    foreach ($checklistGroups as $gIndex => $group) {
        if (!empty($group['required_all'])) {
            $checkedItems = $_POST["checklist_g{$gIndex}"] ?? [];
            $requiredCount = count($group['items'] ?? []);
            if (count($checkedItems) < $requiredCount) {
                $errors[] = htmlspecialchars($group['title'] ?? '필수 항목') . '을 모두 체크해 주세요.';
            }
        }
    }

    // 최종 동의 검증
    if (!empty($template['final_agree_text']) && empty($_POST['final_agree'])) {
        $errors[] = '최종 동의 항목에 체크해 주세요.';
    }

    // 서명 검증
    $signatureData = $_POST['signature_data'] ?? '';
    if ($signatureData === '' || strpos($signatureData, 'data:image/png;base64,') !== 0) {
        $errors[] = '서명을 입력해 주세요.';
    }

    if (empty($errors)) {
        // 서명 이미지 저장
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

        // 체크리스트 응답 수집
        $checklistAnswers = [];
        foreach ($checklistGroups as $gIndex => $group) {
            $checklistAnswers[$gIndex] = $_POST["checklist_g{$gIndex}"] ?? [];
        }
        $checklistAnswers['_final_agree'] = !empty($_POST['final_agree']);

        // 시술 부위 마커 수집 (body_markers_p{n}_z{n} 패턴)
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
                $_SESSION['staff_id'] ?? null,
                json_encode($template, JSON_UNESCAPED_UNICODE),
                json_encode($checklistAnswers, JSON_UNESCAPED_UNICODE),
                $signatureUrl,
            ]);

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
        <a href="javascript:history.back()" class="consent-modal-close">×</a>
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
// ===== 서명 패드 =====
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

// ===== 필수 체크박스/서명 검증 =====
function validateForm() {
    let allRequiredChecked = true;
    document.querySelectorAll('.consent-section').forEach(function (section) {
        // required_all 그룹은 PHP에서 렌더링할 때 data 속성으로 표시하는 게 이상적이나,
        // 여기서는 서버 측 최종 검증에 의존하고 클라이언트는 최소 검증만 수행한다.
    });

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
