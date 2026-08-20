<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

$keyword = trim($_GET['keyword'] ?? '');

// ss_users(회원/가입 계정) 기준으로 조회한다. 매장(ss_stores)과는 별개의 테이블이므로
// 매장을 하나도 만들지 않은 회원도 반드시 목록에 나와야 한다. 그래서 LEFT JOIN + GROUP BY로
// 회원별 매장 수를 세어서 함께 보여준다.
$sql = "SELECT u.id, u.email, u.name, u.phone, u.is_active, u.created_at, u.last_login_at,
               COUNT(s.id) AS store_count
        FROM ss_users u
        LEFT JOIN ss_stores s ON s.owner_id = u.id
        WHERE 1=1";
$params = [];

if ($keyword !== '') {
    $sql .= ' AND (u.email LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)';
    $params[] = "%$keyword%"; $params[] = "%$keyword%"; $params[] = "%$keyword%";
}

$sql .= ' GROUP BY u.id ORDER BY u.created_at DESC LIMIT 200';

$members = [];
$loadError = '';
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = '회원 목록을 불러오지 못했습니다.';
}

$totalMembers = 0;
try {
    $totalMembers = (int) $pdo->query('SELECT COUNT(*) FROM ss_users')->fetchColumn();
} catch (Throwable $e) {}

$activePage = 'members';
$pageTitle = '회원 관리';
require_once __DIR__ . '/includes/admin_layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($admin['name']); ?>님 (최고관리자)</span></header>
    <div class="page-content">
      <div class="page-header">
        <h1 class="page-title">회원 관리</h1>
        <span class="see-all">전체 <?php echo number_format($totalMembers); ?>명</span>
      </div>

      <?php if ($loadError): ?><div class="alert-error"><?php echo htmlspecialchars($loadError); ?></div><?php endif; ?>

      <form method="get" style="margin-bottom:16px;display:flex;gap:8px;">
        <input type="text" name="keyword" placeholder="이름, 이메일, 전화번호 검색"
               value="<?php echo htmlspecialchars($keyword); ?>"
               style="flex:1;border:1px solid var(--border);border-radius:8px;padding:10px 14px;">
        <button type="submit" class="btn-secondary">검색</button>
      </form>

      <table class="data-table">
        <thead>
          <tr>
            <th>이름</th><th>이메일</th><th>전화번호</th><th>보유 매장 수</th>
            <th>상태</th><th>가입일</th><th>최근 로그인</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$members): ?>
            <tr><td colspan="7" class="recent-empty">조건에 맞는 회원이 없습니다.</td></tr>
          <?php endif; ?>
          <?php foreach ($members as $m): ?>
            <tr>
              <td><?php echo htmlspecialchars($m['name'] ?: '-'); ?></td>
              <td><?php echo htmlspecialchars($m['email']); ?></td>
              <td><?php echo htmlspecialchars($m['phone'] ?: '-'); ?></td>
              <td><?php echo (int)$m['store_count']; ?>개</td>
              <td>
                <span class="status-badge <?php echo $m['is_active'] ? 'active' : 'suspended'; ?>">
                  <?php echo $m['is_active'] ? '활성' : '비활성'; ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars(substr($m['created_at'], 0, 10)); ?></td>
              <td><?php echo $m['last_login_at'] ? htmlspecialchars(substr($m['last_login_at'], 0, 10)) : '-'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/admin_layout_foot.php'; ?>
