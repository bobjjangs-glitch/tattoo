<?php
/**
 * consent-sign.php
 * 동의서 본문 확인 + 체크리스트(그룹) + 신체 부위 표시(다중 도해 타입 지원) + 서명 저장
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/includes/diagram_helper.php';

$user = requireLogin();
$pdo  = getDbConnection();

$storeId    = $_GET['id'] ?? ($_POST['store_id'] ?? '');
$customerId = $_GET['customer_id'] ?? ($_POST['customer_id'] ?? '');
$templateId = $_GET['template_id'] ?? ($_POST['template_id'] ?? '');

if ($storeId === '' || $customerId === '' || $templateId === '') {
    http_response_code(400);
    die('필수 파라미터(id, customer_id, template_id)가 없습니다.');
}

$stmt = $pdo->prepare("SELECT * FROM ss_stores WHERE id = ? LIMIT 1");
$stmt->execute([$storeId]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$store) { http_response_code(404); die('매장 정보를 찾을 수 없습니다.'); }

$stmt = $pdo->prepare("SELECT * FROM ss_customers WHERE id = ? AND store_id = ? LIMIT 1");
$stmt->execute([$customerId, $storeId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) { http_response_code(404); die('고객 정보를 찾을 수 없습니다.'); }

$stmt = $pdo->prepare("SELECT * FROM ss_consent_templates WHERE id = ? AND store_id = ? LIMIT 1");
$stmt->execute([$templateId, $storeId]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$template) { http_response_code(404); die('동의서 템플릿을 찾을 수 없습니다.'); }

// ── 도해 설정 로드 및 정규화 (공백/대소문자/구형값까지 흡수, 매칭 실패 시 로그 남김) ──
$diagramConfig = include __DIR__ . '/includes/diagram_config.php';
if (!is_array($diagramConfig) || empty($diagramConfig)) {
    http_response_code(500);
    die('시술 부위 도해 설정 파일을 불러오지 못했습니다.');
}
$diagramTypeRaw = $template['diagram_type'] ?? 'none';
$diagramType    = normalizeDiagramTypeKey($diagramTypeRaw, $diagramConfig);
$diagramPanels  = $diagramConfig[$diagramType]['panels'] ?? [];

/**
 * checklist_items 를 그룹 형태로 정규화
 */
function normalizeChecklistGroups($rawJson): array {
    if (empty($rawJson)) return [];
    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) return [];

    if (isset($decoded['groups']) && is_array($decoded['groups'])) {
        $groups = [];
        foreach ($decoded['groups'] as $g) {
            $items = [];
            foreach (($g['items'] ?? []) as $it) {
                $items[] = is_array($it) ? ($it['text'] ?? '') : $it;
            }
            $groups[] = [
                'group_title'  => $g['group_title'] ?? '확인 항목',
                'group_note'   => $g['group_note'] ?? '',
                'required_all' => !empty($g['required_all']),
                'items'        => $items,
            ];
        }
        return $groups;
    }

    $items = [];
    foreach ($decoded as $it) {
        $items[] = is_array($it) ? ($it['text'] ?? '') : $it;
    }
    if (empty($items)) return [];
    return [[
        'group_title'  => '동의 사항 확인',
        'group_note'   => '',
        'required_all' => true,
        'items'        => $items,
    ]];
}

$checklistGroups = normalizeChecklistGroups($template['checklist_items'] ?? null);

$finalAgreeText = '위 내용을 읽고 이해하였으며, 시술에 동의합니다.';
if (!empty($template['agreement_clauses'])) {
    $agree = json_decode($template['agreement_clauses'], true);
    if (is_array($agree) && !empty($agree['final_text'])) {
        $finalAgreeText = $agree['final_text'];
    }
}

function generateUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$formError = '';
$maxMarkers = 6;

// ===== 제출 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    $checklistPost = $_POST['checklist'] ?? [];

    $missingRequired = false;
    foreach ($checklistGroups as $gIdx => $group) {
        if (!$group['required_all']) continue;
        $checkedInGroup = $checklistPost[$gIdx] ?? [];
        if (count($checkedInGroup) < count($group['items'])) {
            $missingRequired = true;
            break;
        }
    }

    if ($missingRequired) {
        $formError = '필수 확인 항목을 모두 체크해주세요.';
    } elseif (empty($_POST['final_agree'])) {
        $formError = '최종 동의 체크가 필요합니다.';
    } else {
        $signatureData = $_POST['signature_data'] ?? '';
        if (strpos($signatureData, 'data:image/png;base64,') !== 0) {
            $formError = '서명을 입력해주세요.';
        } else {
            $bodyMarkers = [];
            $totalMarkerCount = 0;

            foreach ($diagramPanels as $pIdx => $panel) {
                $zones = $panel['zones'];
                $zoneLabels = is_array($zones) ? $zones : ['single'];
                $panelData = [];

                foreach ($zoneLabels as $zIdx => $zoneLabel) {
                    $fieldName = "body_markers_p{$pIdx}_z{$zIdx}";
                    $decoded = json_decode($_POST[$fieldName] ?? '[]', true);
                    $markerArr = is_array($decoded) ? $decoded : [];
                    $panelData[$zoneLabel] = $markerArr;
                    $totalMarkerCount += count($markerArr);
                }

                if (!empty($diagramPanels)) {
                    $bodyMarkers["panel{$pIdx}"] = $panelData;
                }
            }

            if ($totalMarkerCount > $maxMarkers) {
                $formError = '시술 부위 표시는 최대 ' . $maxMarkers . '개까지만 허용됩니다.';
            }
        }

        if ($formError === '') {
            $base64 = substr($signatureData, strlen('data:image/png;base64,'));
            $binary = base64_decode($base64);

            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/tattoo/uploads/signatures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $docId    = generateUuidV4();
            $fileName = $docId . '.png';

            if ($binary === false || file_put_contents($uploadDir . $fileName, $binary) === false) {
                $formError = '서명 이미지 저장에 실패했습니다. uploads/signatures 폴더 권한(775)을 확인하세요.';
            } else {
                $signatureUrl = '/tattoo/uploads/signatures/' . $fileName;

                $checklistAnswers = ['groups' => []];
                foreach ($checklistGroups as $gIdx => $group) {
                    $checkedInGroup = $checklistPost[$gIdx] ?? [];
                    $groupAnswer = ['group_title' => $group['group_title'], 'items' => []];
                    foreach ($group['items'] as $iIdx => $text) {
                        $groupAnswer['items'][] = [
                            'text'    => $text,
                            'checked' => in_array((string)$iIdx, $checkedInGroup, true),
                        ];
                    }
                    $checklistAnswers['groups'][] = $groupAnswer;
                }
                $checklistAnswers['final_agree']   = true;
                $checklistAnswers['_body_markers'] = $bodyMarkers;

                $stmt = $pdo->prepare("
                    INSERT INTO ss_consent_documents
                        (id, store_id, customer_id, template_id, staff_id,
                         template_snapshot, checklist_answers, signature_image_url,
                         pdf_file_url, pdf_sha256_hash, signed_at, created_at)
                    VALUES
                        (:id, :store_id, :customer_id, :template_id, :staff_id,
                         :template_snapshot, :checklist_answers, :signature_image_url,
                         NULL, NULL, NOW(), NOW())
                ");
                $stmt->execute([
                    ':id'                  => $docId,
                    ':store_id'            => $storeId,
                    ':customer_id'         => $customerId,
                    ':template_id'         => $templateId,
                    ':staff_id'            => $user['id'],
                    ':template_snapshot'   => json_encode($template, JSON_UNESCAPED_UNICODE),
                    ':checklist_answers'   => json_encode($checklistAnswers, JSON_UNESCAPED_UNICODE),
                    ':signature_image_url' => $signatureUrl,
                ]);

                header('Location: store.php?id=' . urlencode($storeId) . '&signed=1');
                exit;
            }
        }
    }
}

function industryBadge($industry) {
    $map = ['tattoo' => '타투', 'common' => '공통', 'perm' => '반영구', 'piercing' => '피어싱'];
    return $map[$industry] ?? $industry;
}

$pageTitle = $template['title'];
require_once __DIR__ . '/includes/flow_head.php';
?>
<div class="consent-modal-page">
    <div class="consent-modal-topbar">
        <h2 class="consent-modal-title"><?= htmlspecialchars($template['title']) ?></h2>
        <a href="consent-select.php?id=<?= urlencode($storeId) ?>&customer_id=<?= urlencode($customerId) ?>" class="consent-modal-close">&times;</a>
    </div>

    <div class="consent-modal-body">
        <?php if ($formError): ?>
            <div class="alert alert-error"><?= htmlspecialchars($formError) ?></div>
        <?php endif; ?>

        <form id="consent-sign-form" method="POST" action="consent-sign.php?id=<?= urlencode($storeId) ?>&customer_id=<?= urlencode($customerId) ?>&template_id=<?= urlencode($templateId) ?>">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="store_id" value="<?= htmlspecialchars($storeId) ?>">
            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customerId) ?>">
            <input type="hidden" name="template_id" value="<?= htmlspecialchars($templateId) ?>">
            <input type="hidden" name="signature_data" id="signature_data" value="">

            <?php foreach ($diagramPanels as $pIdx => $panel):
                $zoneLabels = is_array($panel['zones']) ? $panel['zones'] : ['single'];
                foreach ($zoneLabels as $zIdx => $zoneLabel): ?>
                <input type="hidden" name="body_markers_p<?= $pIdx ?>_z<?= $zIdx ?>" id="body_markers_p<?= $pIdx ?>_z<?= $zIdx ?>" value="[]">
            <?php endforeach; endforeach; ?>

            <!-- 체크리스트 그룹 -->
            <?php foreach ($checklistGroups as $gIdx => $group): ?>
            <div class="consent-section">
                <h3 class="consent-section-title"><?= htmlspecialchars($group['group_title']) ?></h3>
                <?php if ($group['group_note']): ?>
                    <p class="consent-section-note"><?= htmlspecialchars($group['group_note']) ?></p>
                <?php endif; ?>
                <?php foreach ($group['items'] as $iIdx => $text): ?>
                    <label class="checklist-row">
                        <input type="checkbox"
                               class="checklist-checkbox <?= $group['required_all'] ? 'required-group' : '' ?>"
                               data-group="<?= $gIdx ?>"
                               name="checklist[<?= $gIdx ?>][]"
                               value="<?= $iIdx ?>">
                        <span><?= htmlspecialchars($text) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <!-- 본문 -->
            <div class="rich-content">
                <?= $template['content'] ?? '' ?>
            </div>

            <?php if (!empty($diagramPanels)): ?>
            <!-- 신체 부위 표시 -->
            <div class="consent-section">
                <div class="consent-section-header-row">
                    <h3 class="consent-section-title">시술 부위 표시</h3>
                    <span class="marker-count-badge" id="marker-count-badge">0/<?= $maxMarkers ?></span>
                </div>
                <p class="consent-section-note">표시하고 싶은 부위를 클릭해주세요. 표시된 점을 다시 클릭하면 취소됩니다.</p>

                <div class="body-diagram-multi-wrap">
                    <?php foreach ($diagramPanels as $pIdx => $panel):
                        $zoneLabels = is_array($panel['zones']) ? $panel['zones'] : null;
                        $zoneCount  = $zoneLabels ? count($zoneLabels) : 1;
                    ?>
                    <div class="body-diagram-frame"
                         data-panel-index="<?= $pIdx ?>"
                         data-zone-count="<?= $zoneCount ?>">
                        <img src="<?= htmlspecialchars($panel['image']) ?>"
                             alt="신체 도해"
                             class="body-diagram-photo"
                             onerror="this.parentElement.classList.add('diagram-img-broken')">
                        <div class="body-diagram-grid" aria-hidden="true"></div>
                        <div class="body-diagram-markers"></div>
                        <?php if ($zoneLabels): ?>
                        <div class="body-diagram-divider-label">
                            <?php foreach ($zoneLabels as $label): ?>
                                <span><?= htmlspecialchars($label) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="consent-diagram-toolbar">
                    <button type="button" class="btn-mini" id="btn-reset-markers">전체 초기화</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- 최종 동의 -->
            <div class="consent-final-agree">
                <label class="checklist-row">
                    <input type="checkbox" id="final_agree" name="final_agree" value="1">
                    <span><?= htmlspecialchars($finalAgreeText) ?></span>
                </label>
            </div>

            <!-- 서명 -->
            <div class="consent-section">
                <h3 class="consent-section-title">서명</h3>
                <p class="consent-section-note">아래 영역에 서명해주세요</p>
                <canvas id="signature-pad" width="600" height="200"></canvas>
                <div class="signature-actions">
                    <button type="button" id="btn-clear-signature" class="btn-mini">다시 서명</button>
                </div>
            </div>

            <button type="submit" id="btn-submit" class="btn btn-primary btn-full" disabled>동의 완료</button>
        </form>
    </div>
</div>

<script>
const MAX_MARKERS = <?= (int)$maxMarkers ?>;
const markers = [];
const countBadge = document.getElementById('marker-count-badge');

document.querySelectorAll('.body-diagram-frame').forEach(function (frame) {
    const panelIdx  = parseInt(frame.dataset.panelIndex, 10);
    const zoneCount = parseInt(frame.dataset.zoneCount, 10);
    const photo     = frame.querySelector('.body-diagram-photo');
    const markerLayer = frame.querySelector('.body-diagram-markers');

    photo.addEventListener('click', function (e) {
        const rect = photo.getBoundingClientRect();
        const xPct = (e.clientX - rect.left) / rect.width * 100;
        const yPct = (e.clientY - rect.top) / rect.height * 100;

        const hitIdx = markers.findIndex(function (m) {
            return m.panelIdx === panelIdx &&
                   Math.abs(m.xPct - xPct) < 2.5 &&
                   Math.abs(m.yPct - yPct) < 2.5;
        });
        if (hitIdx !== -1) {
            markers.splice(hitIdx, 1);
            renderAllMarkers();
            syncMarkerInputs();
            return;
        }

        if (markers.length >= MAX_MARKERS) {
            alert('최대 ' + MAX_MARKERS + '개까지 표시할 수 있습니다.');
            return;
        }

        const zoneWidth = 100 / zoneCount;
        let zoneIdx = Math.floor(xPct / zoneWidth);
        if (zoneIdx >= zoneCount) zoneIdx = zoneCount - 1;
        if (zoneIdx < 0) zoneIdx = 0;

        markers.push({ xPct: xPct, yPct: yPct, panelIdx: panelIdx, zoneIdx: zoneIdx });
        renderAllMarkers();
        syncMarkerInputs();
    });
});

function renderAllMarkers() {
    document.querySelectorAll('.body-diagram-markers').forEach(function (layer) { layer.innerHTML = ''; });

    markers.forEach(function (m) {
        const frame = document.querySelector('.body-diagram-frame[data-panel-index="' + m.panelIdx + '"]');
        const layer = frame.querySelector('.body-diagram-markers');
        const dot = document.createElement('div');
        dot.className = 'marker-dot';
        dot.style.left = m.xPct + '%';
        dot.style.top = m.yPct + '%';
        layer.appendChild(dot);
    });

    countBadge.textContent = markers.length + '/' + MAX_MARKERS;
}

function syncMarkerInputs() {
    document.querySelectorAll('.body-diagram-frame').forEach(function (frame) {
        const panelIdx  = parseInt(frame.dataset.panelIndex, 10);
        const zoneCount = parseInt(frame.dataset.zoneCount, 10);
        const zoneWidth = 100 / zoneCount;

        for (let z = 0; z < zoneCount; z++) {
            const input = document.getElementById('body_markers_p' + panelIdx + '_z' + z);
            if (!input) continue;

            const zoneMarkers = markers
                .filter(function (m) { return m.panelIdx === panelIdx && m.zoneIdx === z; })
                .map(function (m) {
                    const localX = ((m.xPct - z * zoneWidth) / zoneWidth * 100);
                    return { x: localX.toFixed(2), y: m.yPct.toFixed(2) };
                });

            input.value = JSON.stringify(zoneMarkers);
        }
    });
}

const resetBtn = document.getElementById('btn-reset-markers');
if (resetBtn) {
    resetBtn.addEventListener('click', function () {
        markers.length = 0;
        renderAllMarkers();
        syncMarkerInputs();
    });
}

const canvas = document.getElementById('signature-pad');
const ctx = canvas.getContext('2d');
ctx.fillStyle = '#fff';
ctx.fillRect(0, 0, canvas.width, canvas.height);
ctx.strokeStyle = '#111';
ctx.lineWidth = 2;
ctx.lineCap = 'round';

let drawing = false;
let hasSignature = false;

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
        x: (clientX - rect.left) * (canvas.width / rect.width),
        y: (clientY - rect.top) * (canvas.height / rect.height)
    };
}
function startDraw(e) {
    drawing = true;
    hasSignature = true;
    const p = getPos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    e.preventDefault();
}
function draw(e) {
    if (!drawing) return;
    const p = getPos(e);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    e.preventDefault();
}
function endDraw() { drawing = false; validateForm(); }

canvas.addEventListener('mousedown', startDraw);
canvas.addEventListener('mousemove', draw);
window.addEventListener('mouseup', endDraw);
canvas.addEventListener('touchstart', startDraw, { passive: false });
canvas.addEventListener('touchmove', draw, { passive: false });
canvas.addEventListener('touchend', endDraw);

document.getElementById('btn-clear-signature').addEventListener('click', function () {
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    hasSignature = false;
    validateForm();
});

const form = document.getElementById('consent-sign-form');
const submitBtn = document.getElementById('btn-submit');
const finalAgree = document.getElementById('final_agree');
const requiredBoxes = document.querySelectorAll('.checklist-checkbox.required-group');

function validateForm() {
    const requiredOk = Array.from(requiredBoxes).every(function (cb) { return cb.checked; });
    const finalOk = finalAgree ? finalAgree.checked : true;
    submitBtn.disabled = !(requiredOk && finalOk && hasSignature);
}
requiredBoxes.forEach(function (cb) { cb.addEventListener('change', validateForm); });
if (finalAgree) finalAgree.addEventListener('change', validateForm);
validateForm();

form.addEventListener('submit', function () {
    document.getElementById('signature_data').value = canvas.toDataURL('image/png');
});
</script>
<?php require_once __DIR__ . '/includes/flow_foot.php'; ?>
