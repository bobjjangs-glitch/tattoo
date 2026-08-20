<?php $activePage = $activePage ?? ''; ?>
<aside class="sidebar admin-sidebar">
  <div class="sidebar-logo"><span class="logo-text">CareForm</span><span class="admin-tag">Admin</span></div>
  <nav class="sidebar-nav">
    <a href="dashboard.php" class="nav-item <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">📊 대시보드</a>
    <a href="members.php" class="nav-item <?php echo $activePage === 'members' ? 'active' : ''; ?>">👤 회원 관리</a>
    <a href="stores.php" class="nav-item <?php echo $activePage === 'stores' ? 'active' : ''; ?>">🏬 매장 관리</a>
    <a href="settings.php" class="nav-item <?php echo $activePage === 'settings' ? 'active' : ''; ?>">⚙️ 요금 설정</a>
    <a href="admins.php" class="nav-item <?php echo $activePage === 'admins' ? 'active' : ''; ?>">🔑 관리자 계정</a>
  </nav>
  <div class="sidebar-footer">
    <form method="POST" action="logout.php">
      <button type="submit" class="logout-btn">로그아웃</button>
    </form>
  </div>
</aside>
