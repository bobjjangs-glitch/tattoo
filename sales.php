<?php
$activePage = 'sales';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';

$pdo = getDbConnection();
$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$actor = requireStoreAccess($pdo, $storeId);
$actorRole = $actor['role'];
$canDelete = in_array($actorRole, ['owner', 'admin'], true);

$stmt = $pdo->prepare('SELECT id, name FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

$errorMsg = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $amountRaw = preg_replace('/\D/', '', $_POST['amount'] ?? '');
    $saleDate = $_POST['sale_date'] ?? date('Y-m-d');
    $customerId = trim($_POST['customer_id'] ?? '');
    $memo = trim($_POST['memo'] ?? '');

    if ($amountRaw === '' || (int)$amountRaw <= 0) {
        $fieldErrors['amount'] = '매출 금액을 정확히 입력해주세요.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $saleDate)) {
        $fieldErrors['sale_date'] = '날짜 형식이 올바르지 않습니다.';
    }
    if ($customerId !== '') {
        $chk = $pdo->prepare('SELECT id FROM ss_customers WHERE id = ? AND store_id = ? AND deleted_at IS NULL');
        $chk->execute([$customerId, $storeId]);
        if (!$chk->fetch()) {
            $fieldErrors['customer_id'] = '고객 정보를 찾을 수 없습니다.';
        }
    }
    if (mb_strlen($memo) > 255) {
        $fieldErrors['memo'] = '메모는 255자 이내로 입력해주세요.';
    }

    if (!$fieldErrors) {
        try {
            $id = Uuid::v4();
            $stmt = $pdo->prepare(
                'INSERT INTO ss_sales (id, store_id, customer_id, staff_id, amount, memo, sale_date, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $id, $storeId,
                $customerId !== '' ? $customerId : null,
                $actor['actor_type'] === 'staff' ? $actor['actor_id'] : null,
                (int)$amountRaw, $memo !== '' ? $memo : null, $saleDate,
            ]);
            logAccess($pdo, $storeId, $actor, 'register_sale', 'sale', $id, number_format((int)$amountRaw) . '원');
            header('Location: sales.php?id=' . urlencode($storeId) . '&created=1');
            exit;
        } catch (Throwable $e) {
            error_log('[sales register] ' . $e->getMessage());
            $errorMsg = '매출 등록 중 오류가 발생했습니다.';
        }
    }
}

function safeSum(PDO $pdo, string $sql, array $params): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
function safeFetchAllS(PDO $pdo, string $sql, array $params): array {
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

$todaySales = safeSum($pdo, 'SELECT COALESCE(SUM(amount),0) FROM ss_sales WHERE store_id = ? AND sale_date = ? AND deleted_at IS NULL', [$storeId, $today]);
$monthSales = safeSum($pdo, 'SELECT COALESCE(SUM(amount),0) FROM ss_sales WHERE store_id = ? AND sale_date >= ? AND deleted_at IS NULL', [$storeId, $monthStart]);
$lastMonthSales = safeSum($pdo, 'SELECT COALESCE(SUM(amount),0) FROM ss_sales WHERE store_id = ? AND sale_date BETWEEN ? AND ? AND deleted_at IS NULL', [$storeId, $lastMonthStart, $lastMonthEnd]);

$chartDays = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $chartDays[$d] = 0;
}
$rawDaily = safeFetchAllS($pdo,
    'SELECT sale_date, COALESCE(SUM(amount),0) AS total
     FROM ss_sales WHERE store_id = ? AND sale_date >= ? AND deleted_at IS NULL
     GROUP BY sale_date',
    [$storeId, date('Y-m-d', strtotime('-29 day'))]);
foreach ($rawDaily as $row) {
    if (isset($chartDays[$row['sale_date']])) {
        $chartDays[$row['sale_date']] = (int)$row['total'];
    }
}
$chartTotal = array_sum($chartDays);
$chartMax = max(1, max($chartDays));

$customerOptions = safeFetchAllS($pdo,
    'SELECT id, name, phone_masked FROM ss_customers WHERE store_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 200',
    [$storeId]);

// 매출 내역 목록 (최근 100건, 삭제된 매출/고객 제외)
$sales = safeFetchAllS($pdo,
    "SELECT sl.id, sl.amount, sl.memo, sl.sale_date, sl.created_at,
            c.name AS customer_name,
            st.name AS staff_name
     FROM ss_sales sl
     LEFT JOIN ss_customers c
        ON c.id COLLATE utf8mb4_unicode_ci = sl.customer_id COLLATE utf8mb4_unicode_ci AND c.deleted_at IS NULL
     LEFT JOIN ss_store_staff st
        ON st.id COLLATE utf8mb4_unicode_ci = sl.staff_id COLLATE utf8mb4_unicode_ci
     WHERE sl.store_id = ? AND sl.deleted_at IS NULL
     ORDER BY sl.sale_date DESC, sl.created_at DESC
     LIMIT 100",
    [$storeId]);

$pageTitle = htmlspecialchars($store['name']) . ' 매출 관리';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header">
      <span><?php echo htmlspecialchars($actor['actor_name']); ?>님</span>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1 class="page-title">매출 관리</h1>
      </div>

      <?php if (isset($_GET['created'])): ?>
        <div class="alert-success">매출이 등록되었습니다.</div>
      <?php endif; ?>
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert-success">매출 내역이 삭제되었습니다.</div>
      <?php endif; ?>
      <?php if (isset($_GET['delete_error'])): ?>
        <div class="alert-error">삭제 권한이 없거나 처리 중 오류가 발생했습니다.</div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
        <div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
      <?php endif; ?>

      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">💰 오늘 매출</div>
          <div class="stat-value"><?php echo number_format($todaySales); ?>원</div>
          <div class="stat-sub"><?php echo date('n월 j일 (D)'); ?> 기준</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📆 이번달 매출</div>
          <div class="stat-value"><?php echo number_format($monthSales); ?>원</div>
          <div class="stat-sub">
            <?php echo $monthSales >= $lastMonthSales ? '▲' : '▼'; ?>
            지난달 <?php echo number_format($lastMonthSales); ?>원
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-label">📊 최근 30일 총매출</div>
          <div class="stat-value"><?php echo number_format($chartTotal); ?>원</div>
          <div class="stat-sub">일평균 <?php echo number_format(round($chartTotal / 30)); ?>원</div>
        </div>
      </div>

      <div class="chart-card" style="margin-top:20px;">
        <h2>최근 30일 매출 추이</h2>
        <div class="chart-summary">총 <?php echo number_format($chartTotal); ?>원</div>
        <div class="chart-svg-wrap">
          <?php
            $barWidth = 18; $gap = 6; $chartHeight = 120;
            $svgWidth = count($chartDays) * ($barWidth + $gap);
          ?>
          <svg width="<?php echo $svgWidth; ?>" height="<?php echo $chartHeight + 24; ?>" viewBox="0 0 <?php echo $svgWidth; ?> <?php echo $chartHeight + 24; ?>">
            <?php $x = 0; foreach ($chartDays as $date => $amt): ?>
              <?php
                $barH = $amt > 0 ? max(2, round(($amt / $chartMax) * $chartHeight)) : 1;
                $y = $chartHeight - $barH;
                $label = date('n/j', strtotime($date));
              ?>
              <rect class="chart-bar" x="<?php echo $x; ?>" y="<?php echo $y; ?>"
                    width="<?php echo $barWidth; ?>" height="<?php echo $barH; ?>" rx="2">
                <title><?php echo $label; ?> · <?php echo number_format($amt); ?>원</title>
              </rect>
              <?php if ($x === 0 || (($x / ($barWidth + $gap)) % 5 == 0)): ?>
                <text class="chart-axis-label" x="<?php echo $x; ?>" y="<?php echo $chartHeight + 16; ?>"><?php echo $label; ?></text>
              <?php endif; ?>
              <?php $x += $barWidth + $gap; ?>
            <?php endforeach; ?>
          </svg>
        </div>
      </div>

      <div class="form-page-card" style="margin-top:20px;">
        <h2 style="margin-bottom:16px;">매출 등록</h2>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="form-group">
            <label>매출 금액(원) *</label>
            <input type="text" name="amount" placeholder="예: 150000" required
                   value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
            <?php if (!empty($fieldErrors['amount'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['amount']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>매출 일자 *</label>
            <div class="tdp-wrap" id="saleDateWrap">
              <div class="tdp-display" id="saleDateDisplay" tabindex="0">
                <span class="tdp-value-text" id="saleDateValueText"></span>
                <span class="tdp-icon">📅</span>
              </div>
              <input type="hidden" name="sale_date" id="saleDateHidden"
                     value="<?php echo htmlspecialchars($_POST['sale_date'] ?? date('Y-m-d')); ?>">

              <div class="tdp-popup" id="saleDatePopup">
                <div class="tdp-header">
                  <div class="tdp-nav-group">
                    <button type="button" class="tdp-nav-btn" id="tdpPrevBtn">‹</button>
                  </div>
                  <div class="tdp-month-label" id="tdpMonthLabel"></div>
                  <div class="tdp-nav-group">
                    <button type="button" class="tdp-nav-btn" id="tdpNextBtn">›</button>
                  </div>
                </div>
                <div class="tdp-body">
                  <div class="tdp-weekdays">
                    <span>일</span><span>월</span><span>화</span><span>수</span><span>목</span><span>금</span><span>토</span>
                  </div>
                  <div class="tdp-grid" id="tdpGrid"></div>
                </div>
                <div class="tdp-footer">
                  <button type="button" class="tdp-today-btn" id="tdpTodayBtn">오늘</button>
                  <button type="button" class="tdp-close-btn" id="tdpCloseBtn">닫기</button>
                </div>
              </div>
            </div>
            <?php if (!empty($fieldErrors['sale_date'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['sale_date']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>고객 (선택)</label>
            <select name="customer_id">
              <option value="">고객 미지정</option>
              <?php foreach ($customerOptions as $co): ?>
                <option value="<?php echo htmlspecialchars($co['id']); ?>" <?php echo (($_POST['customer_id'] ?? '') === $co['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($co['name']) . ' (' . htmlspecialchars($co['phone_masked'] ?? '') . ')'; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($fieldErrors['customer_id'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['customer_id']); ?></p><?php endif; ?>
          </div>
          <div class="form-group">
            <label>메모 (선택)</label>
            <input type="text" name="memo" maxlength="255" placeholder="예: 팔뚝 타투 1건" value="<?php echo htmlspecialchars($_POST['memo'] ?? ''); ?>">
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary">매출 등록</button>
          </div>
        </form>
      </div>

      <h2 style="margin:28px 0 12px;">매출 내역 (최근 100건)</h2>
      <?php if (empty($sales)): ?>
        <div class="empty-state" style="padding:60px 20px;text-align:center;color:var(--text-sub);">등록된 매출 내역이 없습니다.</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr><th>매출일</th><th>금액</th><th>고객</th><th>메모</th><th>등록자</th><th>관리</th></tr>
          </thead>
          <tbody>
            <?php foreach ($sales as $s): ?>
              <tr>
                <td><?php echo htmlspecialchars(date('Y.m.d', strtotime($s['sale_date']))); ?></td>
                <td><?php echo number_format((int)$s['amount']); ?>원</td>
                <td><?php echo htmlspecialchars($s['customer_name'] ?? '-'); ?></td>
                <td style="max-width:180px;color:var(--text-sub);font-size:13px;"><?php echo htmlspecialchars($s['memo'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($s['staff_name'] ?? '대표'); ?></td>
                <td>
                  <?php if ($canDelete): ?>
                    <button type="button" class="btn-mini" style="color:var(--danger,#dc3545);"
                      onclick="openDeleteSaleModal('<?php echo htmlspecialchars($s['id'], ENT_QUOTES); ?>', <?php echo (int)$s['amount']; ?>)">삭제</button>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
</div>

<?php if ($canDelete): ?>
<div class="modal-overlay" id="deleteSaleModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title" style="color:var(--danger,#dc3545);">매출 내역을 삭제하시겠습니까?</h2>
    <p id="deleteSaleDesc" style="font-size:13px;color:var(--text-sub);margin-bottom:16px;"></p>
    <form method="post" action="sales-delete.php">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($storeId); ?>">
      <input type="hidden" name="sale_id" id="deleteSaleId" value="">
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('deleteSaleModal').style.display='none'">취소</button>
        <button type="submit" class="btn-danger-outline" style="flex:1;">삭제 확정</button>
      </div>
    </form>
  </div>
</div>
<script>
function openDeleteSaleModal(saleId, amount) {
    document.getElementById('deleteSaleId').value = saleId;
    document.getElementById('deleteSaleDesc').textContent = amount.toLocaleString() + '원 매출 내역이 영구적으로 삭제됩니다. 이 작업은 되돌릴 수 없습니다.';
    document.getElementById('deleteSaleModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<script>
(function () {
  var hiddenInput = document.getElementById('saleDateHidden');
  var display = document.getElementById('saleDateDisplay');
  var valueText = document.getElementById('saleDateValueText');
  var popup = document.getElementById('saleDatePopup');
  var monthLabel = document.getElementById('tdpMonthLabel');
  var grid = document.getElementById('tdpGrid');
  var prevBtn = document.getElementById('tdpPrevBtn');
  var nextBtn = document.getElementById('tdpNextBtn');
  var todayBtn = document.getElementById('tdpTodayBtn');
  var closeBtn = document.getElementById('tdpCloseBtn');

  function parseYmd(str) {
    var p = (str || '').split('-');
    if (p.length !== 3) return new Date();
    return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
  }
  function toYmd(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var day = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }
  function toDisplayText(d) {
    var week = ['일', '월', '화', '수', '목', '금', '토'];
    return d.getFullYear() + '년 ' + (d.getMonth() + 1) + '월 ' + d.getDate() + '일 (' + week[d.getDay()] + ')';
  }

  var selectedDate = parseYmd(hiddenInput.value || toYmd(new Date()));
  var viewYear = selectedDate.getFullYear();
  var viewMonth = selectedDate.getMonth();

  function render() {
    monthLabel.textContent = viewYear + '년 ' + (viewMonth + 1) + '월';
    grid.innerHTML = '';

    var firstDay = new Date(viewYear, viewMonth, 1);
    var startWeekday = firstDay.getDay();
    var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
    var daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

    var today = new Date();
    var todayStr = toYmd(today);
    var selectedStr = toYmd(selectedDate);

    var cells = [];
    for (var i = startWeekday - 1; i >= 0; i--) {
      cells.push({ day: daysInPrevMonth - i, other: true, y: viewYear, m: viewMonth - 1 });
    }
    for (var d = 1; d <= daysInMonth; d++) {
      cells.push({ day: d, other: false, y: viewYear, m: viewMonth });
    }
    var remain = 42 - cells.length;
    for (var n = 1; n <= remain; n++) {
      cells.push({ day: n, other: true, y: viewYear, m: viewMonth + 1 });
    }

    cells.forEach(function (cell) {
      var realDate = new Date(cell.y, cell.m, cell.day);
      var ymd = toYmd(realDate);
      var btn = document.createElement('div');
      btn.className = 'tdp-day';
      btn.textContent = cell.day;
      if (cell.other) btn.classList.add('other-month');
      var weekday = realDate.getDay();
      if (weekday === 0) btn.classList.add('is-sun');
      if (weekday === 6) btn.classList.add('is-sat');
      if (ymd === todayStr) btn.classList.add('is-today');
      if (ymd === selectedStr) btn.classList.add('is-selected');

      btn.addEventListener('click', function () {
        selectedDate = realDate;
        viewYear = realDate.getFullYear();
        viewMonth = realDate.getMonth();
        commitAndClose();
      });
      grid.appendChild(btn);
    });
  }

  function commitAndClose() {
    hiddenInput.value = toYmd(selectedDate);
    valueText.textContent = toDisplayText(selectedDate);
    valueText.classList.remove('placeholder');
    render();
    closePopup();
  }

  function openPopup() {
    popup.classList.add('is-open');
    display.classList.add('is-open');
  }
  function closePopup() {
    popup.classList.remove('is-open');
    display.classList.remove('is-open');
  }

  display.addEventListener('click', function (e) {
    e.stopPropagation();
    popup.classList.contains('is-open') ? closePopup() : openPopup();
  });
  closeBtn.addEventListener('click', function (e) { e.stopPropagation(); closePopup(); });
  prevBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    viewMonth -= 1;
    if (viewMonth < 0) { viewMonth = 11; viewYear -= 1; }
    render();
  });
  nextBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    viewMonth += 1;
    if (viewMonth > 11) { viewMonth = 0; viewYear += 1; }
    render();
  });
  todayBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    var t = new Date();
    selectedDate = t;
    viewYear = t.getFullYear();
    viewMonth = t.getMonth();
    commitAndClose();
  });
  document.addEventListener('click', function (e) {
    if (!document.getElementById('saleDateWrap').contains(e.target)) closePopup();
  });

  valueText.textContent = toDisplayText(selectedDate);
  render();
})();
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
