<?php
/**
 * 매장의 플랜 상태(체험/운영/중지)를 검사해서
 * 만료된 경우 접근을 막는 공용 가드.
 *
 * ⚠ 상태값은 ss_stores.plan_status 컬럼에 실제로 저장되는 값과 반드시 일치해야 한다.
 *   이 프로젝트에서 저장되는 값은 'trial' / 'active' / 'suspended' 세 가지뿐이다.
 *
 * ⚠ 이 파일의 함수를 호출하는 곳(store-dashboard.php, store.php, sales.php,
 *   consent.php, customer-register.php, consent-select.php)은 전부
 *   enforcePlanAccess($pdo, $store); 형태로 호출해야 한다.
 *   $store 하나만 넘기면 PHP가 TypeError로 즉시 죽고 500 오류가 뜬다 (배포 시 반드시 확인).
 */

/**
 * 체험 기간이 끝났는데 아직 plan_status가 'trial'로 남아있는 매장을
 * 이 요청 시점에서 즉시 'suspended'로 DB에 반영한다.
 */
function syncStorePlanStatus(PDO $pdo, array &$store): void {
    if (($store['plan_status'] ?? '') !== 'trial') return;
    if (empty($store['trial_ends_at'])) return;
    if (strtotime($store['trial_ends_at']) >= time()) return; // 아직 안 끝남

    try {
        $pdo->prepare("UPDATE ss_stores SET plan_status = 'suspended' WHERE id = ? AND plan_status = 'trial'")
            ->execute([$store['id']]);
        $store['plan_status'] = 'suspended';
    } catch (Throwable $e) {
        error_log('[plan_guard] 체험 만료 동기화 실패: ' . $e->getMessage());
    }
}

function isStorePlanExpired(array $store): bool {
    $status = $store['plan_status'] ?? 'trial';

    if ($status === 'active') {
        return false;
    }
    if ($status === 'suspended') {
        return true;
    }
    if ($status === 'trial') {
        if (empty($store['trial_ends_at'])) return false;
        return strtotime($store['trial_ends_at']) < time();
    }
    return false;
}

/**
 * 만료된 매장이면 결제/설정 페이지를 제외한 모든 곳에서 접근을 막고
 * 결제 유도 모달 화면을 출력한다.
 */
function enforcePlanAccess(PDO $pdo, array &$store): void {
    syncStorePlanStatus($pdo, $store);

    if (!isStorePlanExpired($store)) return;

    $allowList = ['billing.php', 'store-settings.php', 'logout.php', 'staff-logout.php'];
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($current, $allowList, true)) return;

    http_response_code(402); // Payment Required
    renderPlanExpiredModal($store);
    exit;
}

/**
 * 어두운 반투명 배경 위에 결제 유도 모달 카드를 띄운다.
 */
function renderPlanExpiredModal(array $store): void {
    $storeId = $store['id'] ?? '';
    $billingUrl = 'billing.php?id=' . urlencode($storeId);

    // trial_ends_at이 있는 상태에서 만료된 것이면 "체험 종료로 인한 자동 중지",
    // trial_ends_at이 없는데 suspended면 "관리자가 직접 중지시킨 매장".
    $isAutoExpiredTrial = !empty($store['trial_ends_at']) && strtotime($store['trial_ends_at']) < time();

    $icon = $isAutoExpiredTrial ? '⏰' : '🚫';
    $title = $isAutoExpiredTrial ? '이용 기간이 만료되었습니다' : '이용이 중지되었습니다';
    $desc = $isAutoExpiredTrial
        ? '무료체험 기간이 종료되어 서비스 이용이 중지되었습니다.'
        : '관리자에 의해 서비스 이용이 중지된 매장입니다.';
    ?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>이용 제한 - CareForm</title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
<link rel="stylesheet" href="/tattoo/assets/css/theme-brand.css">
<style>
  html, body { margin:0; background:#eef0ed; }
  .plan-blocked-backdrop {
    position:fixed; inset:0; background:rgba(20,20,20,.55);
    backdrop-filter: blur(4px);
    display:flex; align-items:center; justify-content:center;
    z-index:9999; padding:20px;
  }
  .plan-blocked-modal {
    background:#fff; border-radius:18px; padding:40px 32px;
    max-width:380px; width:100%; text-align:center;
    box-shadow:0 24px 60px rgba(0,0,0,.28);
    animation: planModalPop .18s ease-out;
  }
  @keyframes planModalPop {
    from { transform:scale(.94); opacity:0; }
    to   { transform:scale(1);   opacity:1; }
  }
  .plan-blocked-modal .icon { font-size:42px; margin-bottom:14px; }
  .plan-blocked-modal h2 { font-size:19px; margin:0 0 10px; color:#222; }
  .plan-blocked-modal p { font-size:14px; color:#666; line-height:1.65; margin:0 0 24px; }
  .plan-blocked-modal .pay-btn {
    display:block; width:100%; padding:14px; border:none; border-radius:10px;
    background:#3E5544; color:#fff; font-size:15px; font-weight:700; cursor:pointer;
    text-decoration:none; box-sizing:border-box; margin-bottom:12px;
  }
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
      <a href="<?php echo htmlspecialchars($billingUrl); ?>" class="pay-btn">지금 결제하고 계속 이용하기</a>
      <a href="logout.php" class="sub-link">다른 계정으로 로그인</a>
    </div>
  </div>
</body>
</html>
    <?php
}
