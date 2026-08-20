<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/includes/platform_settings.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

$successMsg = '';
$errorMsg = '';

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

$monthlyFee = (int) getPlatformSetting($pdo, 'monthly_fee', '5900');
$trialDays = (int) getPlatformSetting($pdo, 'trial_days', '14');

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
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/admin_layout_foot.php'; ?>
