<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/includes/platform_settings.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

$successMsg = '';
$errorMsg = '';
$companySuccessMsg = '';
$companyErrorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_fee') {
    $feeRaw = preg_replace('/\D/', '', $_POST['monthly_fee'] ?? '');
    $trialRaw = preg_replace('/\D/', '', $_POST['trial_days'] ?? '');

    if ($feeRaw === '' || (int)$feeRaw < 0) {
        $errorMsg = '월 사용료를 정확히 입력해주세요.';
    } elseif ($trialRaw === '' || (int)$trialRaw < 0) {
        $errorMsg = '무료체험 일수를 정확히 입력해주세요.';
    } else {
        setPlatformSetting($pdo, 'monthly_fee', (string)(int)$feeRaw);
        setPlatformSetting($pdo, 'trial_days', (string)(int)$trialRaw);
        $successMsg = '요금 정책이 저장되었습니다.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_company') {
    $companyName    = trim($_POST['company_name'] ?? '');
    $ceoName        = trim($_POST['ceo_name'] ?? '');
    $bizRegNo       = trim($_POST['biz_reg_no'] ?? '');
    $mailOrderNo    = trim($_POST['mail_order_no'] ?? '');
    $companyEmail   = trim($_POST['company_email'] ?? '');
    $companyAddress = trim($_POST['company_address'] ?? '');

    if ($companyName === '') {
        $companyErrorMsg = '상호(회사명)는 필수 입력 항목입니다.';
    } elseif ($companyEmail !== '' && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
        $companyErrorMsg = '이메일 형식을 확인해주세요.';
    } else {
        setPlatformSetting($pdo, 'company_name', $companyName);
        setPlatformSetting($pdo, 'ceo_name', $ceoName);
        setPlatformSetting($pdo, 'biz_reg_no', $bizRegNo);
        setPlatformSetting($pdo, 'mail_order_no', $mailOrderNo);
        setPlatformSetting($pdo, 'company_email', $companyEmail);
        setPlatformSetting($pdo, 'company_address', $companyAddress);
        $companySuccessMsg = '사업자 정보가 저장되었습니다.';
    }
}

$monthlyFee = (int) getPlatformSetting($pdo, 'monthly_fee', '5900');
$trialDays = (int) getPlatformSetting($pdo, 'trial_days', '14');

$companyName    = getPlatformSetting($pdo, 'company_name', 'CareForm');
$ceoName        = getPlatformSetting($pdo, 'ceo_name', '');
$bizRegNo       = getPlatformSetting($pdo, 'biz_reg_no', '');
$mailOrderNo    = getPlatformSetting($pdo, 'mail_order_no', '');
$companyEmail   = getPlatformSetting($pdo, 'company_email', '');
$companyAddress = getPlatformSetting($pdo, 'company_address', '');

$activePage = 'settings';
$pageTitle = '요금 설정';
require_once __DIR__ . '/includes/admin_layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($admin['name']); ?>님 (최고관리자)</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">요금 설정</h1></div>

      <?php if ($successMsg): ?><div class="alert-success"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
      <?php if ($errorMsg): ?><div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

      <div class="settings-section">
        <h2>구독 요금 정책</h2>
        <p class="section-desc">
          현재 모든 매장에 단일 요금제가 적용됩니다. 결제 연동이 완료되기 전까지는
          안내용 정책 값으로만 저장되며, 실제 자동결제 승인 API 연동 후 이 값이 청구 금액으로 사용됩니다.
        </p>
        <form method="post" style="max-width:360px;">
          <input type="hidden" name="action" value="update_fee">
          <div class="form-group">
            <label>월 사용료 (원) *</label>
            <input type="text" name="monthly_fee" value="<?php echo htmlspecialchars((string)$monthlyFee); ?>" required>
          </div>
          <div class="form-group">
            <label>무료체험 기간 (일) *</label>
            <input type="text" name="trial_days" value="<?php echo htmlspecialchars((string)$trialDays); ?>" required>
          </div>
          <button type="submit" class="btn-primary" style="width:auto;padding:12px 28px;">저장</button>
        </form>
      </div>

      <?php if ($companySuccessMsg): ?><div class="alert-success"><?php echo htmlspecialchars($companySuccessMsg); ?></div><?php endif; ?>
      <?php if ($companyErrorMsg): ?><div class="alert-error"><?php echo htmlspecialchars($companyErrorMsg); ?></div><?php endif; ?>

      <div class="settings-section">
        <h2>사업자 정보 (랜딩페이지 하단 표시)</h2>
        <p class="section-desc">
          여기 입력한 값은 홈페이지(landing.php) 하단 푸터에 그대로 노출됩니다.
          전자상거래법상 통신판매업자 표시 의무 대상이라면 실제 사업자등록증·통신판매업신고증에
          기재된 값과 정확히 일치해야 합니다. 다른 회사의 정보를 넣으면 안 됩니다.
        </p>
        <form method="post" style="max-width:480px;">
          <input type="hidden" name="action" value="update_company">
          <div class="form-group">
            <label>상호(서비스명) *</label>
            <input type="text" name="company_name" value="<?php echo htmlspecialchars($companyName); ?>" required>
          </div>
          <div class="form-group">
            <label>대표자명</label>
            <input type="text" name="ceo_name" value="<?php echo htmlspecialchars($ceoName); ?>">
          </div>
          <div class="form-group">
            <label>사업자등록번호</label>
            <input type="text" name="biz_reg_no" value="<?php echo htmlspecialchars($bizRegNo); ?>" placeholder="000-00-00000">
          </div>
          <div class="form-group">
            <label>통신판매업신고번호</label>
            <input type="text" name="mail_order_no" value="<?php echo htmlspecialchars($mailOrderNo); ?>" placeholder="해당 시에만 입력">
          </div>
          <div class="form-group">
            <label>이메일</label>
            <input type="text" name="company_email" value="<?php echo htmlspecialchars($companyEmail); ?>" placeholder="contact@yourdomain.com">
          </div>
          <div class="form-group">
            <label>사업장 주소</label>
            <input type="text" name="company_address" value="<?php echo htmlspecialchars($companyAddress); ?>">
          </div>
          <button type="submit" class="btn-primary" style="width:auto;padding:12px 28px;">저장</button>
        </form>
      </div>
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/admin_layout_foot.php'; ?>
