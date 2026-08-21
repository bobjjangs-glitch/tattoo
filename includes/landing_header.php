<?php
/**
 * includes/landing_header.php
 * 랜딩페이지 계열(landing.php, tool-consent-form.php 등)이 공통으로 쓰는 헤더/네비게이션.
 * 호출하는 쪽에서 미리 아래 변수를 설정해야 한다.
 *   $pageTitle       (string) <title> 태그
 *   $pageDescription (string) meta description
 *   $activeNav       (string) 'home' | 'features' | 'tool' | 'pricing' | 'faq' 중 현재 위치 (없어도 됨)
 *   $signupUrl, $loginUrl (string) 이미 호출부에서 정의됨
 */
$pageTitle       = $pageTitle ?? 'CareForm';
$pageDescription = $pageDescription ?? '뷰티·타투 매장을 위한 전자동의서 서비스';
$activeNav       = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
<style>
  :root {
    --primary: #55705C; --primary-hover: #435C49; --primary-light: #E8EEE7;
    --border: #E4E0D9; --text-main: #2B2A26; --text-sub: #8A8478; --text-body: #4A473F;
    --bg-page: #F7F6F2; --bg-card-soft: #F1EFE9;
    --accent-gold: #B8935A; --accent-terracotta: #C97B54;
    --radius: 14px; --radius-lg: 20px;
  }
  * { box-sizing: border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; background:var(--bg-page); color:var(--text-main); line-height:1.6; }
  a { text-decoration:none; color:inherit; }
  .wrap { max-width:1080px; margin:0 auto; padding:0 24px; }

  /* ===== 공통 헤더 ===== */
  header.site-header { position:sticky; top:0; background:rgba(247,246,242,0.92); backdrop-filter:blur(6px); border-bottom:1px solid var(--border); z-index:50; }
  .nav { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; max-width:1080px; margin:0 auto; }
  .logo { font-weight:800; font-size:19px; letter-spacing:-0.5px; color:var(--primary); }
  .nav-links { display:flex; gap:24px; align-items:center; font-size:14px; color:var(--text-sub); }
  .nav-links a { position:relative; }
  .nav-links a:hover { color:var(--primary); }
  .nav-links a.is-active { color:var(--primary); font-weight:700; }
  .btn-cta { background:var(--primary); color:#fff; padding:10px 20px; border-radius:999px; font-weight:700; font-size:14px; }
  .btn-cta:hover { background:var(--primary-hover); }

  .hero { padding:72px 0 56px; text-align:center; }
  .hero h1 { font-size:34px; font-weight:800; letter-spacing:-0.8px; margin:0 0 18px; }
  .hero h1 span { color:var(--primary); }
  .hero p.lead { font-size:16px; color:var(--text-sub); max-width:560px; margin:0 auto 30px; }
  .hero-badges { display:flex; justify-content:center; gap:18px; font-size:13px; color:var(--text-sub); margin-top:22px; flex-wrap:wrap; }
  .hero-badges span::before { content:"✓ "; color:var(--primary); font-weight:700; }
  .btn-primary-lg { display:inline-block; background:var(--primary); color:#fff; padding:16px 36px; border-radius:12px; font-weight:800; font-size:16px; box-shadow:0 8px 20px rgba(85,112,92,0.25); border:none; cursor:pointer; text-align:center; }
  .btn-primary-lg:hover { background:var(--primary-hover); transform:translateY(-1px); }
  .btn-secondary-lg { display:inline-block; background:#fff; color:var(--text-main); border:1px solid var(--border); padding:16px 34px; border-radius:12px; font-weight:700; font-size:15px; }
  .btn-secondary-lg:hover { border-color:var(--primary); color:var(--primary); }

  section { padding:60px 0; }
  .section-title { font-size:26px; font-weight:800; text-align:center; letter-spacing:-0.5px; margin:0 0 12px; }
  .section-sub { text-align:center; color:var(--text-sub); font-size:15px; margin:0 0 40px; }

  .problem-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:20px; }
  .problem-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:26px; }
  .problem-card .num { font-size:12px; font-weight:800; color:var(--accent-gold); }
  .problem-card h3 { font-size:16px; margin:8px 0 10px; }
  .problem-card p { font-size:14px; color:var(--text-body); margin:0; }

  .feature-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:18px; }
  .feature-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:24px; transition:transform .18s,box-shadow .18s; }
  .feature-card:hover { transform:translateY(-3px); box-shadow:0 10px 24px rgba(43,42,38,0.08); }
  .feature-card .icon { width:40px; height:40px; border-radius:12px; background:var(--primary-light); display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:14px; }
  .feature-card h3 { font-size:15px; margin:0 0 8px; }
  .feature-card p { font-size:13px; color:var(--text-sub); margin:0; }

  .trust-section { background:#fff; border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
  .trust-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; }
  .trust-item { display:flex; gap:12px; align-items:flex-start; }
  .trust-item .check { color:var(--primary); font-weight:800; font-size:16px; }
  .trust-item h4 { font-size:14px; margin:0 0 4px; }
  .trust-item p { font-size:13px; color:var(--text-sub); margin:0; }

  /* ===== 도구(무료 도구) 소개용 카드 (홈 화면에서만 사용, 정적) ===== */
  .tool-teaser { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:36px; display:grid; grid-template-columns:1fr 1fr; gap:32px; align-items:center; }
  @media (max-width:800px){ .tool-teaser { grid-template-columns:1fr; } }
  .tool-teaser-copy h3 { font-size:22px; margin:0 0 12px; }
  .tool-teaser-copy p { font-size:14px; color:var(--text-sub); margin:0 0 20px; }
  .tool-teaser-badges { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px; }
  .tool-teaser-badges span { font-size:12px; background:var(--bg-card-soft); border:1px solid var(--border); padding:5px 10px; border-radius:999px; color:var(--text-body); }
  .mock-a4 { background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 30px rgba(43,42,38,0.08); padding:22px 20px; font-size:11px; color:#333; }
  .mock-a4 h4 { font-size:13px; margin:0 0 10px; }
  .mock-a4 ul { list-style:none; padding:0; margin:0; }
  .mock-a4 li { padding:3px 0; display:flex; gap:6px; }
  .mock-a4 li::before { content:"☐"; color:var(--text-sub); }

  #pricing { }
  .pricing-box { max-width:420px; margin:0 auto; background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:40px 32px; text-align:center; box-shadow:0 10px 30px rgba(43,42,38,0.06); }
  .pricing-box .plan-name { font-size:13px; color:var(--text-sub); font-weight:700; }
  .pricing-box .price { font-size:36px; font-weight:800; margin:10px 0 4px; }
  .pricing-box .price span { font-size:14px; color:var(--text-sub); font-weight:500; }
  .pricing-box .vat { font-size:12px; color:var(--text-sub); margin-bottom:24px; }
  .pricing-box ul { list-style:none; padding:0; margin:0 0 26px; text-align:left; }
  .pricing-box li { font-size:14px; color:var(--text-body); padding:7px 0; }
  .pricing-box li::before { content:"✓ "; color:var(--primary); font-weight:700; }
  .pricing-note { font-size:12px; color:var(--text-sub); margin-top:16px; line-height:1.6; }

  /* ===== FAQ 아코디언 (공통) ===== */
  .faq-section .wrap { max-width:720px; }
  .faq-list { border-top:1px solid var(--border); }
  .faq-item { border-bottom:1px solid var(--border); }
  .faq-question {
    width:100%; display:flex; align-items:center; justify-content:space-between; gap:16px;
    background:none; border:none; padding:20px 4px; cursor:pointer; text-align:left;
    font-size:15px; font-weight:700; color:var(--text-main); font-family:inherit;
  }
  .faq-toggle { position:relative; width:18px; height:18px; flex:0 0 auto; }
  .faq-toggle span { position:absolute; background:var(--primary); border-radius:2px; transition:transform .2s ease, opacity .2s ease; }
  .faq-toggle span:first-child { width:100%; height:2px; top:50%; left:0; transform:translateY(-50%); }
  .faq-toggle span:last-child  { width:2px; height:100%; left:50%; top:0; transform:translateX(-50%); }
  .faq-item.is-open .faq-toggle span:last-child { transform:translateX(-50%) rotate(90deg); opacity:0; }
  .faq-answer-wrap { max-height:0; overflow:hidden; transition:max-height .25s ease; }
  .faq-answer { padding:0 4px 20px; font-size:14px; color:var(--text-sub); line-height:1.7; }

  /* ===== 도구 전용 페이지(tool-consent-form.php)에서만 쓰는 스타일 ===== */
  .tool-hero { background:var(--primary-light); border-bottom:1px solid var(--border); padding:56px 0 40px; text-align:left; }
  .tool-hero .wrap { max-width:860px; }
  .tool-hero h1 { font-size:32px; font-weight:800; margin:0 0 14px; letter-spacing:-0.6px; }
  .tool-hero p { font-size:15px; color:var(--text-body); max-width:640px; margin:0 0 14px; }
  .tool-hero .meta { font-size:12px; color:var(--primary); font-weight:700; }

  .tool-grid { display:grid; grid-template-columns:1fr 1fr; gap:32px; align-items:start; }
  @media (max-width:860px){ .tool-grid { grid-template-columns:1fr; } }
  .tool-form .form-group { margin-bottom:16px; }
  .tool-form label { display:block; font-size:13px; font-weight:700; color:var(--text-body); margin-bottom:6px; }
  .tool-form input[type="text"], .tool-form textarea {
    width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit; background:var(--bg-card-soft);
  }
  .tool-checklist { display:flex; flex-direction:column; gap:8px; background:var(--bg-card-soft); border:1px solid var(--border); border-radius:10px; padding:12px 14px; }
  .tool-checklist label { display:flex; align-items:flex-start; gap:8px; font-size:13px; font-weight:400; color:var(--text-body); cursor:pointer; }
  .tool-checklist input { margin-top:2px; }
  .tool-note { font-size:11px; color:var(--text-sub); margin-top:10px; line-height:1.6; text-align:center; }

  .tool-preview-wrap { position:sticky; top:100px; }
  .tool-preview-label { font-size:13px; font-weight:700; margin-bottom:10px; }
  .tool-preview-label span { display:block; font-size:11px; color:var(--text-sub); font-weight:400; margin-top:2px; }
  .tool-preview-a4 {
    background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 30px rgba(43,42,38,0.08);
    padding:28px 26px; aspect-ratio:210/297; overflow-y:auto; font-size:12px; color:#333;
  }
  .tool-preview-a4 h3 { font-size:15px; margin:0 0 4px; }
  .tool-preview-a4 .pv-date { font-size:11px; color:var(--text-sub); margin:0 0 14px; }
  .tool-preview-a4 dl { display:grid; grid-template-columns:1fr 1fr; gap:6px 12px; margin:0 0 16px; padding-bottom:14px; border-bottom:1px solid var(--border); }
  .tool-preview-a4 dt { font-size:10px; color:var(--text-sub); margin:0; }
  .tool-preview-a4 dd { font-size:13px; font-weight:700; margin:0; }
  .tool-preview-a4 h4 { font-size:12px; margin:14px 0 8px; color:var(--primary); }
  .tool-preview-a4 ul { list-style:none; padding:0; margin:0; }
  .tool-preview-a4 ul li { font-size:11.5px; padding:4px 0; display:flex; gap:6px; }
  .tool-preview-a4 ul li::before { content:"☐"; color:var(--text-sub); }
  .tool-preview-a4 .pv-agree { font-size:11.5px; white-space:pre-wrap; line-height:1.7; margin-top:6px; }
  .tool-preview-a4 .pv-sign { margin-top:26px; padding-top:14px; border-top:1px solid var(--border); font-size:11px; color:var(--text-sub); display:flex; justify-content:space-between; }

  .content-section { border-top:1px solid var(--border); padding:34px 0; max-width:760px; margin:0 auto; }
  .content-section h2 { font-size:20px; margin:0 0 14px; }
  .content-section p { font-size:14px; color:var(--text-body); margin:0 0 12px; }
  .content-disclaimer { font-size:12px; color:var(--text-sub); border-top:1px solid var(--border); padding-top:20px; max-width:760px; margin:0 auto; }

  .final-cta { text-align:center; background:linear-gradient(135deg,#5E7D66 0%,#3E5544 100%); border-radius:var(--radius-lg); padding:56px 24px; color:#fff; margin:0 24px; }
  .final-cta h2 { font-size:26px; margin:0 0 14px; }
  .final-cta p { color:rgba(255,255,255,0.85); margin:0 0 26px; }
  .final-cta .btn-primary-lg { background:#fff; color:var(--primary); }
  .final-cta .btn-primary-lg:hover { background:#f2f2f2; }

  footer.site-footer { text-align:center; padding:36px 24px; font-size:12px; color:var(--text-sub); }

  @media (max-width:640px){
    .nav-links { display:none; }
    .hero h1 { font-size:26px; }
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="nav">
    <a href="landing.php" class="logo">CareForm</a>
    <nav class="nav-links">
      <a href="landing.php#features" class="<?php echo $activeNav === 'features' ? 'is-active' : ''; ?>">기능</a>
      <a href="tool-consent-form.php" class="<?php echo $activeNav === 'tool' ? 'is-active' : ''; ?>">무료 도구</a>
      <a href="landing.php#pricing" class="<?php echo $activeNav === 'pricing' ? 'is-active' : ''; ?>">요금</a>
      <a href="landing.php#faq" class="<?php echo $activeNav === 'faq' ? 'is-active' : ''; ?>">자주 묻는 질문</a>
      <a href="index.php">로그인</a>
    </nav>
    <a href="signup.php" class="btn-cta">무료로 시작하기</a>
  </div>
</header>
