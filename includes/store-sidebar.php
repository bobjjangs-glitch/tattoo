<?php
// $storeId, $storeName, $activePage, $actorRole('owner'|'admin'|'staff') 는 호출 페이지에서 설정
$actorRole = $actorRole ?? 'owner';
?>
<aside class="sidebar">
  <div class="sidebar-logo"><span class="logo-text">SalonForm</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item">🏠 매장 목록</a>
    <a href="store-dashboard.php?id=<?php echo urlencode($storeId); ?>"
       class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">📊 대시보드</a>
    <a href="store.php?id=<?php echo urlencode($storeId); ?>"
       class="nav-item <?php echo $activePage === 'customers' ? 'active' : ''; ?>">👥 고객 관리</a>
    <a href="consent.php?id=<?php echo urlencode($storeId); ?>"
       class="nav-item <?php echo $activePage === 'consent' ? 'active' : ''; ?>">📄 동의서 관리</a>
    <?php if ($actorRole === 'owner' || $actorRole === 'admin'): ?>
      <a href="billing.php?id=<?php echo urlencode($storeId); ?>"
         class="nav-item <?php echo $activePage === 'billing' ? 'active' : ''; ?>">💳 결제 관리</a>
      <a href="store-settings.php?id=<?php echo urlencode($storeId); ?>"
         class="nav-item <?php echo $activePage === 'settings' ? 'active' : ''; ?>">⚙️ 매장 설정</a>
    <?php endif; ?>
    <a href="mailto:support@salonform.club" class="nav-item">💬 문의하기</a>
  </nav>
  <div class="sidebar-footer">
    <form method="POST" action="logout.php">
      <button type="submit" class="logout-btn">로그아웃</button>
    </form>
  </div>
</aside>
