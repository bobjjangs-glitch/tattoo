<?php
require_once __DIR__ . '/session.php';

/**
 * 매장에 대한 접근 권한을 확인한다.
 * - 오너(ss_users 로그인)인 경우 role='owner'
 * - 직원(ss_store_staff 로그인)인 경우 role='admin' 또는 'staff'
 * 둘 다 아니면 직원 로그인 페이지로 리다이렉트한다.
 * ⚠ 최고관리자가 매장을 'suspended' 상태로 전환한 경우,
 *   실제 고객 데이터 화면(고객관리·매출관리·동의서관리)에는 접근할 수 없도록 차단한다.
 */
function requireStoreAccess(PDO $pdo, string $storeId): array {
    $storeStmt = $pdo->prepare('SELECT plan_status FROM ss_stores WHERE id = ?');
    $storeStmt->execute([$storeId]);
    $storeRow = $storeStmt->fetch();

    if ($storeRow && $storeRow['plan_status'] === 'suspended') {
        renderSuspendedNotice();
    }

    // 1) 오너 세션 확인
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id FROM ss_stores WHERE id = ? AND owner_id = ?');
        $stmt->execute([$storeId, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            return [
                'actor_type' => 'owner',
                'actor_id'   => $_SESSION['user_id'],
                'actor_name' => $_SESSION['user_name'] ?? '대표',
                'role'       => 'owner',
            ];
        }
    }

    // 2) 직원 세션 확인
    if (isset($_SESSION['staff_id']) && ($_SESSION['staff_store_id'] ?? '') === $storeId) {
        $stmt = $pdo->prepare('SELECT id, name, role, is_active FROM ss_store_staff WHERE id = ? AND store_id = ?');
        $stmt->execute([$_SESSION['staff_id'], $storeId]);
        $staff = $stmt->fetch();
        if ($staff && $staff['is_active']) {
            return [
                'actor_type' => 'staff',
                'actor_id'   => $staff['id'],
                'actor_name' => $staff['name'],
                'role'       => $staff['role'],
            ];
        }
    }

    // 3) 둘 다 아니면 직원 로그인 페이지로
    header('Location: staff-login.php?store_id=' . urlencode($storeId));
    exit;
}

/**
 * 매장이 결제 문제로 중지된 경우 안내 화면을 보여주고 종료한다.
 */
function renderSuspendedNotice(): void {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>서비스 이용 중지 - CareForm</title>
    <link rel="stylesheet" href="/tattoo/assets/css/common.css">
    <link rel="stylesheet" href="/tattoo/assets/css/theme-brand.css"></head><body>
    <div class="auth-page"><div class="auth-card">
    <div class="auth-logo">CareForm</div>
    <p class="auth-subtitle">현재 이 매장은 결제 문제로 서비스 이용이<br>일시 중지된 상태입니다.</p>
    <div class="auth-links">매장 대표께 문의하여 결제를 완료해주세요.<br><a href="mailto:support@salonform.club">support@salonform.club</a></div>
    </div></div></body></html>';
    exit;
}

/**
 * 접근/행동 로그 기록. 실패해도 화면이 죽지 않도록 방어 처리.
 */
function logAccess(PDO $pdo, string $storeId, array $actor, string $action, ?string $targetType = null, ?string $targetId = null, ?string $detail = null): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO ss_access_logs (id, store_id, actor_type, actor_id, actor_name, action, target_type, target_id, detail, ip_address, created_at)
             VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $storeId, $actor['actor_type'], $actor['actor_id'], $actor['actor_name'],
            $action, $targetType, $targetId, $detail, $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        error_log('[access_log] ' . $e->getMessage());
    }
}

/**
 * 오너 또는 admin 역할 직원만 허용. 일반 staff는 스타일이 적용된 안내 화면을 보여주고 종료.
 */
function requireAdminRole(array $actor, string $storeId = ''): void {
    if ($actor['role'] !== 'owner' && $actor['role'] !== 'admin') {
        http_response_code(403);
        $backUrl = $storeId !== '' ? 'store.php?id=' . urlencode($storeId) : 'javascript:history.back()';
        echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>접근 권한 없음 - CareForm</title>
        <link rel="stylesheet" href="/tattoo/assets/css/common.css">
        <link rel="stylesheet" href="/tattoo/assets/css/theme-brand.css"></head><body>
        <div class="auth-page"><div class="auth-card">
        <div class="auth-logo">CareForm</div>
        <p class="auth-subtitle">이 기능은 매장 대표 또는<br>관리자 권한 직원만 사용할 수 있습니다.</p>
        <div class="auth-links"><a href="' . htmlspecialchars($backUrl) . '" class="btn-primary" style="display:inline-block;text-decoration:none;">고객 관리로 돌아가기</a></div>
        </div></div></body></html>';
        exit;
    }
}
