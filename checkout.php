<?php
/**
 * 결제 전용 화면.
 * 대시보드 사이드바/메뉴 없이 오직 결제 처리만을 위한 독립 페이지.
 * plan_guard.php의 결제 유도 모달 버튼이 여기로 연결된다.
 *
 * ⚠ PG(결제대행사) 연동 전 임시 처리다. 실제 카드 승인 절차 없이
 * 형식 검증만 통과하면 결제 완료로 간주한다. 반드시 실 서비스 전환 시
 * 이 블록을 PG API 승인/웹훅 처리로 교체해야 한다.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/includes/platform_settings.php';
require_once __DIR__ . '/includes/plan_guard.php';
require_once __DIR__ . '/includes/billing_history.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? ($_POST['id'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ? AND owner_id = ?');
$stmt->execute([$storeId, $user['id']]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

syncStorePlanStatus($pdo, $store);

$planName = getPlatformSetting($pdo, 'plan_name', '스탠다드 플랜');
$monthlyFee = (int)getPlatformSetting($pdo, 'monthly_fee', 5900);

$resultMsg = '';
$resultType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    $cardExpiry = trim($_POST['card_expiry'] ?? '');
    $cardBirth = preg_replace('/\D/', '', $_POST['card_birth'] ?? '');

    if (strlen($cardNumber) < 14 || strlen($cardNumber) > 16) {
        $resultMsg = '카드번호를 정확히 입력해주세요.';
        $resultType = 'error';
    } elseif ($cardExpiry === '') {
        $resultMsg = '유효기간을 입력해주세요.';
        $resultType = 'error';
    } elseif (strlen($cardBirth) < 6) {
        $resultMsg = '생년월일(또는 사업자번호)을 정확히 입력해주세요.';
        $resultType = 'error';
    } else {
        try {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));

            // ⚠ plan_status만 바꾸면 "plan=free인데 active" 라는 모순된 데이터가 남는다.
            // 결제 완료 시 plan도 함께 유료 등급(basic)으로 갱신한다.
            $pdo->prepare("UPDATE ss_stores SET plan = 'basic', plan_status = 'active', plan_expires_at = ? WHERE id = ?")
                ->execute([$expiresAt, $store['id']]);

            $store['plan'] = 'basic';
            $store['plan_status'] = 'active';
            $store['plan_expires_at'] = $expiresAt;

            recordBillingHistory($pdo, $store['id'], $planName, $monthlyFee, 'paid', 'PG 연동 전 테스트 결제');

            $resultMsg = '결제가 완료되었습니다. 이제 서비스를 계속 이용하실 수 있습니다.';
            $resultType = 'success';
        } catch (Throwable $e) {
            error_log('[checkout] ' . $e->getMessage());
            $resultMsg = '결제 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
            $resultType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>결제하기 - CareForm</title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
<link rel="stylesheet" href="/tattoo/assets/css/theme-brand.css">
<style>
  html, body { margin:0; background:#eef0ed; }
  .checkout-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .checkout-card { background:#fff; border-radius:18px; padding:36px 32px; max-width:420px; width:100%; box-shadow:0 20px 50px rgba(0,0,0,.12); }
  .checkout-logo { font-weight:800; font-size:18px; color:#3E5544; margin-bottom:4px; }
  .checkout-title { font-size:20px; font-weight:700; margin:4px 0 20px; color:#222; }
  .checkout-plan-box { background:#f6f7f5; border-radius:12px; padding:16px 18px; margin-bottom:22px; }
  .checkout-plan-box .row { display:flex; justify-content:space-between; font-size:14px; color:#555; margin-bottom:6px; }
  .checkout-plan-box .row:last-child { margin-bottom:0; font-weight:700; color:#222; font-size:16px; }
  .checkout-form .form-group { margin-bottom:14px; }
  .checkout-form label { display:block; font-size:13px; color:#555; margin-bottom:6px; }
  .checkout-form input { width:100%; box-sizing:border-box; padding:11px 12px; border:1px solid #dcdfda; border-radius:8px; font-size:14px; }
  .checkout-row-2 { display:flex; gap:10px; }
  .checkout-row-2 .form-group { flex:1; }
  .pay-submit-btn { width:100%; padding:14px; border:none; border-radius:10px; background:#3E5544; color:#fff; font-size:15px; font-weight:700; cursor:pointer; margin-top:8px; }
  .pay-submit-btn:hover { background:#2e4033; }
  .checkout-alert { padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:16px; }
  .checkout-alert.error { background:#fdecec; color:#c0392b; }
  .checkout-alert.success { background:#eaf6ec; color:#2c7a3f; }
  .checkout-back { display:block; text-align:center; margin-top:16px; font-size:12px; color:#999; text-decoration:none; }
  .checkout-note { font-size:11px; color:#aaa; margin-top:18px; line-height:1.6; text-align:center; }
</style>
</head>
<body>
<div class="checkout-page">
  <div class="checkout-card">
    <div class="checkout-logo">CareForm</div>
    <div class="checkout-title">결제하기</div>

    <div class="checkout-plan-box">
      <div class="row"><span>매장명</span><span><?php echo htmlspecialchars($store['name']); ?></span></div>
      <div class="row"><span>플랜</span><span><?php echo htmlspecialchars($planName); ?></span></div>
      <div class="row"><span>결제 금액</span><span><?php echo number_format($monthlyFee); ?>원/월</span></div>
    </div>

    <?php if ($resultMsg): ?>
      <div class="checkout-alert <?php echo $resultType; ?>"><?php echo htmlspecialchars($resultMsg); ?></div>
    <?php endif; ?>

    <?php if ($resultType !== 'success'): ?>
      <form method="POST" class="checkout-form">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($storeId); ?>">
        <div class="form-group">
          <label>카드번호</label>
          <input type="text" name="card_number" inputmode="numeric" maxlength="16" placeholder="숫자만 입력" required>
        </div>
        <div class="checkout-row-2">
          <div class="form-group">
            <label>유효기간 (MM/YY)</label>
            <input type="text" name="card_expiry" placeholder="MM/YY" maxlength="5" required>
          </div>
          <div class="form-group">
            <label>생년월일/사업자번호</label>
            <input type="text" name="card_birth" inputmode="numeric" placeholder="6자리 또는 10자리" required>
          </div>
        </div>
        <button type="submit" class="pay-submit-btn"><?php echo number_format($monthlyFee); ?>원 결제하기</button>
      </form>
    <?php else: ?>
      <a href="dashboard.php" class="pay-submit-btn" style="display:block;text-decoration:none;box-sizing:border-box;text-align:center;">매장 목록으로 이동</a>
    <?php endif; ?>

    <a href="dashboard.php" class="checkout-back">나중에 하기 (매장 목록으로)</a>

    <p class="checkout-note">
      ※ 현재는 결제 게이트웨이(PG) 연동 전 테스트 화면입니다.<br>
      실 결제는 아직 발생하지 않으며, 카드 정보는 저장되지 않습니다.
    </p>
  </div>
</div>
</body>
</html>
