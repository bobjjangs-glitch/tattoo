<?php
/**
 * 시술 부위 표시 섹션 렌더링 (보기/수정/서명 화면 공용)
 *
 * 필요 변수 (호출 전에 반드시 세팅)
 *   $diagramType : 템플릿에 저장된 diagram_type (예: front_back)
 *   $readOnly    : true = 보기/미리보기(클릭 불가, 기존 마커만 표시), false = 서명 화면(마커 클릭 가능)
 *   $maxMarkers  : 최대 마커 수 (기본 6)
 *   $existingMarkers : 이미 서명된 문서를 다시 볼 때 넣을 마커 배열 (선택, 없으면 빈 배열)
 */
$diagramConfig = require __DIR__ . '/diagram_config.php';
$diagramType   = $diagramType ?? 'none';
$readOnly      = $readOnly ?? true;
$maxMarkers    = $maxMarkers ?? 6;
$existingMarkers = $existingMarkers ?? [];

if (!array_key_exists($diagramType, $diagramConfig)) {
    error_log('[diagram_render] 알 수 없는 diagram_type: ' . var_export($diagramType, true));
    $diagramType = 'none';
}

$currentDiagram = $diagramConfig[$diagramType];
?>

<?php if ($diagramType !== 'none'): ?>
<div class="diagram-section">
    <div class="diagram-section-header">
        <h3 class="diagram-section-title">
            시술 부위 표시
            <span class="diagram-toggle-badge">ON</span>
        </h3>
        <p class="diagram-section-desc">
            <?= $readOnly ? '고객이 표시한 시술 부위입니다.' : '시술 부위와 피해야 할 부위를 앞면 또는 뒷면에 표시해 주세요.' ?>
        </p>
    </div>

    <?php if (empty($currentDiagram['images'])): ?>
        <div class="diagram-empty-notice">
            ⚠ 이 동의서에 설정된 도해 이미지가 없습니다. 관리자에게 문의해 주세요.
        </div>
    <?php else: ?>
        <div class="body-diagram-multi-wrap">
            <?php foreach ($currentDiagram['images'] as $panelIndex => $imageSrc): ?>
                <div class="body-diagram-panel"
                     data-panel-index="<?= $panelIndex ?>"
                     data-max-markers="<?= (int)$maxMarkers ?>">

                    <div class="body-diagram-frame<?= $readOnly ? ' is-preview-only' : '' ?>"
                         data-panel-index="<?= $panelIndex ?>">
                        <img src="<?= htmlspecialchars($imageSrc) ?>?v=2"
                             alt="<?= htmlspecialchars($currentDiagram['label']) ?> 도해"
                             onerror="this.closest('.body-diagram-frame').classList.add('diagram-img-broken'); this.replaceWith(Object.assign(document.createElement('div'),{className:'thumb-broken',innerText:'이미지를 불러올 수 없습니다'}));">
                        <div class="body-diagram-marker-layer" data-panel-index="<?= $panelIndex ?>">
                            <?php
                            // ── 기존 마커를 항상 정적으로 그린다 (readOnly 여부와 무관) ──
                            if (!empty($currentDiagram['zones'])) {
                                foreach ($currentDiagram['zones'] as $zoneIndex => $zoneLabel) {
                                    $fieldName = "body_markers_p{$panelIndex}_z{$zoneIndex}";
                                    $markerList = $existingMarkers[$fieldName] ?? [];
                                    foreach ($markerList as $m) {
                                        $x = htmlspecialchars($m['x'] ?? 0);
                                        $y = htmlspecialchars($m['y'] ?? 0);
                                        echo "<div class=\"body-diagram-marker-dot is-static\" style=\"left:{$x}%;top:{$y}%;\"></div>";
                                    }
                                }
                            } else {
                                $fieldName = "body_markers_p{$panelIndex}_z0";
                                $markerList = $existingMarkers[$fieldName] ?? [];
                                foreach ($markerList as $m) {
                                    $x = htmlspecialchars($m['x'] ?? 0);
                                    $y = htmlspecialchars($m['y'] ?? 0);
                                    echo "<div class=\"body-diagram-marker-dot is-static\" style=\"left:{$x}%;top:{$y}%;\"></div>";
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <?php if (!empty($currentDiagram['zones'])): ?>
                        <?php foreach ($currentDiagram['zones'] as $zoneIndex => $zoneLabel): ?>
                            <?php
                                $fieldName = "body_markers_p{$panelIndex}_z{$zoneIndex}";
                                $fieldValue = isset($existingMarkers[$fieldName])
                                    ? htmlspecialchars(json_encode($existingMarkers[$fieldName], JSON_UNESCAPED_UNICODE))
                                    : '[]';
                            ?>
                            <div class="diagram-zone-label"><?= htmlspecialchars($zoneLabel) ?></div>
                            <?php if (!$readOnly): ?>
                            <input type="hidden"
                                   name="<?= $fieldName ?>"
                                   id="<?= $fieldName ?>"
                                   value="<?= $fieldValue ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php
                            $fieldName = "body_markers_p{$panelIndex}_z0";
                            $fieldValue = isset($existingMarkers[$fieldName])
                                ? htmlspecialchars(json_encode($existingMarkers[$fieldName], JSON_UNESCAPED_UNICODE))
                                : '[]';
                        ?>
                        <?php if (!$readOnly): ?>
                        <input type="hidden"
                               name="<?= $fieldName ?>"
                               id="<?= $fieldName ?>"
                               value="<?= $fieldValue ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$readOnly): ?>
        <div class="diagram-marker-badge">
            표시된 부위: <span id="diagramMarkerCount">0</span>/<?= (int)$maxMarkers ?>
        </div>
        <script>
        (function () {
            var MAX_MARKERS = <?= (int)$maxMarkers ?>;
            var panels = document.querySelectorAll('.body-diagram-panel');
            var totalCount = 0;
            var countBadge = document.getElementById('diagramMarkerCount');

            function refreshBadge() {
                if (countBadge) countBadge.textContent = totalCount;
            }

            panels.forEach(function (panel) {
                var frame = panel.querySelector('.body-diagram-frame');
                var layer = panel.querySelector('.body-diagram-marker-layer');
                if (!frame || !layer) return;

                var hiddenInputs = panel.querySelectorAll('input[type="hidden"]');
                var zoneInputs = {};
                hiddenInputs.forEach(function (input) {
                    var match = input.name.match(/^body_markers_p\d+_z(\d+)$/);
                    if (match) {
                        zoneInputs[match[1]] = input;
                        try {
                            var existing = JSON.parse(input.value || '[]');
                            totalCount += existing.length;
                        } catch (e) {}
                    }
                });
                refreshBadge();

                frame.addEventListener('click', function (e) {
                    if (frame.classList.contains('is-preview-only')) return;
                    if (totalCount >= MAX_MARKERS) {
                        alert('최대 ' + MAX_MARKERS + '개까지 표시할 수 있습니다.');
                        return;
                    }
                    var rect = frame.getBoundingClientRect();
                    var xPct = ((e.clientX - rect.left) / rect.width) * 100;
                    var yPct = ((e.clientY - rect.top) / rect.height) * 100;

                    var zoneKey = Object.keys(zoneInputs).length > 1
                        ? (xPct < 50 ? '0' : '1')
                        : Object.keys(zoneInputs)[0];
                    var targetInput = zoneInputs[zoneKey];
                    if (!targetInput) return;

                    var arr = [];
                    try { arr = JSON.parse(targetInput.value || '[]'); } catch (err) { arr = []; }
                    arr.push({ x: xPct.toFixed(2), y: yPct.toFixed(2) });
                    targetInput.value = JSON.stringify(arr);

                    totalCount++;
                    drawMarker(layer, xPct, yPct, targetInput);
                    refreshBadge();
                });
            });

            function drawMarker(layer, xPct, yPct, targetInput) {
                var dot = document.createElement('div');
                dot.className = 'body-diagram-marker-dot';
                dot.style.left = xPct + '%';
                dot.style.top = yPct + '%';
                dot.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var arr = [];
                    try { arr = JSON.parse(targetInput.value || '[]'); } catch (err) { arr = []; }
                    var idx = Array.from(dot.parentNode.children).indexOf(dot);
                    arr.splice(idx, 1);
                    targetInput.value = JSON.stringify(arr);
                    dot.remove();
                    totalCount--;
                    refreshBadge();
                });
                layer.appendChild(dot);
            }
        })();
        </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
