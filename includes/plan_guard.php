<?php
/**
 * 매장의 플랜 상태(체험/운영/중지)를 검사해서
 * 만료된 경우 접근을 막는 공용 가드.
 * $store 배열에 plan_status, trial_ends_at 컬럼이 반드시 포함되어 있어야 한다.
 */

function isStorePlanExpired(array $store): bool {
    $status = $store['plan_status'] ?? 'trial';

    if ($status === 'active') {
        // 결제 연동 전이므로 active는 무조건 정상.
        // 결제 연동 후에는 plan_expires_at 도 함께 검사하도록 이 부분을 확장해야 한다.
        return false;
    }
    if ($status === 'stopped') {
        return true; // 관리자가 강제 중지시킨 매장
    }
    if ($status === 'trial') {
        if (empty($store['trial_ends_at'])) return false; // 안전장치: 날짜 없으면 막지 않음
        return strtotime($store['trial_ends_at']) < time();
    }
    return false;
}

/**
 * 만료된 매장이면 결제/설정 페이지를 제외한 모든 곳에서 접근을 막고 안내 화면을 출력한다.
 */
function enforcePlanAccess(array $store): void {
    if (!isStorePlanExpired($store)) return;

    // 결제하러 가거나 설정을 확인/삭제하는 페이지는 만료돼도 열어줘야 한다.
    $allowList = ['billing.php', 'store-settings.php', 'logout.php'];
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($current, $allowList, true)) return;

    http_response_code(402); // Payment Required
    $storeId = $store['id'] ?? '';
    $billingUrl = 'billing.php?id=' . urlencode($storeId);
    $reason = ($store['plan_status'] ?? '') === 'stopped'
        ? '이용이 일시 중지된 매장입니다.'
        : '무료 체험 기간이 종료되었습니다.';

    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>이용 제한 - CareForm</title>
    <link rel="stylesheet" href="/tattoo/assets/css/common.css"></head><body>
    <div class="auth-page"><div class="auth-card">
    <div class="auth-logo">CareForm</div>
    <p class="auth-subtitle">' . htmlspecialchars($reason) . '<br>결제 등록 후 계속 이용하실 수 있습니다.</p>
    <div class="auth-links"><a href="' . htmlspecialchars($billingUrl) . '" class="btn-primary" style="display:inline-block;text-decoration:none;">결제 관리로 이동</a></div>
    </div></div></body></html>';
    exit;
}
