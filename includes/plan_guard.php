<?php
/**
 * 매장의 플랜 상태(체험/운영/중지)를 검사해서
 * 만료된 경우 접근을 막는 공용 가드.
 *
 * ⚠ 상태값은 ss_stores.plan_status 컬럼에 실제로 저장되는 값과 반드시 일치해야 한다.
 *   이 프로젝트에서 저장되는 값은 'trial' / 'active' / 'suspended' 세 가지뿐이다.
 *
 * ⚠ 이 파일을 사용하는 모든 페이지는 반드시 아래 형태로 호출해야 한다.
 *     enforcePlanAccess($pdo, $store);
 *
 * ⚠ 중요: 여기서는 절대 http_response_code()로 4xx/5xx를 보내지 않는다.
 *   일부 호스팅(카페24 등)은 200이 아닌 응답이 오면 서버가 우리 HTML을
 *   무시하고 자기네 기본 안내 페이지로 덮어써버리기 때문에,
 *   반드시 200(정상 응답)으로 유지한 채 결제 안내 화면만 보여준다.
 */

function syncStorePlanStatus(PDO $pdo, array &$store): void {
    if (($store['plan_status'] ?? '') !== 'trial') return;
    if (empty($store['trial_ends_at'])) return;
    if (strtotime($store['trial_ends_at']) >= time()) return;

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

    if ($status === 'active') return false;
    if ($status === 'suspended') return true;
    if ($status === 'trial') {
        if (empty($store['trial_ends_at'])) return false;
        return strtotime($store['trial_ends_at']) < time();
    }
    return false;
}

function enforcePlanAccess(PDO $pdo, array &$store): void {
    syncStorePlanStatus($pdo, $store);

    if (!isStorePlanExpired($store)) return;

    $allowList = ['billing.php', 'store-settings.php', 'logout.php', 'staff-logout.php'];
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (in_array($current, $allowList, true)) return;

    // 402를 보내면 일부 호스팅이 응답 본문을 자기네 기본 에러 페이지로
    // 덮어써버리므로, 반드시 200으로 유지한다.
    http_response_code(200);
    renderPlanExpiredModal($store);
    exit;
}

function renderPlanExpiredModal(array $store): void {
    $storeId = $store['id'] ?? '';
    $billingUrl = 'billing.php?id=' . urlencode($storeId);
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
<title>결제가 필요합니다 - CareForm</title>
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
