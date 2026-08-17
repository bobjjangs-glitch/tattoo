<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';
require_once __DIR__ . '/api/utils/Validator.php';

$user = requireLogin();
$pdo = getDbConnection();

$errorMsg = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $businessNumber = preg_replace('/\D/', '', $_POST['business_number'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';

    if (!$name || mb_strlen($name) > 50) {
        $fieldErrors['name'] = '매장명을 1~50자로 입력해주세요.';
    }
    if (!Validator::isValidIndustry($industry)) {
        $fieldErrors['industry'] = '업종을 선택해주세요.';
    }
    if (!Validator::isValidBusinessNumber($businessNumber)) {
        $fieldErrors['business_number'] = '사업자등록번호 형식이 올바르지 않습니다.';
    }
    if (strlen($adminPassword) < 4) {
        $fieldErrors['admin_password'] = '관리자 비밀번호는 4자리 이상이어야 합니다.';
    }

    if (!$fieldErrors) {
        try {
            $id = Uuid::v4();
            $stmt = $pdo->prepare(
                'INSERT INTO ss_stores (id, owner_id, name, industry, business_number, admin_password_hash, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $id, $user['id'], $name, $industry, $businessNumber,
                password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            ]);
            header('Location: dashboard.php?created=1');
            exit;
        } catch (Throwable $e) {
            error_log('[store create] ' . $e->getMessage());
            $errorMsg = '매장 등록 중 오류가 발생했습니다.';
        }
    }
}

$stmt = $pdo->prepare('SELECT id, name, industry, business_number, created_at FROM ss_stores WHERE owner_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$stores = $stmt->fetchAll();

$pageTitle = '대시보드 | SalonForm';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-page">
  <header class="dashboard-header">
    <h1>안녕하세요, <?= htmlspecialchars($user['name']) ?>님</h1>
    <a href="logout.php" class="btn-secondary">로그아웃</a>
  </header>

  <section class="store-list">
    <h2>내 매장 목록</h2>
    <?php if (!$stores): ?>
      <p>등록된 매장이 없습니다. 아래에서 새 매장을 등록해주세요.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($stores as $store): ?>
          <li>
            <a href="store.php?id=<?= urlencode($store['id']) ?>">
              <?= htmlspecialchars($store['name']) ?> (<?= htmlspecialchars($store['industry']) ?>)
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="store-register">
    <h2>새 매장 등록</h2>
    <form method="post">
      <div class="form-group">
        <label>매장명</label>
        <input type="text" name="name" required>
        <?php if (!empty($fieldErrors['name'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['name']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>업종</label>
        <select name="industry" required>
          <option value="">선택하세요</option>
          <option value="hair">헤어</option>
          <option value="skin">피부</option>
          <option value="nail">네일</option>
          <option value="waxing">왁싱</option>
          <option value="lash">속눈썹</option>
          <option value="tattoo">타투</option>
        </select>
        <?php if (!empty($fieldErrors['industry'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['industry']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>사업자등록번호 (숫자 10자리)</label>
        <input type="text" name="business_number" maxlength="12" required>
        <?php if (!empty($fieldErrors['business_number'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['business_number']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>매장 관리자 비밀번호 (4자리 이상)</label>
        <input type="password" name="admin_password" required>
        <?php if (!empty($fieldErrors['admin_password'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['admin_password']) ?></p><?php endif; ?>
      </div>
      <?php if ($errorMsg): ?><p class="error-text"><?= htmlspecialchars($errorMsg) ?></p><?php endif; ?>
      <button type="submit" class="btn-primary">매장 등록</button>
    </form>
  </section>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
