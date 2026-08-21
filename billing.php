<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/includes/platform_settings.php';
require_once __DIR__ . '/includes/plan_guard.php';
require_once __DIR__ . '/includes/billing_history.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ? AND owner_id = ?');
$stmt->execute([$storeId, $user['id']]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

syncStorePlanStatus($pdo, $store);

$planName = getPlatformSetting($pdo, 'plan_name', '스탠다드 플랜');
$monthlyFee = (int)getPlatformSetting($pdo, 'monthly_fee', 5900);

$isExpired = isStorePlanExpired($store);

$trialDaysLeft = null;
if ($store['plan_status'] === 'trial' && $store['trial_ends_at']) {
    $trialDaysLeft = max(0, (int)ceil((strtotime($store['trial_ends_at']) - time()) / 86400));
}

$history = getBillingHistory($pdo, $storeId);

$statusLabelMap = [
    'trial' => '체험중',
    'active' => '사용 중',
    'suspended' => '중지됨',
    'canceled' => '해지됨',
];

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

      <?php if ($isExpired): ?>
        <div class="alert-error">
          <?php
            if ($store['plan_status'] === 'canceled') {
                echo '구독이 해지된 매장입니다.';
            } elseif ($store['plan_status'] === 'suspended') {
                echo '이용이 중지된 매장입니다.';
            } else {
                echo '무료 체험 기간이 종료되었습니다.';
            }
          ?>
          <a href="checkout.php?id=<?php echo urlencode($storeId); ?>">결제를 진행해주세요.</a>
        </div>
      <?php elseif ($trialDaysLeft !== null): ?>
        <div class="trial-banner">
          <span>⏰ 무료체험 <?php echo $trialDaysLeft; ?>일 남았습니다. 체험 종료 시점에 결제가 필요합니다.</span>
        </div>
      <?php elseif ($store['plan_status'] === 'active' && $store['plan_expires_at']): ?>
        <div class="trial-banner">
          <span>다음 결제일: <?php echo substr($store['plan_expires_at'], 0, 10); ?> (PG 자동 재결제 연동 전이므로 만료일에 직접 재결제가 필요합니다.)</span>
        </div>
      <?php endif; ?>

      <div class="settings-section">
        <h2>이용 플랜</h2>
        <div class="plan-info-list">
          <div class="plan-info-row"><span class="label">플랜명</span><span class="value"><?php echo htmlspecialchars($planName); ?></span></div>
          <div class="plan-info-row"><span class="label">요금</span><span class="value"><?php echo number_format($monthlyFee); ?>원/월</span></div>
          <div class="plan-info-row"><span class="label">상태</span><span class="value">
            <span class="status-badge <?php echo htmlspecialchars($store['plan_status']); ?>">
              <?php echo $statusLabelMap[$store['plan_status']] ?? htmlspecialchars($store['plan_status']); ?>
            </span>
          </span></div>
          <div class="plan-info-row"><span class="label">시작일</span><span class="value"><?php echo substr($store['created_at'], 0, 10); ?></span></div>
          <div class="plan-info-row"><span class="label">만료일</span><span class="value"><?php echo $store['trial_ends_at'] ? substr($store['trial_ends_at'], 0, 10) : ($store['plan_expires_at'] ? substr($store['plan_expires_at'], 0, 10) : '-'); ?></span></div>
        </div>
      </div>

      <div class="settings-section">
        <h2>결제 카드</h2>
        <p class="section-desc">PG 연동 전까지는 매 결제 시 checkout.php에서 직접 입력합니다.</p>
        <div class="upload-box" style="cursor:default;">
          <div class="upload-icon">💳</div>
          <div class="upload-text">등록된 카드가 없습니다</div>
          <div class="upload-sub">PG 연동 후 카드 등록/자동결제가 지원됩니다</div>
        </div>
      </div>

      <div class="settings-section">
        <h2>결제 이력</h2>
        <?php if (!$history): ?>
          <div class="empty-state" style="padding:40px 20px;">결제 이력이 없습니다.</div>
        <?php else: ?>
          <table class="billing-history-table" style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
              <tr style="text-align:left;border-bottom:1px solid #e5e7e2;">
                <th style="padding:10px 8px;">결제일</th>
                <th style="padding:10px 8px;">플랜</th>
                <th style="padding:10px 8px;">금액</th>
                <th style="padding:10px 8px;">상태</th>
                <th style="padding:10px 8px;">비고</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr style="border-bottom:1px solid #f1f2ef;">
                  <td style="padding:10px 8px;"><?php echo htmlspecialchars(substr($h['paid_at'], 0, 16)); ?></td>
                  <td style="padding:10px 8px;"><?php echo htmlspecialchars($h['plan_name']); ?></td>
                  <td style="padding:10px 8px;"><?php echo number_format($h['amount']); ?>원</td>
                  <td style="padding:10px 8px;">
                    <?php echo $h['status'] === 'paid' ? '결제완료' : ($h['status'] === 'failed' ? '결제실패' : htmlspecialchars($h['status'])); ?>
                  </td>
                  <td style="padding:10px 8px;color:#888;"><?php echo htmlspecialchars($h['memo'] ?? '-'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
