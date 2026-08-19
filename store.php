<?php
$activePage = 'customers';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();
$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$actor = requireStoreAccess($pdo, $storeId);

$stmt = $pdo->prepare('SELECT id, name, industry FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없습니다.');
}

logAccess($pdo, $storeId, $actor, 'view_customer_list');

$keyword = trim($_GET['keyword'] ?? '');
$sql = 'SELECT id, name, phone_masked, gender, created_at FROM ss_customers WHERE store_id = ?';
$params = [$storeId];
if ($keyword !== '') {
    $sql .= ' AND (name LIKE ? OR phone_masked LIKE ?)';
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword;
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';
$listStmt = $pdo->prepare($sql);
$listStmt->execute($params);
$customers = $listStmt->fetchAll();

// ── 각 고객의 서명 완료 동의서 "건수"를 조회 (최근 1건만이 아니라 전체 건수) ──
// 여러 번 서명한 고객도 개수가 그대로 보이도록, 최근 것만 남기고 가리지 않는다.
$docCountByCustomer = [];
if (!empty($customers)) {
    $customerIds = array_column($customers, 'id');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    try {
        $docStmt = $pdo->prepare(
            "SELECT customer_id, COUNT(*) AS cnt
             FROM ss_consent_documents
             WHERE store_id = ? AND customer_id IN ($placeholders)
             GROUP BY customer_id"
        );
        $docStmt->execute(array_merge([$storeId], $customerIds));
        foreach ($docStmt->fetchAll() as $row) {
            $docCountByCustomer[$row['customer_id']] = (int)$row['cnt'];
        }
    } catch (Throwable $e) {
        error_log('[store.php] 서명 건수 조회 실패: ' . $e->getMessage());
    }
}

$actorRole = $actor['role'];
$pageTitle = htmlspecialchars($store['name']) . ' 고객 목록';
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
                <h1 class="page-title">고객 목록</h1>
                <a href="customer-register.php?id=<?php echo urlencode($storeId); ?>" class="btn-primary" style="width:auto;padding:11px 22px;">+ 고객 등록</a>
            </div>

            <?php if (isset($_GET['created'])): ?>
                <div class="alert-success">고객이 등록되었습니다.</div>
            <?php endif; ?>
            <?php if (isset($_GET['signed'])): ?>
                <div class="alert-success">동의서 서명이 완료되었습니다.</div>
            <?php endif; ?>

            <form method="get" class="customer-search-bar">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($storeId); ?>">
                <span>🔍</span>
                <input type="text" name="keyword" placeholder="이름 또는 전화번호 뒤 4자리 검색" value="<?php echo htmlspecialchars($keyword); ?>">
            </form>

            <?php if (empty($customers)): ?>
                <div class="empty-state" style="padding:60px 20px;text-align:center;color:var(--text-sub);">등록된 고객이 없습니다.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr><th>이름</th><th>전화번호</th><th>등록일</th><th>관리</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <?php $signedCount = $docCountByCustomer[$c['id']] ?? 0; ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                    <?php if ($c['gender'] === 'male'): ?>남<?php elseif ($c['gender'] === 'female'): ?>여<?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($c['phone_masked']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y. n. j.', strtotime($c['created_at']))); ?></td>
                                <td>
                                    <?php if ($signedCount > 0): ?>
                                        <a href="consent-history.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini">✔ 서명 내역 보기 (<?php echo $signedCount; ?>건)</a>
                                        <a href="consent-select.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini" style="margin-left:6px;">+ 새 동의서</a>
                                    <?php else: ?>
                                        <a href="consent-select.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini">동의서 작성</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="table-total-count">총 <?php echo count($customers); ?>명</div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
