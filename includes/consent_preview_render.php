<?php
/**
 * 동의서 본문 + 체크리스트 + 최종 동의 + 도해 렌더링 (보기/수정 화면 공용)
 *
 * 필요 변수
 *   $template         : ss_consent_templates 1행 배열 (title, content, checklist_items, final_agree_text, diagram_type 포함)
 *   $checklistAnswers : 이미 서명된 문서의 응답값. 없으면 null 또는 빈 배열
 *   $readOnly         : true = 보기 전용, false = 체크박스 클릭 가능
 */
$checklistGroups  = json_decode($template['checklist_items'] ?? '[]', true) ?: [];
$checklistAnswers = $checklistAnswers ?? [];
$readOnly         = $readOnly ?? true;
?>

<div class="consent-preview-wrap">
    <h2 class="consent-doc-title"><?= htmlspecialchars($template['title'] ?? '') ?></h2>

    <div class="consent-content-box">
        <?= $template['content'] ?? '' ?>
    </div>

    <?php foreach ($checklistGroups as $groupIndex => $group): ?>
        <div class="consent-checklist-box">
            <div class="consent-checklist-box-title"><?= htmlspecialchars($group['title'] ?? '') ?></div>
            <?php if (!empty($group['note'])): ?>
                <div class="consent-checklist-box-note"><?= htmlspecialchars($group['note']) ?></div>
            <?php endif; ?>
            <?php foreach (($group['items'] ?? []) as $itemIndex => $itemLabel): ?>
                <?php $checked = in_array($itemLabel, $checklistAnswers[$groupIndex] ?? []); ?>
                <label class="consent-checklist-item">
                    <input type="checkbox"
                           name="checklist_g<?= $groupIndex ?>[]"
                           value="<?= htmlspecialchars($itemLabel) ?>"
                           <?= $checked ? 'checked' : '' ?>
                           <?= $readOnly ? 'disabled' : '' ?>>
                    <span><?= htmlspecialchars($itemLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($template['final_agree_text'])): ?>
        <div class="consent-final-agree-box">
            <label class="consent-checklist-item">
                <input type="checkbox" name="final_agree" value="1"
                       <?= !empty($checklistAnswers['_final_agree']) ? 'checked' : '' ?>
                       <?= $readOnly ? 'disabled' : '' ?>>
                <span><?= htmlspecialchars($template['final_agree_text']) ?></span>
            </label>
        </div>
    <?php endif; ?>

    <?php
    $diagramType     = $template['diagram_type'] ?? 'none';
    $existingMarkers = $checklistAnswers['_body_markers'] ?? [];
    include __DIR__ . '/diagram_render.php';
    ?>
</div>
