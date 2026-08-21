<?php
/**
 * 간단한 메일 발송 래퍼 (PHP 기본 mail() 함수 사용).
 *
 * ⚠ 닷홈 무료 호스팅 주의사항:
 *   - 무료 호스팅은 발신 메일(SMTP)에 제한이 있거나 스팸 필터에 걸려
 *     실제 수신이 안 될 수 있다. 반드시 테스트 발송 후 수신함(스팸함 포함)에서
 *     도착 여부를 직접 확인해야 한다.
 *   - 안정적으로 보내려면 추후 닷홈이 제공하는 SMTP 계정으로 발송 방식을
 *     교체하는 것을 권장한다.
 */
function sendTrialEndingMail(string $toEmail, string $storeName, int $daysLeft, string $storeId): bool {
    $subject = "[CareForm] {$storeName} 매장 무료체험이 {$daysLeft}일 후 종료됩니다";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $checkoutUrl = 'https://' . $host . '/tattoo/checkout.php?id=' . urlencode($storeId);

    $body = "안녕하세요, {$storeName} 매장 담당자님.\n\n"
        . "무료체험 기간이 {$daysLeft}일 후 종료됩니다.\n"
        . "체험 종료 후에는 결제를 완료해야 서비스를 계속 이용하실 수 있습니다.\n\n"
        . "지금 결제하기: {$checkoutUrl}\n\n"
        . "감사합니다.\nCareForm";

    $headers = "From: CareForm <no-reply@{$host}>\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($toEmail, $subject, $body, $headers);
}

/**
 * 유료 매장의 재결제일(plan_expires_at) 임박 안내 메일.
 */
function sendRenewalEndingMail(string $toEmail, string $storeName, int $daysLeft, string $storeId, int $monthlyFee): bool {
    $subject = "[CareForm] {$storeName} 매장 재결제일이 {$daysLeft}일 후입니다";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $checkoutUrl = 'https://' . $host . '/tattoo/checkout.php?id=' . urlencode($storeId);

    $body = "안녕하세요, {$storeName} 매장 담당자님.\n\n"
        . "이용 중인 플랜의 결제 유효기간이 {$daysLeft}일 후 만료됩니다.\n"
        . "재결제일까지 결제를 완료하지 않으면 서비스 이용이 중지되니 주의해주세요.\n\n"
        . "결제 금액: " . number_format($monthlyFee) . "원\n"
        . "지금 재결제하기: {$checkoutUrl}\n\n"
        . "감사합니다.\nCareForm";

    $headers = "From: CareForm <no-reply@{$host}>\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($toEmail, $subject, $body, $headers);
}
