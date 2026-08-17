<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';
require_once __DIR__ . '/api/utils/Validator.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $businessNumber = preg_replace('/\D/', '', $_POST['business_number'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $managerName = trim($_POST['manager_name'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if (!$name) {
        $storeError = '매장명을 입력해주세요.';
    } elseif (!Validator::isValidIndustry($industry)) {
        $storeError = '업종을 선택해주세요.';
    } elseif (!Validator::isValidBusinessNumber($businessNumber)) {
        $storeError = '사업자등록번호 10자리를 정확히 입력해주세요.';
    } elseif (strlen($adminPassword) < 4) {
        $storeError = '매장 관리 비밀번호는 4자 이상이어야 합니다.';
    } else {
        try {
            $id = Uuid::v4();
            $stmt = $pdo->prepare(
                'INSERT INTO ss_stores
                 (id, owner_id, name, industry, business_number, phone, owner_name, manager_name,
                  admin_password_hash, plan, plan_status, trial_ends_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "free", "trial", DATE_ADD(NOW(), INTERVAL 14 DAY), NOW())'
            );
            $stmt->execute([
                $id, $user['id'], $name, $industry, $businessNumber,
                $phone, $ownerName, $managerName,
                password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            ]);
            header('Location: store-dashboard.php?id=' . urlencode($id));
            exit;
        } catch (Throwable $e) {
            error_log('[store create] ' . $e->getMessage());
            $storeError = '매장 등록 중 오류가 발생했습니다.';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT id, name, industry, business_number, phone, owner_name, manager_name, plan_status, created_at
     FROM ss_stores WHERE owner_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$user['id']]);
$stores = $stmt->fetchAll();

$pageTitle = '매장 목록';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-logo"><span class="logo-text">SalonForm</span></div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="nav-item active">🏠 매장 목록</a>
    </nav>
    <div class="sidebar-footer">
      <form method="POST" action="logout.php">
        <button type="submit" class="logout-btn">로그아웃</button>
      </form>
    </div>
  </aside>

  <main class="main-content">
    <header class="dashboard-header">
      <span><?php echo htmlspecialchars($user['name'] ?? ''); ?>님</span>
    </header>

    <div class="page-content">
      <div class="page-header">
        <h1 class="page-title">매장 목록</h1>
        <button class="btn-add" onclick="document.getElementById('addStoreModal').style.display='flex'">+ 새 매장 등록</button>
      </div>

      <?php if (!$stores): ?>
        <div class="empty-state">
          <p class="empty-text">등록된 매장이 없습니다</p>
          <p class="empty-desc">새 매장을 등록하고 고객 관리를 시작하세요</p>
          <button class="btn-primary" style="width:auto;padding:12px 28px;"
            onclick="document.getElementById('addStoreModal').style.display='flex'">매장 등록하기</button>
        </div>
      <?php else: ?>
        <?php foreach ($stores as $s): ?>
          <a href="store-dashboard.php?id=<?php echo urlencode($s['id']); ?>" style="display:block;">
            <div class="store-card">
              <div class="store-card-top">
                <div class="store-card-name">🏠 <?php echo htmlspecialchars($s['name']); ?></div>
                <span class="status-badge <?php echo htmlspecialchars($s['plan_status']); ?>">
                  <?php echo $s['plan_status'] === 'trial' ? '체험중' : ($s['plan_status'] === 'active' ? '운영 중' : '중지됨'); ?>
                </span>
              </div>
              <div class="store-card-meta">
                <span><b>전화</b><?php echo htmlspecialchars($s['phone'] ?: '-'); ?></span>
                <span><b>대표</b><?php echo htmlspecialchars($s['owner_name'] ?: '-'); ?></span>
                <span><b>사업자번호</b><?php echo htmlspecialchars($s['business_number']); ?></span>
                <span><b>연락처 담당자</b><?php echo htmlspecialchars($s['manager_name'] ?: '-'); ?></span>
                <span><b>등록일</b><?php echo htmlspecialchars(substr($s['created_at'], 0, 10)); ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</div>

<div class="modal-overlay" id="addStoreModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title">새 매장 등록</h2>
    <?php if ($storeError): ?><div class="alert-error"><?php echo htmlspecialchars($storeError); ?></div><?php endif; ?>
    <form method="POST" action="dashboard.php">
      <div class="form-group"><label>매장명</label><input type="text" name="name" required></div>
      <div class="form-group">
        <label>업종</label>
        <select name="industry" required>
          <option value="hair">헤어</option>
          <option value="skin">피부</option>
          <option value="nail">네일</option>
          <option value="waxing">왁싱</option>
          <option value="lash">속눈썹</option>
          <option value="tattoo">타투</option>
        </select>
      </div>
      <div class="form-group"><label>사업자등록번호 (10자리)</label><input type="text" name="business_number" maxlength="10" required></div>
      <div class="form-group"><label>매장 전화번호</label><input type="tel" name="phone"></div>
      <div class="form-group"><label>대표자명</label><input type="text" name="owner_name"></div>
      <div class="form-group"><label>연락처 담당자</label><input type="text" name="manager_name"></div>
      <div class="form-group"><label>매장 관리 비밀번호</label><input type="password" name="admin_password" required minlength="4"></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('addStoreModal').style.display='none'">취소</button>
        <button type="submit" class="btn-primary">등록</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
