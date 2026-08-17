<?php
$activePage = 'customers';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';
require_once __DIR__ . '/api/utils/Crypto.php';
require_once __DIR__ . '/api/utils/Mask.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, industry FROM ss_stores WHERE id = ? AND owner_id = ?');
$stmt->execute([$storeId, $user['id']]);
$store = $stmt->fetch();

if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없거나 접근 권한이 없습니다.');
}

$errorMsg = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? 'unknown';

    if ($name === '' || mb_strlen($name) > 100) {
        $fieldErrors['name'] = '이름을 정확히 입력해주세요.';
    }
    if (strlen($phone) < 9 || strlen($phone) > 11) {
        $fieldErrors['phone'] = '전화번호 형식이 올바르지 않습니다.';
    }
    if (!in_array($gender, ['female', 'male', 'unknown'], true)) {
        $gender = 'unknown';
    }

    if (empty($fieldErrors)) {
        try {
            $newId = Uuid::v4();
            $insStmt = $pdo->prepare(
                'INSERT INTO ss_customers (id, store_id, name, phone_encrypted, phone_masked, gender, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $insStmt->execute([$newId, $storeId, $name, Crypto::encrypt($phone), Mask::phone($phone), $gender]);
            header('Location: store.php?id=' . urlencode($storeId) . '&created=1');
            exit;
        } catch (Throwable $e) {
            error_log('[store.php customer create] ' . $e->getMessage());
            $errorMsg = '고객 등록 중 오류가 발생했습니다.';
        }
    }
}

$keyword = trim($_GET['keyword'] ?? '');
$sql = 'SELECT id, name, phone_masked, gender, created_at FROM ss_customers WHERE store_id = ?';
$params = [$storeId];
if ($keyword !== '') {
    $sql .= ' AND name LIKE ?';
    $params[] = '%' . $keyword . '%';
}
$sql .= ' ORDER BY created_at DESC LIMIT 50';
$listStmt = $pdo->prepare($sql);
$listStmt->execute($params);
$customers = $listStmt->fetchAll();

$pageTitle = htmlspecialchars($store['name']) . ' 고객 관리';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
    <?php require __DIR__ . '/includes/store_sidebar.php'; ?>

    <main class="main-content">
        <header class="dashboard-header">
            <span><?php echo htmlspecialchars($user['name'] ?? ''); ?>님</span>
        </header>

        <div class="page-content">
            <div class="page-header">
                <h1 class="page-title"><?php echo htmlspecialchars($store['name']); ?> (<?php echo htmlspecialchars($store['industry']); ?>) 고객 관리</h1>
                <a href="dashboard.php" class="btn-secondary">← 대시보드</a>
            </div>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert-success">고객이 등록되었습니다.</div>
            <?php endif; ?>

            <?php if ($errorMsg !== ''): ?>
                <div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <div class="settings-section">
                <h2>고객 검색</h2>
                <form method="get" class="customer-search-form">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($storeId); ?>">
                    <input type="text" name="keyword" placeholder="고객 이름 검색" value="<?php echo htmlspecialchars($keyword); ?>">
                    <button type="submit" class="btn-secondary">검색</button>
                </form>
            </div>

            <div class="settings-section">
                <h2>고객 목록 (<?php echo count($customers); ?>명)</h2>

                <?php if (empty($customers)): ?>
                    <div class="empty-state" style="padding:40px 20px;">등록된 고객이 없습니다.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>이름</th>
                                <th>전화번호</th>
                                <th>성별</th>
                                <th>등록일</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['phone_masked']); ?></td>
                                    <td><?php echo htmlspecialchars($c['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="settings-section">
                <h2>고객 등록</h2>
                <form method="post" class="customer-register-form">
                    <div class="form-group">
                        <label>이름 *</label>
                        <input type="text" name="name" required>
                        <?php if (!empty($fieldErrors['name'])): ?>
                            <p class="error-text"><?php echo htmlspecialchars($fieldErrors['name']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>전화번호 *</label>
                        <input type="text" name="phone" placeholder="010-0000-0000" required>
                        <?php if (!empty($fieldErrors['phone'])): ?>
                            <p class="error-text"><?php echo htmlspecialchars($fieldErrors['phone']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>성별</label>
                        <select name="gender">
                            <option value="unknown">선택안함</option>
                            <option value="female">여성</option>
                            <option value="male">남성</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary" style="width:auto;padding:12px 28px;">고객 등록</button>
                </form>
            </div>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
