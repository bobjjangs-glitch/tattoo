<?php
/**
 * includes/diagram_helper.php
 * diagram_type 값을 정규화하고, 매칭 실패 시에도 원인을 추적할 수 있도록
 * 로그를 남기는 공용 함수 모음.
 * consent-sign.php, consent-edit.php 양쪽에서 반드시 이 함수를 통해서만 diagram_type을 처리한다.
 */

/**
 * DB에서 읽어온 diagram_type 원본 값을 정규화된 키로 변환
 * - 앞뒤 공백 제거
 * - 소문자 통일
 * - 과거 오타/구형 값에 대한 별칭(alias) 매핑
 */
function normalizeDiagramTypeKey(?string $rawValue, array $diagramConfig): string {
    $trimmed = trim((string)$rawValue);
    $lower   = strtolower($trimmed);

    // 이미 정확히 일치하면 그대로 사용
    if (array_key_exists($lower, $diagramConfig)) {
        return $lower;
    }

    // 과거에 잘못 입력됐을 가능성이 있는 값들에 대한 별칭 매핑
    $aliasMap = [
        'frontback'     => 'front_back',
        'front-back'    => 'front_back',
        'front_and_back'=> 'front_back',
        'facebody'      => 'face_body',
        'face-body'     => 'face_body',
        'handsfeet'     => 'hands_feet',
        'hands-feet'    => 'hands_feet',
        ''              => 'none',
    ];
    if (isset($aliasMap[$lower]) && array_key_exists($aliasMap[$lower], $diagramConfig)) {
        return $aliasMap[$lower];
    }

    // 여기까지 왔다면 정말로 매칭 실패 — 조용히 넘기지 않고 로그를 남긴다
    error_log(sprintf(
        '[diagram_helper] diagram_type 매칭 실패 — 원본값: "%s" (trim/lower: "%s") → none으로 폴백',
        $rawValue,
        $lower
    ));

    return 'none';
}

/**
 * 매칭 실패가 발생했는지 여부를 판단 (관리자 화면 경고 배너용)
 */
function diagramTypeMismatchDetected(?string $rawValue, string $resolvedKey): bool {
    $trimmed = trim((string)$rawValue);
    return $trimmed !== '' && $trimmed !== $resolvedKey && strtolower($trimmed) !== $resolvedKey;
}
