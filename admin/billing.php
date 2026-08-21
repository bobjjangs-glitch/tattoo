<?php
/**
 * 관리자용 전체 결제 이력 화면.
 * ss_billing_history를 ss_stores와 조인해서 매장명/대표자와 함께 보여준다.
 */
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

$keyword = trim($_GET['keyword'] ?? '');

$sql = "SELECT h.id, h.plan_name, h.amount, h.status, h.memo, h.paid_at,
               s.id AS store_id, s.name AS store_name, s.owner_name
        FROM ss_billing_history h
        JOIN ss_stores s ON s.id = h.store_id";
$params = [];
if ($keyword !== '') {
    $sql .= " WHERE s.name LIKE ? OR s.owner_name LIKE ?";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}
$sql .= " ORDER BY h.paid_at DESC LIMIT 300";

$history = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[admin/billing] 결제 이력 조회 실패: ' . $e->getMessage());
}

function safeSumA(PDO $pdo, string $sql, array $params = []): int {
    try { $s = $pdo->prepare($sql); $s->execute($params); return (int)$s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

// 실제 결제된 금액 기준 매출 (추정치가 아닌 실측치)
$thisMonthRevenue = safeSumA($pdo,
    "SELECT COALESCE(SUM(amount),0) FROM ss_billing_history
     WHERE status = 'paid' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$lastMonthRevenue = safeSumA($pdo,
    "SELECT COALESCE(SUM(amount),0) FROM ss_billing_history
     WHERE status = 'paid' AND paid_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')
       AND paid_at < DATE_FORMAT(NOW(), '%Y-%m-01')");
$totalRevenue = safeSumA($pdo, "SELECT COALESCE(SUM(amount),0) FROM ss_billing_history WHERE status = 'paid'");
$totalPaidCount = safeSumA($pdo, "SELECT COUNT(*) FROM ss_billing_history WHERE status = 'paid'");

$activePage = 'billing';
$pageTitle = '결제 내역';
require_once __DIR__ . '/includes/admin_layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($admin['name']); ?>님 (최고관리자)</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">결제 내역</h1></div>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">💰 이번달 실제 매출</div>
          <div class="stat-value"><?php echo number_format($thisMonthRevenue); ?>원</div>
          <div class="stat-sub">실제 결제 완료 금액 합계</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📅 지난달 매출</div>
          <div class="stat-value"><?php echo number_format($lastMonthRevenue); ?>원</div>
          <div class="stat-sub">전월 결제 완료 금액 합계</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📈 전체 누적 매출</div>
          <div class="stat-value"><?php echo number_format($totalRevenue); ?>원</div>
          <div class="stat-sub">누적 결제 완료 건수 <?php echo number_format($totalPaidCount); ?>건</div>
        </div>
      </div>

      <div class="settings-section" style="margin-top:20px;">
        <form method="GET" style="margin-bottom:16px;display:flex;gap:8px;">
          <input type="text" name="keyword" placeholder="매장명 또는 대표자명 검색"
                 value="<?php echo htmlspecialchars($keyword); ?>"
                 style="flex:1;padding:10px 12px;border:1px solid #dcdfda;border-radius:8px;">
          <button type="submit" class="btn-primary" style="width:auto;padding:10px 20px;">검색</button>
        </form>

        <?php if (!$history): ?>
          <div class="empty-state" style="padding:40px 20px;">결제 이력이 없습니다.</div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr><th>결제일</th><th>매장명</th><th>대표자</th><th>플랜</th><th>금액</th><th>상태</th><th>비고</th></tr>
            </thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td><?php echo htmlspecialchars(substr($h['paid_at'], 0, 16)); ?></td>
                  <td><a href="stores.php?keyword=<?php echo urlencode($h['store_name']); ?>"><?php echo htmlspecialchars($h['store_name']); ?></a></td>
                  <td><?php echo htmlspecialchars($h['owner_name'] ?: '-'); ?></td>
                  <td><?php echo htmlspecialchars($h['plan_name']); ?></td>
                  <td><?php echo number_format($h['amount']); ?>원</td>
                  <td>
                    <?php echo $h['status'] === 'paid' ? '결제완료' : ($h['status'] === 'failed' ? '결제실패' : htmlspecialchars($h['status'])); ?>
                  </td>
                  <td style="color:#888;"><?php echo htmlspecialchars($h['memo'] ?? '-'); ?></td>
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
