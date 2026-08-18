<?php
/**
 * includes/diagram_render.php
 * 용도: consent-edit.php 미리보기 영역 (읽기 전용 렌더링)
 * 필수 변수: $diagramType (정규화 완료된 키여야 함 — 호출 전 normalizeDiagramTypeKey() 사용)
 */

if (!isset($diagramType)) {
    $diagramType = 'none';
}

$diagramConfigForRender = include __DIR__ . '/diagram_config.php';

if (!array_key_exists($diagramType, $diagramConfigForRender)) {
    // 이 지점까지 매칭 안 된 값이 온다면 호출부에서 normalizeDiagramTypeKey()를 안 거쳤다는 뜻
    error_log('[diagram_render] 정규화되지 않은 diagram_type이 전달됨: ' . $diagramType);
    $diagramType = 'none';
}

$panelsForRender = $diagramConfigForRender[$diagramType]['panels'] ?? [];
?>

<?php if (!empty($panelsForRender)): ?>
  <div class="body-diagram-multi-wrap">
    <?php foreach ($panelsForRender as $pIdx => $panel):
        $zoneLabels = is_array($panel['zones']) ? $panel['zones'] : null;
        $zoneCount  = $zoneLabels ? count($zoneLabels) : 1;
    ?>
      <div class="body-diagram-frame is-preview-only"
           data-panel-index="<?= $pIdx ?>"
           data-zone-count="<?= $zoneCount ?>">
        <img src="<?= htmlspecialchars($panel['image']) ?>"
             alt="시술 부위 도해"
             class="body-diagram-photo"
             onerror="this.parentElement.classList.add('diagram-img-broken')">
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
<?php else: ?>
  <p class="muted">이 동의서에는 시술 부위 도해가 없습니다.</p>
<?php endif; ?>
