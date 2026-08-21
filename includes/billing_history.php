<?php
/**
 * 결제 이력(ss_billing_history) 저장/조회 헬퍼.
 * 사전에 phpMyAdmin에서 ss_billing_history 테이블을 생성해야 한다.
 */
function recordBillingHistory(PDO $pdo, string $storeId, string $planName, int $amount, string $status = 'paid', ?string $memo = null): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO ss_billing_history (store_id, plan_name, amount, status, memo, paid_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$storeId, $planName, $amount, $status, $memo]);
    } catch (Throwable $e) {
        error_log('[billing_history] 결제 이력 저장 실패: ' . $e->getMessage());
    }
}

function getBillingHistory(PDO $pdo, string $storeId): array {
    try {
        $stmt = $pdo->prepare(
            'SELECT plan_name, amount, status, memo, paid_at
             FROM ss_billing_history WHERE store_id = ? ORDER BY paid_at DESC LIMIT 100'
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        error_log('[billing_history] 결제 이력 조회 실패: ' . $e->getMessage());
        return [];
    }
}
