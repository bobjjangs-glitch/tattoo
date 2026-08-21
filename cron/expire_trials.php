<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI에서만 실행 가능합니다.');
}

require_once __DIR__ . '/../api/config/database.php';

try {
    $pdo = getDbConnection();

    $stmt1 = $pdo->prepare(
        "UPDATE ss_stores
         SET plan_status = 'suspended'
         WHERE plan_status = 'trial'
           AND trial_ends_at IS NOT NULL
           AND trial_ends_at < NOW()"
    );
    $stmt1->execute();
    $trialExpired = $stmt1->rowCount();

    $stmt2 = $pdo->prepare(
        "UPDATE ss_stores
         SET plan_status = 'suspended'
         WHERE plan_status = 'active'
           AND plan_expires_at IS NOT NULL
           AND plan_expires_at < NOW()"
    );
    $stmt2->execute();
    $renewalExpired = $stmt2->rowCount();

    $log = sprintf(
        '[%s] [expire_trials] 체험 만료 처리: %d건, 재결제 필요 처리: %d건',
        date('Y-m-d H:i:s'), $trialExpired, $renewalExpired
    );
    error_log($log);
    echo $log . PHP_EOL;
} catch (Throwable $e) {
    error_log('[expire_trials] 배치 실행 중 오류: ' . $e->getMessage());
    echo '오류 발생: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
