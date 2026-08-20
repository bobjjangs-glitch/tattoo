<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

$actionMsg = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $storeId = $_POST['store_id'] ?? '';

    if ($action === 'suspend' && $storeId !== '') {
        try {
            $pdo->prepare("UPDATE ss_stores SET plan_status = 'suspended' WHERE id = ?")->execute([$storeId]);
            $actionMsg = '매장 이용이 중지되었습니다.';
        } catch (Throwable $e) { $actionError = '처리 중 오류가 발생했습니다.'; }
    } elseif ($action === 'activate' && $storeId !== '') {
        try {
            $pdo->prepare("UPDATE ss_stores SET plan_status = 'active' WHERE id = ?")->execute([$storeId]);
            $actionMsg = '매장 이용이 재개되었습니다.';
        } catch (Throwable $e) { $actionError = '처리 중 오류가 발생했습니다.'; }
    } elseif ($action === 'delete' && $storeId !== '') {
        $confirmText = trim($_POST['confirm_text'] ?? '');
        if ($confirmText !== 'DELETE') {
            $actionError = '삭제 확인 문구가 일치하지 않습니다.';
        } else {
            try {
                $pdo->prepare('DELETE FROM ss_stores WHERE id = ?')->execute([$storeId]);
                $actionMsg = '매장이 완전히 삭제되었습니다.';
            } catch (Throwable $e) { $actionError = '삭제 중 오류가 발생했습니다.'; }
        }
    }
}

$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT s.id, s.name, s.owner_name, s.phone, s.business_number, s.plan_status,
               s.trial_ends_at, s.created_at, u.email AS owner_email
        FROM ss_stores s LEFT JOIN ss_users u ON u.id = s.owner_id WHERE 1=1";
$params = [];
if ($keyword !== '') {
    $sql .= ' AND (s.name LIKE ? OR s.owner_name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$keyword%"; $params[] = "%$keyword%"; $params[] = "%$keyword%";
}
if (in_array($statusFilter, ['trial', 'active', 'suspended'], true)) {
    $sql .= ' AND s.plan_status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY s.created_at DESC LIMIT 200';

$stores = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stores = $stmt->fetchAll();
} catch (Throwable $e) {
    $actionError = '매장 목록을 불러오지 못했습니다.';
}

$activePage = 'stores';
$pageTitle = '매장 관리';
require_once __DIR__ . '/includes/admin_layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($admin['name']); ?>님 (최고관리자)</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">매장 관리</h1></div>

      <?php if ($actionMsg): ?><div class="alert-success"><?php echo htmlspecialchars($actionMsg); ?></div><?php endif; ?>
      <?php if ($actionError): ?><div class="alert-error"><?php echo htmlspecialchars($actionError); ?></div><?php endif; ?>

      <form method="get" style="margin-bottom:16px;display:flex;gap:8px;">
        <input type="text" name="keyword" placeholder="매장명, 대표자, 이메일 검색" value="<?php echo htmlspecialchars($keyword); ?>" style="flex:1;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
        <select name="status" style="border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
          <option value="">전체 상태</option>
          <option value="trial" <?php echo $statusFilter === 'trial' ? 'selected' : ''; ?>>체험중</option>
          <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>운영 중</option>
          <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>중지됨</option>
        </select>
        <button type="submit" class="btn-secondary">검색</button>
      </form>

      <table class="data-table">
        <thead>
          <tr><th>매장명</th><th>대표자</th><th>대표 이메일</th><th>사업자번호</th><th>상태</th><th>가입일</th><th>관리</th></tr>
        </thead>
        <tbody>
          <?php if (!$stores): ?>
            <tr><td colspan="7" class="recent-empty">조건에 맞는 매장이 없습니다.</td></tr>
          <?php endif; ?>
          <?php foreach ($stores as $s): ?>
            <tr>
              <td><?php echo htmlspecialchars($s['name']); ?></td>
              <td><?php echo htmlspecialchars($s['owner_name'] ?: '-'); ?></td>
              <td><?php echo htmlspecialchars($s['owner_email'] ?: '-'); ?></td>
              <td><?php echo htmlspecialchars($s['business_number']); ?></td>
              <td><span class="status-badge <?php echo htmlspecialchars($s['plan_status']); ?>">
                <?php echo $s['plan_status'] === 'trial' ? '체험중' : ($s['plan_status'] === 'active' ? '운영 중' : '중지됨'); ?>
              </span></td>
              <td><?php echo htmlspecialchars(substr($s['created_at'], 0, 10)); ?></td>
              <td style="white-space:nowrap;">
                <?php if ($s['plan_status'] !== 'suspended'): ?>
                  <form method="post" style="display:inline;" onsubmit="return confirm('이 매장의 서비스 이용을 즉시 중지하시겠습니까?');">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                    <button type="submit" class="btn-mini" style="color:var(--danger);border-color:var(--danger);">중지</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                    <button type="submit" class="btn-mini">재개</button>
                  </form>
                <?php endif; ?>
                <button type="button" class="btn-mini" onclick="openDeleteModal('<?php echo htmlspecialchars($s['id'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>')">삭제</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<div class="modal-overlay" id="deleteStoreModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title" style="color:var(--danger);">매장을 완전히 삭제하시겠습니까?</h2>
    <p id="deleteStoreDesc" style="font-size:13px;color:var(--text-sub);margin-bottom:12px;"></p>
    <p style="font-size:13px;color:var(--text-sub);margin-bottom:8px;">계속하려면 대문자로 <b>DELETE</b> 를 입력하세요.</p>
    <form method="post">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="store_id" id="deleteStoreId" value="">
      <div class="form-group"><input type="text" name="confirm_text" placeholder="DELETE" required></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('deleteStoreModal').style.display='none'">취소</button>
        <button type="submit" class="btn-danger-outline" style="flex:1;">완전 삭제</button>
      </div>
    </form>
  </div>
</div>
<script>
function openDeleteModal(id, name) {
    document.getElementById('deleteStoreId').value = id;
    document.getElementById('deleteStoreDesc').textContent = name + ' 매장의 고객, 동의서, 매출 데이터가 모두 영구 삭제됩니다.';
    document.getElementById('deleteStoreModal').style.display = 'flex';
}
</script>
<?php require_once __DIR__ . '/includes/admin_layout_foot.php'; ?>
