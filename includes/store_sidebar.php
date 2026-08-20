<?php
// $storeId, $storeName, $activePage 는 호출하는 페이지에서 미리 설정해야 함
// $actorRole 은 store.php, consent.php처럼 직원 로그인을 지원하는 페이지에서 설정됨 (owner/admin/staff)
// $actorRole을 설정하지 않는 페이지(store-dashboard.php, billing.php 등)는 대표 전용이므로 기본값 owner
$actorRole = $actorRole ?? 'owner';
$isOwner = ($actorRole === 'owner');
$canManageConsent = in_array($actorRole, ['owner', 'admin'], true);
?>
<aside class="sidebar">
  <div class="sidebar-logo"><span class="logo-text">SalonForm</span></div>
  <nav class="sidebar-nav">
    <?php if ($isOwner): ?>
      <a href="dashboard.php" class="nav-item">🏠 매장 목록</a>
      <a href="store-dashboard.php?id=<?php echo urlencode($storeId); ?>"
         class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">📊 대시보드</a>
    <?php endif; ?>
    <a href="store.php?id=<?php echo urlencode($storeId); ?>"
       class="nav-item <?php echo $activePage === 'customers' ? 'active' : ''; ?>">👥 고객 관리</a>
    <a href="sales.php?id=<?php echo urlencode($storeId); ?>"
       class="nav-item <?php echo $activePage === 'sales' ? 'active' : ''; ?>">💰 매출 관리</a>
    <?php if ($canManageConsent): ?>
      <a href="consent.php?id=<?php echo urlencode($storeId); ?>"
         class="nav-item <?php echo $activePage === 'consent' ? 'active' : ''; ?>">📄 동의서 관리</a>
    <?php endif; ?>
    <?php if ($isOwner): ?>
      <a href="billing.php?id=<?php echo urlencode($storeId); ?>"
         class="nav-item <?php echo $activePage === 'billing' ? 'active' : ''; ?>">💳 결제 관리</a>
      <a href="store-settings.php?id=<?php echo urlencode($storeId); ?>"
         class="nav-item <?php echo $activePage === 'settings' ? 'active' : ''; ?>">⚙️ 매장 설정</a>
    <?php endif; ?>
    <a href="mailto:support@salonform.club" class="nav-item">💬 문의하기</a>
  </nav>
  <div class="sidebar-footer">
    <form method="POST" action="<?php echo $isOwner ? 'logout.php' : 'staff-logout.php'; ?>">
      <button type="submit" class="logout-btn">로그아웃</button>
    </form>
  </div>
</aside>
