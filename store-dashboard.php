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

function safeCount(PDO $pdo, string $sql, array $params): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
function safeFetchAll(PDO $pdo, string $sql, array $params): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

$newCustomersThisMonth = safeCount($pdo,
    'SELECT COUNT(*) FROM ss_customers WHERE store_id = ? AND created_at >= ? AND deleted_at IS NULL',
    [$storeId, $monthStart]);
$newCustomersLastMonth = safeCount($pdo,
    'SELECT COUNT(*) FROM ss_customers WHERE store_id = ? AND created_at BETWEEN ? AND ? AND deleted_at IS NULL',
    [$storeId, $lastMonthStart, $lastMonthEnd . ' 23:59:59']);
$totalCustomers = safeCount($pdo, 'SELECT COUNT(*) FROM ss_customers WHERE store_id = ? AND deleted_at IS NULL', [$storeId]);

$consentThisMonth = safeCount($pdo,
    'SELECT COUNT(*) FROM ss_consent_documents WHERE store_id = ? AND created_at >= ? AND deleted_at IS NULL',
    [$storeId, $monthStart]);
$consentLastMonth = safeCount($pdo,
    'SELECT COUNT(*) FROM ss_consent_documents WHERE store_id = ? AND created_at BETWEEN ? AND ? AND deleted_at IS NULL',
    [$storeId, $lastMonthStart, $lastMonthEnd . ' 23:59:59']);
$consentTotal = safeCount($pdo, 'SELECT COUNT(*) FROM ss_consent_documents WHERE store_id = ? AND deleted_at IS NULL', [$storeId]);
$templateCount = safeCount($pdo, 'SELECT COUNT(*) FROM ss_consent_templates WHERE store_id = ?', [$storeId]);

$todaySales = safeCount($pdo,
    'SELECT COALESCE(SUM(amount),0) FROM ss_sales WHERE store_id = ? AND sale_date = ? AND deleted_at IS NULL',
    [$storeId, $today]);
$monthSales = safeCount($pdo,
    'SELECT COALESCE(SUM(amount),0) FROM ss_sales WHERE store_id = ? AND sale_date >= ? AND deleted_at IS NULL',
    [$storeId, $monthStart]);
$lastMonthSales = safeCount($pdo,
    'SELECT COALESCE(SUM(amount),0) FROM ss_sales WHERE store_id = ? AND sale_date BETWEEN ? AND ? AND deleted_at IS NULL',
    [$storeId, $lastMonthStart, $lastMonthEnd]);

$hasTemplate = $templateCount > 0;
$hasCustomer = $totalCustomers > 0;
$hasSignedConsent = $consentTotal > 0;
$steps = [
    ['label' => '매장 생성 완료', 'done' => true],
    ['label' => '동의서 템플릿 확인', 'done' => $hasTemplate],
    ['label' => '첫 고객 등록', 'done' => $hasCustomer],
    ['label' => '첫 서명 완료', 'done' => $hasSignedConsent],
];
$doneCount = count(array_filter($steps, fn($s) => $s['done']));
$progressPct = round($doneCount / count($steps) * 100);

$trialDaysLeft = null;
if ($store['plan_status'] === 'trial' && $store['trial_ends_at']) {
    $trialDaysLeft = max(0, (int)ceil((strtotime($store['trial_ends_at']) - time()) / 86400));
}

$chartDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $chartDays[$d] = 0;
}
$rawDaily = safeFetchAll($pdo,
    'SELECT DATE(created_at) AS d, COUNT(*) AS cnt
     FROM ss_consent_documents
     WHERE store_id = ? AND created_at >= ? AND deleted_at IS NULL
     GROUP BY DATE(created_at)',
    [$storeId, date('Y-m-d', strtotime('-29 day'))]);
foreach ($rawDaily as $row) {
    if (isset($chartDays[$row['d']])) {
        $chartDays[$row['d']] = (int)$row['cnt'];
    }
}
$chartTotal = array_sum($chartDays);
$chartAvg = round($chartTotal / 30, 1);
$chartMax = max(1, max($chartDays));

$salesChartDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $salesChartDays[$d] = 0;
}
$rawSalesDaily = safeFetchAll($pdo,
    'SELECT sale_date AS d, COALESCE(SUM(amount),0) AS total
     FROM ss_sales
     WHERE store_id = ? AND sale_date >= ? AND deleted_at IS NULL
     GROUP BY sale_date',
    [$storeId, date('Y-m-d', strtotime('-29 day'))]);
foreach ($rawSalesDaily as $row) {
    if (isset($salesChartDays[$row['d']])) {
        $salesChartDays[$row['d']] = (int)$row['total'];
    }
}
$salesChartTotal = array_sum($salesChartDays);
$salesChartAvg = round($salesChartTotal / 30);
$salesChartMax = max(1, max($salesChartDays));

$recentCustomers = safeFetchAll($pdo,
    'SELECT id, name, phone_masked, created_at FROM ss_customers
     WHERE store_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5',
    [$storeId]);

$recentConsents = safeFetchAll($pdo,
    'SELECT cd.id, cd.created_at, c.name AS customer_name
     FROM ss_consent_documents cd
     LEFT JOIN ss_customers c
        ON c.id COLLATE utf8mb4_unicode_ci = cd.customer_id COLLATE utf8mb4_unicode_ci
     WHERE cd.store_id = ? AND cd.deleted_at IS NULL ORDER BY cd.created_at DESC LIMIT 5',
    [$storeId]);

$activePage = 'dashboard';
$storeName = $store['name'];
$pageTitle = $storeName . ' 대시보드';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

  <main class="main-content">
    <header class="dashboard-header">
      <span><?php echo htmlspecialchars($user['name'] ?? ''); ?>님</span>
    </header>

    <div class="page-content">

      <div class="shop-hub-header-card">
        <div>
          <div class="hub-date"><?php echo date('Y. n. j. (D)'); ?></div>
          <h1><?php echo htmlspecialchars($storeName); ?>
            <span class="status-badge <?php echo htmlspecialchars($store['plan_status']); ?>" style="background:rgba(255,255,255,.2);color:#fff;">
              <?php echo $store['plan_status'] === 'trial' ? '체험중' : ($store['plan_status'] === 'active' ? '운영 중' : '중지됨'); ?>
            </span>
          </h1>
          <p>오늘 등록된 고객과 동의서, 매출 현황을 확인하세요.</p>
        </div>
        <div class="hub-actions">
          <a href="sales.php?id=<?php echo urlencode($storeId); ?>" class="btn-white" style="text-decoration:none;">매출 관리</a>
          <a href="consent.php?id=<?php echo urlencode($storeId); ?>" class="btn-white" style="text-decoration:none;">동의서 관리</a>
          <a href="store.php?id=<?php echo urlencode($storeId); ?>" class="btn-white" style="text-decoration:none;">+ 고객 등록</a>
        </div>
      </div>

      <?php if ($trialDaysLeft !== null): ?>
        <div class="trial-banner">
          <span>⏰ 무료체험 <?php echo $trialDaysLeft; ?>일 남았습니다.</span>
          <button class="btn-upgrade">카드 등록하기</button>
        </div>
      <?php endif; ?>

      <div class="guide-card">
        <h2>시작 가이드</h2>
        <p style="font-size:13px;color:var(--text-sub);margin-bottom:16px;">
          살롱폼을 시작하기 위한 단계를 완료하세요 &nbsp; (<?php echo $doneCount; ?>/<?php echo count($steps); ?> 완료)
        </p>
        <div class="guide-progress-track">
          <div class="guide-progress-fill" style="width:<?php echo $progressPct; ?>%;"></div>
        </div>
        <div class="guide-checklist">
          <?php foreach ($steps as $step): ?>
            <div class="guide-item <?php echo $step['done'] ? 'done' : ''; ?>">
              <span class="guide-check"><?php echo $step['done'] ? '✓' : ''; ?></span>
              <span class="label"><?php echo htmlspecialchars($step['label']); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">💰 오늘 매출</div>
          <div class="stat-value"><?php echo number_format($todaySales); ?>원</div>
          <div class="stat-sub"><?php echo date('n월 j일 (D)'); ?> 기준</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📆 이번달 매출</div>
          <div class="stat-value"><?php echo number_format($monthSales); ?>원</div>
          <div class="stat-sub <?php echo $monthSales >= $lastMonthSales ? 'up' : ''; ?>">
            <?php echo $monthSales >= $lastMonthSales ? '▲' : '▼'; ?>
            지난달 <?php echo number_format($lastMonthSales); ?>원
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">👤 이번달 신규 고객</div>
          <div class="stat-value"><?php echo $newCustomersThisMonth; ?></div>
          <div class="stat-sub <?php echo $newCustomersThisMonth >= $newCustomersLastMonth ? 'up' : ''; ?>">
            <?php echo $newCustomersThisMonth >= $newCustomersLastMonth ? '▲' : '▼'; ?>
            지난달 <?php echo $newCustomersLastMonth; ?>명 신규
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📄 이번달 동의서</div>
          <div class="stat-value"><?php echo $consentThisMonth; ?></div>
          <div class="stat-sub">
            지난달 <?php echo $consentLastMonth; ?>건
            · <?php echo $consentThisMonth === $consentLastMonth ? '변동 없음' : ($consentThisMonth > $consentLastMonth ? '증가' : '감소'); ?>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📚 누적 동의서</div>
          <div class="stat-value"><?php echo $consentTotal; ?></div>
          <div class="stat-sub">고객 <?php echo $totalCustomers; ?>명 보유 중</div>
        </div>
      </div>

      <div class="chart-card" style="margin-top:20px;">
        <h2>최근 30일 매출 추이</h2>
        <div class="chart-summary">총 <?php echo number_format($salesChartTotal); ?>원 · 일평균 <?php echo number_format($salesChartAvg); ?>원</div>
        <div class="chart-svg-wrap">
          <?php
            $barWidth = 18; $gap = 6; $chartHeight = 120;
            $svgWidth = count($salesChartDays) * ($barWidth + $gap);
          ?>
          <svg width="<?php echo $svgWidth; ?>" height="<?php echo $chartHeight + 24; ?>" viewBox="0 0 <?php echo $svgWidth; ?> <?php echo $chartHeight + 24; ?>">
            <?php $x = 0; foreach ($salesChartDays as $date => $amt): ?>
              <?php
                $barH = $amt > 0 ? max(2, round(($amt / $salesChartMax) * $chartHeight)) : 1;
                $y = $chartHeight - $barH;
                $label = date('n/j', strtotime($date));
              ?>
              <rect class="chart-bar" x="<?php echo $x; ?>" y="<?php echo $y; ?>"
                    width="<?php echo $barWidth; ?>" height="<?php echo $barH; ?>" rx="2">
                <title><?php echo $label; ?> · 매출 <?php echo number_format($amt); ?>원</title>
              </rect>
              <?php if ($x === 0 || (($x / ($barWidth + $gap)) % 5 == 0)): ?>
                <text class="chart-axis-label" x="<?php echo $x; ?>" y="<?php echo $chartHeight + 16; ?>"><?php echo $label; ?></text>
              <?php endif; ?>
              <?php $x += $barWidth + $gap; ?>
            <?php endforeach; ?>
          </svg>
        </div>
      </div>

      <div class="chart-card" style="margin-top:20px;">
        <h2>최근 30일 동의서 작성 추이</h2>
        <div class="chart-summary">총 <?php echo $chartTotal; ?>건 · 일평균 <?php echo $chartAvg; ?>건</div>
        <div class="chart-svg-wrap">
          <?php
            $barWidth = 18;
            $gap = 6;
            $chartHeight = 120;
            $svgWidth = count($chartDays) * ($barWidth + $gap);
          ?>
          <svg width="<?php echo $svgWidth; ?>" height="<?php echo $chartHeight + 24; ?>" viewBox="0 0 <?php echo $svgWidth; ?> <?php echo $chartHeight + 24; ?>">
            <?php $x = 0; foreach ($chartDays as $date => $cnt): ?>
              <?php
                $barH = $cnt > 0 ? max(2, round(($cnt / $chartMax) * $chartHeight)) : 1;
                $y = $chartHeight - $barH;
                $label = date('n/j', strtotime($date));
              ?>
              <rect class="chart-bar" x="<?php echo $x; ?>" y="<?php echo $y; ?>"
                    width="<?php echo $barWidth; ?>" height="<?php echo $barH; ?>" rx="2">
                <title><?php echo $label; ?> · 동의서 작성 <?php echo $cnt; ?>건</title>
              </rect>
              <?php if ($x === 0 || (($x / ($barWidth + $gap)) % 5 == 0)): ?>
                <text class="chart-axis-label" x="<?php echo $x; ?>" y="<?php echo $chartHeight + 16; ?>"><?php echo $label; ?></text>
              <?php endif; ?>
              <?php $x += $barWidth + $gap; ?>
            <?php endforeach; ?>
          </svg>
        </div>
      </div>

      <div class="quick-actions">
        <a href="sales.php?id=<?php echo urlencode($storeId); ?>" class="quick-action-card" style="text-decoration:none;">
          <div class="quick-action-left">
            <div class="quick-action-icon">💰</div>
            <div>
              <div class="quick-action-title">매출 관리</div>
              <div class="quick-action-desc">매출 등록 · 조회 · 통계</div>
            </div>
          </div>
          <span class="quick-action-arrow">›</span>
        </a>
        <a href="store.php?id=<?php echo urlencode($storeId); ?>" class="quick-action-card" style="text-decoration:none;">
          <div class="quick-action-left">
            <div class="quick-action-icon">👥</div>
            <div>
              <div class="quick-action-title">고객 관리</div>
              <div class="quick-action-desc">고객 정보 조회 · 검색 · 수정</div>
            </div>
          </div>
          <span class="quick-action-arrow">›</span>
        </a>
        <a href="store.php?id=<?php echo urlencode($storeId); ?>#register" class="quick-action-card" style="text-decoration:none;">
          <div class="quick-action-left">
            <div class="quick-action-icon">➕</div>
            <div>
              <div class="quick-action-title">고객 등록</div>
              <div class="quick-action-desc">새 고객 정보 입력</div>
            </div>
          </div>
          <span class="quick-action-arrow">›</span>
        </a>
        <a href="consent.php?id=<?php echo urlencode($storeId); ?>" class="quick-action-card" style="text-decoration:none;">
          <div class="quick-action-left">
            <div class="quick-action-icon">📄</div>
            <div>
              <div class="quick-action-title">동의서 관리</div>
              <div class="quick-action-desc">동의서 템플릿 작성 · 수정</div>
            </div>
          </div>
          <span class="quick-action-arrow">›</span>
        </a>
      </div>

      <div class="recent-grid">
        <div class="recent-card">
          <div class="recent-card-head">
            <h2>최근 등록 고객</h2>
            <a href="store.php?id=<?php echo urlencode($storeId); ?>" class="see-all">전체 보기 →</a>
          </div>
          <?php if (!$recentCustomers): ?>
            <div class="recent-empty">아직 등록된 고객이 없습니다.</div>
          <?php else: ?>
            <?php foreach ($recentCustomers as $c): ?>
              <div class="recent-customer-item">
                <div class="recent-customer-left">
                  <div class="recent-customer-avatar"><?php echo htmlspecialchars(mb_substr($c['name'], 0, 1)); ?></div>
                  <div>
                    <div class="recent-customer-name"><?php echo htmlspecialchars($c['name']); ?></div>
                    <div class="recent-customer-phone"><?php echo htmlspecialchars($c['phone_masked'] ?? ''); ?></div>
                  </div>
                </div>
                <span class="recent-customer-arrow">›</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="recent-card">
          <div class="recent-card-head">
            <h2>최근 동의서 활동</h2>
            <span class="count-badge"><?php echo count($recentConsents); ?>건</span>
          </div>
          <?php if (!$recentConsents): ?>
            <div class="recent-empty">아직 작성된 동의서가 없습니다.</div>
          <?php else: ?>
            <?php foreach ($recentConsents as $cd): ?>
              <a href="consent-document-view.php?id=<?php echo urlencode($storeId); ?>&document_id=<?php echo urlencode($cd['id']); ?>"
                 class="recent-customer-item" style="text-decoration:none;color:inherit;">
                <div class="recent-customer-left">
                  <div class="recent-customer-avatar">📄</div>
                  <div>
                    <div class="recent-customer-name"><?php echo htmlspecialchars($cd['customer_name'] ?? '알 수 없음'); ?></div>
                    <div class="recent-customer-phone"><?php echo htmlspecialchars(substr($cd['created_at'], 0, 16)); ?></div>
                  </div>
                </div>
                <span class="recent-customer-arrow">›</span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
