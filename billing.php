<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ? AND owner_id = ?');
$stmt->execute([$storeId, $user['id']]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

$planNames = ['free' => '무료', 'basic' => '베이직', 'premium' => '프리미엄', 'enterprise' => '엔터프라이즈'];
$planFees  = ['free' => '무료', 'basic' => '9,900원/월', 'premium' => '29,900원/월', 'enterprise' => '문의'];

$trialDaysLeft = null;
if ($store['plan_status'] === 'trial' && $store['trial_ends_at']) {
    $trialDaysLeft = max(0, (int)ceil((strtotime($store['trial_ends_at']) - time()) / 86400));
}

$activePage = 'billing';
$pageTitle = htmlspecialchars($store['name']) . ' 결제 관리';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($user['name'] ?? ''); ?>님</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">결제 관리</h1></div>

      <?php if ($trialDaysLeft !== null): ?>
        <div class="trial-banner">
          <span>⏰ 무료체험 <?php echo $trialDaysLeft; ?>일 남았습니다. 카드를 등록하지 않으면 14일 후 서비스 이용이 중지됩니다.</span>
        </div>
      <?php endif; ?>

      <div class="settings-section">
        <h2>이용 플랜</h2>
        <div class="plan-info-list">
          <div class="plan-info-row"><span class="label">플랜명</span><span class="value"><?php echo $planNames[$store['plan']] ?? $store['plan']; ?></span></div>
          <div class="plan-info-row"><span class="label">요금</span><span class="value"><?php echo $planFees[$store['plan']] ?? '-'; ?></span></div>
          <div class="plan-info-row"><span class="label">상태</span><span class="value">
            <span class="status-badge <?php echo htmlspecialchars($store['plan_status']); ?>">
              <?php echo $store['plan_status'] === 'trial' ? '체험중' : ($store['plan_status'] === 'active' ? '사용 중' : '중지됨'); ?>
            </span>
          </span></div>
          <div class="plan-info-row"><span class="label">시작일</span><span class="value"><?php echo substr($store['created_at'], 0, 10); ?></span></div>
          <div class="plan-info-row"><span class="label">만료일</span><span class="value"><?php echo $store['trial_ends_at'] ? substr($store['trial_ends_at'], 0, 10) : ($store['plan_expires_at'] ? substr($store['plan_expires_at'], 0, 10) : '-'); ?></span></div>
        </div>
      </div>

      <div class="settings-section">
        <h2>결제 카드</h2>
        <p class="section-desc">플랜 결제에 사용할 카드를 관리합니다.</p>
        <div class="upload-box" style="cursor:default;">
          <div class="upload-icon">💳</div>
          <div class="upload-text">등록된 카드가 없습니다</div>
          <div class="upload-sub">클릭하여 결제 카드를 등록하세요</div>
        </div>
      </div>

      <div class="settings-section">
        <h2>결제 이력</h2>
        <div class="empty-state" style="padding:40px 20px;">결제 이력이 없습니다.</div>
      </div>
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
