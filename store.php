<?php
$activePage = 'customers';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/includes/plan_guard.php';

$pdo = getDbConnection();
$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$actor = requireStoreAccess($pdo, $storeId);
$actorRole = $actor['role'];
$canDelete = in_array($actorRole, ['owner', 'admin'], true);

$stmt = $pdo->prepare('SELECT id, name, industry, plan_status, trial_ends_at FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    http_response_code(404);
    die('매장을 찾을 수 없습니다.');
}

enforcePlanAccess($pdo, $store);

logAccess($pdo, $storeId, $actor, 'view_customer_list');

$keyword = trim($_GET['keyword'] ?? '');
$sql = 'SELECT id, name, phone_masked, gender, memo, created_at FROM ss_customers WHERE store_id = ? AND deleted_at IS NULL';
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

$docCountByCustomer = [];
if (!empty($customers)) {
    $customerIds = array_column($customers, 'id');
    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    try {
        $docStmt = $pdo->prepare(
            "SELECT customer_id, COUNT(*) AS cnt
             FROM ss_consent_documents
             WHERE store_id = ? AND deleted_at IS NULL AND customer_id IN ($placeholders)
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

function genderLabel(?string $g): string {
    if ($g === 'male') return '남';
    if ($g === 'female') return '여';
    return '-';
}

function memoPreview(?string $memoHtml): string {
    if ($memoHtml === null || trim($memoHtml) === '') return '-';
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($memoHtml)));
    if ($plain === '') return '-';
    return mb_strlen($plain) > 20 ? mb_substr($plain, 0, 20) . '…' : $plain;
}

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
            <?php if (isset($_GET['updated'])): ?>
                <div class="alert-success">고객 정보가 수정되었습니다.</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert-success">고객이 삭제되었습니다.</div>
            <?php endif; ?>
            <?php if (isset($_GET['signed'])): ?>
                <div class="alert-success">동의서 서명이 완료되었습니다.</div>
            <?php endif; ?>
            <?php if (isset($_GET['delete_error'])): ?>
                <div class="alert-error">삭제 권한이 없거나 처리 중 오류가 발생했습니다.</div>
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
                        <tr>
                            <th>이름</th>
                            <th>성별</th>
                            <th>전화번호</th>
                            <th>메모</th>
                            <th>등록일</th>
                            <th>동의서</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <?php $signedCount = $docCountByCustomer[$c['id']] ?? 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['name']); ?></td>
                                <td><?php echo genderLabel($c['gender']); ?></td>
                                <td><?php echo htmlspecialchars($c['phone_masked']); ?></td>
                                <td style="max-width:180px;color:var(--text-sub);font-size:13px;"><?php echo htmlspecialchars(memoPreview($c['memo'])); ?></td>
                                <td><?php echo htmlspecialchars(date('Y. n. j.', strtotime($c['created_at']))); ?></td>
                                <td>
                                    <?php if ($signedCount > 0): ?>
                                        <a href="consent-history.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini">✔ 내역 (<?php echo $signedCount; ?>건)</a>
                                        <a href="consent-select.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini" style="margin-left:4px;">+ 새 동의서</a>
                                    <?php else: ?>
                                        <a href="consent-select.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini">동의서 작성</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="customer-edit.php?id=<?php echo urlencode($storeId); ?>&customer_id=<?php echo urlencode($c['id']); ?>" class="btn-mini">수정</a>
                                    <?php if ($canDelete): ?>
                                        <button type="button" class="btn-mini" style="color:var(--danger,#dc3545);margin-left:4px;"
                                            onclick="openDeleteModal('<?php echo htmlspecialchars($c['id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>', <?php echo $signedCount; ?>)">삭제</button>
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

<?php if ($canDelete): ?>
<div class="modal-overlay" id="deleteCustomerModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title" style="color:var(--danger,#dc3545);">고객을 삭제하시겠습니까?</h2>
    <p id="deleteCustomerDesc" style="font-size:13px;color:var(--text-sub);margin-bottom:16px;"></p>
    <form method="post" action="customer-delete.php">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($storeId); ?>">
      <input type="hidden" name="customer_id" id="deleteCustomerId" value="">
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('deleteCustomerModal').style.display='none'">취소</button>
        <button type="submit" class="btn-danger-outline" style="flex:1;">삭제 확정</button>
      </div>
    </form>
  </div>
</div>
<script>
function openDeleteModal(customerId, name, signedCount) {
    document.getElementById('deleteCustomerId').value = customerId;
    var desc = name + '님의 고객 정보가 영구적으로 삭제됩니다.';
    if (signedCount > 0) {
        desc += ' 서명 완료된 동의서 ' + signedCount + '건도 함께 삭제되며, 이 작업은 되돌릴 수 없습니다.';
    } else {
        desc += ' 이 작업은 되돌릴 수 없습니다.';
    }
    document.getElementById('deleteCustomerDesc').textContent = desc;
    document.getElementById('deleteCustomerModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
