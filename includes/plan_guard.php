<?php
/**
 * 매장의 플랜 상태(체험/운영/중지/해지)를 검사해서
 * 만료된 경우 접근을 막는 공용 가드.
 *
 * ⚠ ss_stores.plan_status는 ENUM('trial','active','suspended','canceled')이다.
 *   canceled는 사용자가 직접 구독을 해지한 경우를 위해 이미 스키마에 준비된 값이며,
 *   이 값도 반드시 만료(접근 차단) 상태로 취급해야 한다.
 *
 * ⚠ ss_stores.plan은 ENUM('free','basic','premium','enterprise')이다.
 *   결제가 완료되면 plan_status뿐 아니라 plan 값도 함께 갱신해야
 *   "무료 플랜인데 사용중" 같은 모순된 데이터가 생기지 않는다.
 *
 * ⚠ 이 파일을 사용하는 모든 페이지는 반드시 아래 형태로 호출해야 한다.
 *     enforcePlanAccess($pdo, $store);
 *
 * ⚠ http_response_code()로 4xx/5xx를 보내지 않는다. 200으로 유지해야
 *   일부 호스팅이 응답 본문을 자기네 기본 에러 페이지로 덮어쓰지 않는다.
 *
 * ⚠ 크론 미등록 상태에서는 이 파일의 검사가 "누군가 실제로 페이지에
 *   접속했을 때만" 실행된다. 아무도 접속하지 않으면 만료 처리와
 *   알림 메일 발송 모두 지연될 수 있다. 추후 크론 등록 시 이 한계가 해소된다.
 */

function syncStorePlanStatus(PDO $pdo, array &$store): void {
    $status = $store['plan_status'] ?? 'trial';

    if ($status === 'trial') {
        if (empty($store['trial_ends_at'])) return;
        if (strtotime($store['trial_ends_at']) >= time()) return;
        try {
            $pdo->prepare("UPDATE ss_stores SET plan_status = 'suspended' WHERE id = ? AND plan_status = 'trial'")
                ->execute([$store['id']]);
            $store['plan_status'] = 'suspended';
            $store['_expire_reason'] = 'trial';
        } catch (Throwable $e) {
            error_log('[plan_guard] 체험 만료 동기화 실패: ' . $e->getMessage());
        }
        return;
    }

    if ($status === 'active') {
        // PG 자동 정기결제 연동 전까지는, 결제 유효기간(plan_expires_at)이
        // 지나면 재결제가 필요한 상태로 되돌린다.
        if (empty($store['plan_expires_at'])) return;
        if (strtotime($store['plan_expires_at']) >= time()) return;
        try {
            $pdo->prepare("UPDATE ss_stores SET plan_status = 'suspended' WHERE id = ? AND plan_status = 'active'")
                ->execute([$store['id']]);
            $store['plan_status'] = 'suspended';
            $store['_expire_reason'] = 'renewal';
        } catch (Throwable $e) {
            error_log('[plan_guard] 정기결제 만료 동기화 실패: ' . $e->getMessage());
        }
    }
}

/**
 * 무료체험 종료 3일 전 이내이고 아직 안내 메일을 보낸 적 없다면 1회 발송한다.
 * 발송 성공 여부와 무관하게 플래그를 세워 재시도 폭탄을 막는다.
 */
function maybeSendTrialEndingNotice(PDO $pdo, array &$store): void {
    if (($store['plan_status'] ?? '') !== 'trial') return;
    if (empty($store['trial_ends_at'])) return;
    if (!empty($store['trial_notice_sent'])) return;

    $daysLeft = (int)ceil((strtotime($store['trial_ends_at']) - time()) / 86400);
    if ($daysLeft < 0 || $daysLeft > 3) return;

    try {
        $stmt = $pdo->prepare('SELECT email FROM ss_users WHERE id = ?');
        $stmt->execute([$store['owner_id']]);
        $owner = $stmt->fetch();
        if (!$owner || empty($owner['email'])) return;

        require_once __DIR__ . '/mailer.php';
        $sent = sendTrialEndingMail($owner['email'], $store['name'], $daysLeft, $store['id']);

        $pdo->prepare('UPDATE ss_stores SET trial_notice_sent = 1 WHERE id = ?')->execute([$store['id']]);
        $store['trial_notice_sent'] = 1;

        if (!$sent) {
            error_log('[plan_guard] 체험 만료 안내 메일 발송 실패(호스팅 메일 제한 가능성): store_id=' . $store['id']);
        }
    } catch (Throwable $e) {
        error_log('[plan_guard] 체험 만료 안내 처리 중 오류: ' . $e->getMessage());
    }
}

function isStorePlanExpired(array $store): bool {
    $status = $store['plan_status'] ?? 'trial';

    if ($status === 'active') return false;
    if ($status === 'suspended' || $status === 'canceled') return true;
    if ($status === 'trial') {
        if (empty($store['trial_ends_at'])) return false;
        return strtotime($store['trial_ends_at']) < time();
    }
    return false;
}

function enforcePlanAccess(PDO $pdo, array &$store): void {
    syncStorePlanStatus($pdo, $store);
    maybeSendTrialEndingNotice($pdo, $store);

    if (!isStorePlanExpired($store)) return;

    $allowList = ['billing.php', 'checkout.php', 'store-settings.php', 'logout.php', 'staff-logout.php'];
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($current, $allowList, true)) return;

    http_response_code(200);
    renderPlanExpiredModal($store);
    exit;
}

function renderPlanExpiredModal(array $store): void {
    $storeId = $store['id'] ?? '';
    $checkoutUrl = 'checkout.php?id=' . urlencode($storeId);
    $reason = $store['_expire_reason'] ?? null;
    $status = $store['plan_status'] ?? '';

    if ($reason === 'renewal') {
        $icon = '🔁';
        $title = '재결제가 필요합니다';
        $desc = '이용 기간(정기결제 주기)이 만료되어 서비스 이용이 중지되었습니다.';
    } elseif ($status === 'canceled') {
        $icon = '📪';
        $title = '구독이 해지되었습니다';
        $desc = '구독이 해지되어 서비스 이용이 중지되었습니다.';
    } elseif ($reason === 'trial' || (!empty($store['trial_ends_at']) && strtotime($store['trial_ends_at']) < time())) {
        $icon = '⏰';
        $title = '이용 기간이 만료되었습니다';
        $desc = '무료체험 기간이 종료되어 서비스 이용이 중지되었습니다.';
    } else {
        $icon = '🚫';
        $title = '이용이 중지되었습니다';
        $desc = '관리자에 의해 서비스 이용이 중지된 매장입니다.';
    }
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>결제가 필요합니다 - CareForm</title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
<link rel="stylesheet" href="/tattoo/assets/css/theme-brand.css">
<style>
  html, body { margin:0; background:#eef0ed; }
  .plan-blocked-backdrop { position:fixed; inset:0; background:rgba(20,20,20,.55); backdrop-filter: blur(4px); display:flex; align-items:center; justify-content:center; z-index:9999; padding:20px; }
  .plan-blocked-modal { background:#fff; border-radius:18px; padding:40px 32px; max-width:380px; width:100%; text-align:center; box-shadow:0 24px 60px rgba(0,0,0,.28); }
  .plan-blocked-modal .icon { font-size:42px; margin-bottom:14px; }
  .plan-blocked-modal h2 { font-size:19px; margin:0 0 10px; color:#222; }
  .plan-blocked-modal p { font-size:14px; color:#666; line-height:1.65; margin:0 0 24px; }
  .plan-blocked-modal .pay-btn { display:block; width:100%; padding:14px; border:none; border-radius:10px; background:#3E5544; color:#fff; font-size:15px; font-weight:700; cursor:pointer; text-decoration:none; box-sizing:border-box; margin-bottom:12px; }
  .plan-blocked-modal .pay-btn:hover { background:#2e4033; }
  .plan-blocked-modal .sub-link { font-size:12px; color:#999; text-decoration:none; }
  .plan-blocked-modal .sub-link:hover { text-decoration:underline; }
</style>
</head>
<body>
  <div class="plan-blocked-backdrop">
    <div class="plan-blocked-modal">
      <div class="icon"><?php echo $icon; ?></div>
      <h2><?php echo htmlspecialchars($title); ?></h2>
      <p>
        <?php echo htmlspecialchars($desc); ?><br>
        <strong>결제를 완료해야 이용이 가능합니다.</strong>
      </p>
      <a href="<?php echo htmlspecialchars($checkoutUrl); ?>" class="pay-btn">지금 결제하고 계속 이용하기</a>
      <a href="logout.php" class="sub-link">다른 계정으로 로그인</a>
    </div>
  </div>
</body>
</html>
    <?php
}
