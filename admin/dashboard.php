<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/includes/platform_settings.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

function safeCountA(PDO $pdo, string $sql, array $params = []): int {
    try { $s = $pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

$totalStores = safeCountA($pdo, 'SELECT COUNT(*) FROM ss_stores');
$trialStores = safeCountA($pdo, "SELECT COUNT(*) FROM ss_stores WHERE plan_status = 'trial'");
$activeStores = safeCountA($pdo, "SELECT COUNT(*) FROM ss_stores WHERE plan_status = 'active'");
$suspendedStores = safeCountA($pdo, "SELECT COUNT(*) FROM ss_stores WHERE plan_status = 'suspended'");
$canceledStores = safeCountA($pdo, "SELECT COUNT(*) FROM ss_stores WHERE plan_status = 'canceled'");
$newStoresThisMonth = safeCountA($pdo, "SELECT COUNT(*) FROM ss_stores WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");

$monthlyFee = (int) getPlatformSetting($pdo, 'monthly_fee', '5900');
$estimatedMrr = $activeStores * $monthlyFee;

// ss_billing_history가 있으면 이번달 실제 결제 완료 금액을 우선 사용한다.
$actualMonthRevenue = safeCountA($pdo,
    "SELECT COALESCE(SUM(amount),0) FROM ss_billing_history
     WHERE status = 'paid' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");

$recentStores = [];
try {
    $stmt = $pdo->prepare('SELECT id, name, owner_name, plan_status, created_at FROM ss_stores ORDER BY created_at DESC LIMIT 8');
    $stmt->execute();
    $recentStores = $stmt->fetchAll();
} catch (Throwable $e) {}

$statusLabelMap = [
    'trial' => '체험중',
    'active' => '운영 중',
    'suspended' => '중지됨',
    'canceled' => '해지됨',
];

$activePage = 'dashboard';
$pageTitle = '관리자 대시보드';
require_once __DIR__ . '/includes/admin_layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($admin['name']); ?>님 (최고관리자)</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">플랫폼 현황</h1></div>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">🏬 전체 매장 수</div>
          <div class="stat-value"><?php echo number_format($totalStores); ?></div>
          <div class="stat-sub">이번달 신규 <?php echo $newStoresThisMonth; ?>곳</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">🟢 유료 운영중</div>
          <div class="stat-value"><?php echo number_format($activeStores); ?></div>
          <div class="stat-sub">현재 요금 <?php echo number_format($monthlyFee); ?>원/월</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">⏰ 무료체험중</div>
          <div class="stat-value"><?php echo number_format($trialStores); ?></div>
          <div class="stat-sub">전환 대상 매장</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">🔴 중지됨</div>
          <div class="stat-value"><?php echo number_format($suspendedStores); ?></div>
          <div class="stat-sub">결제 실패 또는 강제 중지</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📪 해지됨</div>
          <div class="stat-value"><?php echo number_format($canceledStores); ?></div>
          <div class="stat-sub">구독 해지 매장</div>
        </div>
      </div>

      <div class="stat-cards" style="margin-top:14px;">
        <div class="stat-card">
          <div class="stat-label">💰 이번달 실제 매출</div>
          <div class="stat-value"><?php echo number_format($actualMonthRevenue); ?>원</div>
          <div class="stat-sub">ss_billing_history 결제완료 금액 합계 (실측치)</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📐 유료매장 기준 추정 MRR</div>
          <div class="stat-value"><?php echo number_format($estimatedMrr); ?>원</div>
          <div class="stat-sub">유료 매장 <?php echo $activeStores; ?>곳 × <?php echo number_format($monthlyFee); ?>원 (참고용 추정치)</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">🔎 자세히 보기</div>
          <div class="stat-value" style="font-size:15px;"><a href="billing.php">전체 결제 내역 →</a></div>
          <div class="stat-sub">매장별 결제 이력, 지난달 매출 확인</div>
        </div>
      </div>

      <div class="recent-card" style="margin-top:20px;">
        <div class="recent-card-head">
          <h2>최근 등록 매장</h2>
          <a href="stores.php" class="see-all">전체 보기 →</a>
        </div>
        <?php if (!$recentStores): ?>
          <div class="recent-empty">등록된 매장이 없습니다.</div>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>매장명</th><th>대표자</th><th>상태</th><th>가입일</th></tr></thead>
            <tbody>
            <?php foreach ($recentStores as $s): ?>
              <tr>
                <td><?php echo htmlspecialchars($s['name']); ?></td>
                <td><?php echo htmlspecialchars($s['owner_name'] ?: '-'); ?></td>
                <td><span class="status-badge <?php echo htmlspecialchars($s['plan_status']); ?>">
                  <?php echo $statusLabelMap[$s['plan_status']] ?? htmlspecialchars($s['plan_status']); ?>
                </span></td>
                <td><?php echo htmlspecialchars(substr($s['created_at'], 0, 10)); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/admin_layout_foot.php'; ?>
