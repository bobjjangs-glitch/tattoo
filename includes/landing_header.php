<?php
/**
 * includes/landing_header.php
 * 랜딩페이지 계열(landing.php, tool-consent-form.php, terms.php, privacy.php, coming-soon.php 등)이
 * 공통으로 쓰는 헤더/네비게이션.
 * 호출하는 쪽에서 미리 아래 변수를 설정해야 한다.
 *   $pageTitle       (string) <title> 태그
 *   $pageDescription (string) meta description
 *   $activeNav       (string) 'home' | 'features' | 'tool' | 'pricing' | 'faq' 중 현재 위치 (없어도 됨)
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
    --radius: 14px; --radius-lg: 20px; --radius-xl: 28px;
    --gradient-brand: linear-gradient(135deg, #5E7D66 0%, #2F4A37 100%);
    --gradient-warm: linear-gradient(135deg, var(--accent-gold), var(--accent-terracotta));
    --shadow-soft: 0 10px 30px rgba(43,42,38,0.08);
    --shadow-lift: 0 18px 40px rgba(43,42,38,0.14);
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Apple SD Gothic Neo","Malgun Gothic",sans-serif; background:var(--bg-page); color:var(--text-main); line-height:1.6; overflow-x:hidden; }
  a { text-decoration:none; color:inherit; }
  .wrap { max-width:1080px; margin:0 auto; padding:0 24px; position:relative; }

  /* ===== 스크롤 리빌 애니메이션 ===== */
  .reveal { opacity:0; transform:translateY(24px); transition:opacity .6s ease, transform .6s ease; }
  .reveal.is-visible { opacity:1; transform:translateY(0); }
  @media (prefers-reduced-motion: reduce) {
    .reveal { opacity:1; transform:none; transition:none; }
    html { scroll-behavior:auto; }
  }

  /* ===== 공통 헤더 (글래스모피즘) ===== */
  header.site-header { position:sticky; top:0; background:rgba(247,246,242,0.78); backdrop-filter:blur(14px) saturate(160%); -webkit-backdrop-filter:blur(14px) saturate(160%); border-bottom:1px solid var(--border); z-index:80; }
  .nav { display:flex; align-items:center; justify-content:space-between; padding:14px 24px; max-width:1080px; margin:0 auto; position:relative; }
  .logo { display:flex; align-items:center; gap:8px; font-weight:800; font-size:19px; letter-spacing:-0.5px; color:var(--primary); }
  .logo .logo-mark { width:28px; height:28px; border-radius:9px; background:var(--gradient-brand); display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; box-shadow:var(--shadow-soft); }
  .nav-links { display:flex; gap:28px; align-items:center; font-size:14px; color:var(--text-sub); }
  .nav-links a { position:relative; padding:4px 0; }
  .nav-links a::after { content:""; position:absolute; left:0; bottom:-2px; width:0; height:2px; background:var(--gradient-warm); transition:width .2s ease; border-radius:2px; }
  .nav-links a:hover { color:var(--primary); }
  .nav-links a:hover::after { width:100%; }
  .nav-links a.is-active { color:var(--primary); font-weight:700; }
  .nav-links a.is-active::after { width:100%; }
  .nav-right { display:flex; align-items:center; gap:14px; }
  .btn-cta { background:var(--gradient-brand); color:#fff; padding:11px 22px; border-radius:999px; font-weight:700; font-size:14px; box-shadow:0 6px 16px rgba(47,74,55,0.28); transition:transform .18s ease, box-shadow .18s ease; }
  .btn-cta:hover { transform:translateY(-1px); box-shadow:0 10px 22px rgba(47,74,55,0.34); }

  /* 모바일 메뉴 토글 */
  .nav-toggle { display:none; width:38px; height:38px; border:1px solid var(--border); border-radius:10px; background:#fff; align-items:center; justify-content:center; cursor:pointer; }
  .nav-toggle span { display:block; width:16px; height:2px; background:var(--text-main); position:relative; }
  .nav-toggle span::before, .nav-toggle span::after { content:""; position:absolute; left:0; width:16px; height:2px; background:var(--text-main); transition:transform .2s ease; }
  .nav-toggle span::before { top:-5px; }
  .nav-toggle span::after { top:5px; }
  .nav-toggle.is-open span { background:transparent; }
  .nav-toggle.is-open span::before { transform:translateY(5px) rotate(45deg); }
  .nav-toggle.is-open span::after { transform:translateY(-5px) rotate(-45deg); }

  .mobile-panel { display:none; flex-direction:column; gap:2px; background:#fff; border-top:1px solid var(--border); padding:8px 24px 16px; }
  .mobile-panel a { padding:12px 4px; font-size:15px; color:var(--text-body); border-bottom:1px solid var(--border); }
  .mobile-panel a:last-child { border-bottom:none; }
  .mobile-panel.is-open { display:flex; }

  /* ===== 히어로: 블러 블롭 배경 ===== */
  .hero { padding:96px 0 64px; text-align:center; position:relative; overflow:hidden; }
  .hero-blob { position:absolute; border-radius:50%; filter:blur(60px); opacity:0.5; z-index:0; pointer-events:none; }
  .hero-blob.b1 { width:420px; height:420px; background:var(--gradient-brand); top:-160px; left:-120px; }
  .hero-blob.b2 { width:360px; height:360px; background:var(--gradient-warm); top:-100px; right:-140px; opacity:0.35; }
  .hero .wrap { z-index:1; }
  .hero-eyebrow { display:inline-flex; align-items:center; gap:6px; background:var(--primary-light); color:var(--primary); font-size:12px; font-weight:700; padding:6px 14px; border-radius:999px; margin-bottom:22px; }
  .hero h1 { font-size:40px; font-weight:800; letter-spacing:-1px; margin:0 0 20px; line-height:1.28; }
  .hero h1 span { background:var(--gradient-brand); -webkit-background-clip:text; background-clip:text; color:transparent; }
  .hero p.lead { font-size:17px; color:var(--text-sub); max-width:580px; margin:0 auto 34px; }
  .hero-badges { display:flex; justify-content:center; gap:12px; font-size:13px; color:var(--text-body); margin-top:26px; flex-wrap:wrap; }
  .hero-badges span { display:inline-flex; align-items:center; gap:6px; background:#fff; border:1px solid var(--border); padding:7px 14px; border-radius:999px; box-shadow:var(--shadow-soft); }
  .hero-badges span::before { content:"✓"; color:var(--primary); font-weight:800; }

  .btn-primary-lg { display:inline-block; background:var(--gradient-brand); color:#fff; padding:17px 38px; border-radius:14px; font-weight:800; font-size:16px; box-shadow:0 14px 30px rgba(47,74,55,0.28); border:none; cursor:pointer; text-align:center; transition:transform .18s ease, box-shadow .18s ease; }
  .btn-primary-lg:hover { transform:translateY(-2px); box-shadow:0 18px 36px rgba(47,74,55,0.34); }
  .btn-secondary-lg { display:inline-block; background:#fff; color:var(--text-main); border:1px solid var(--border); padding:16px 34px; border-radius:14px; font-weight:700; font-size:15px; transition:border-color .18s ease, color .18s ease; }
  .btn-secondary-lg:hover { border-color:var(--primary); color:var(--primary); }

  section { padding:72px 0; position:relative; }
  .section-eyebrow { display:block; text-align:center; font-size:12px; font-weight:800; letter-spacing:1px; color:var(--accent-gold); text-transform:uppercase; margin-bottom:10px; }
  .section-title { font-size:28px; font-weight:800; text-align:center; letter-spacing:-0.6px; margin:0 0 14px; }
  .section-sub { text-align:center; color:var(--text-sub); font-size:15px; margin:0 0 46px; }

  .problem-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:22px; }
  .problem-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-xl); padding:28px; transition:transform .2s ease, box-shadow .2s ease; }
  .problem-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lift); }
  .problem-card .num { display:inline-flex; width:32px; height:32px; align-items:center; justify-content:center; border-radius:10px; background:var(--primary-light); font-size:12px; font-weight:800; color:var(--primary); margin-bottom:14px; }
  .problem-card h3 { font-size:16px; margin:0 0 10px; }
  .problem-card p { font-size:14px; color:var(--text-body); margin:0; }

  .feature-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:20px; }
  .feature-card { background:#fff; border:1px solid var(--border); border-radius:var(--radius-lg); padding:26px; transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
  .feature-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lift); border-color:var(--primary); }
  .feature-card .icon { width:44px; height:44px; border-radius:14px; background:var(--gradient-brand); display:flex; align-items:center; justify-content:center; font-size:19px; margin-bottom:16px; box-shadow:0 8px 18px rgba(47,74,55,0.22); }
  .feature-card h3 { font-size:15px; margin:0 0 8px; }
  .feature-card p { font-size:13px; color:var(--text-sub); margin:0; }

  .trust-section { background:#fff; border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
  .trust-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:20px; }
  .trust-item { display:flex; gap:14px; align-items:flex-start; padding:18px; border-radius:var(--radius); border-left:3px solid var(--primary); background:var(--bg-card-soft); }
  .trust-item .check { color:var(--primary); font-weight:800; font-size:16px; }
  .trust-item h4 { font-size:14px; margin:0 0 4px; }
  .trust-item p { font-size:13px; color:var(--text-sub); margin:0; }

  /* ===== 도구(무료 도구) 소개용 카드 (홈 화면에서만 사용, 정적) ===== */
  .tool-teaser { position:relative; background:#fff; border:1px solid var(--border); border-radius:var(--radius-xl); padding:40px; display:grid; grid-template-columns:1fr 1fr; gap:36px; align-items:center; overflow:hidden; box-shadow:var(--shadow-soft); }
  .tool-teaser::before { content:""; position:absolute; top:0; left:0; width:100%; height:5px; background:var(--gradient-warm); }
  @media (max-width:800px){ .tool-teaser { grid-template-columns:1fr; } }
  .tool-teaser-copy h3 { font-size:23px; margin:0 0 12px; }
  .tool-teaser-copy p { font-size:14px; color:var(--text-sub); margin:0 0 20px; }
  .tool-teaser-badges { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px; }
  .tool-teaser-badges span { font-size:12px; background:var(--bg-card-soft); border:1px solid var(--border); padding:6px 12px; border-radius:999px; color:var(--text-body); font-weight:600; }
  .mock-a4 { position:relative; background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow-lift); padding:24px 22px; font-size:11px; color:#333; transform:rotate(1.5deg); }
  .mock-a4::after { content:"FREE"; position:absolute; top:14px; right:14px; font-size:10px; font-weight:800; color:var(--accent-terracotta); background:#FBEDE4; padding:3px 8px; border-radius:999px; }
  .mock-a4 h4 { font-size:13px; margin:0 0 12px; }
  .mock-a4 ul { list-style:none; padding:0; margin:0; }
  .mock-a4 li { padding:4px 0; display:flex; gap:6px; }
  .mock-a4 li::before { content:"☐"; color:var(--text-sub); }

  .pricing-box { position:relative; max-width:440px; margin:0 auto; background:#fff; border:1px solid var(--border); border-radius:var(--radius-xl); padding:44px 34px; text-align:center; box-shadow:var(--shadow-lift); overflow:hidden; }
  .pricing-box::before { content:""; position:absolute; top:0; left:0; width:100%; height:6px; background:var(--gradient-brand); }
  .pricing-box .ribbon { position:absolute; top:18px; right:-34px; background:var(--gradient-warm); color:#fff; font-size:11px; font-weight:800; padding:5px 40px; transform:rotate(38deg); box-shadow:0 4px 10px rgba(0,0,0,0.15); }
  .pricing-box .plan-name { font-size:13px; color:var(--text-sub); font-weight:700; }
  .pricing-box .price { font-size:38px; font-weight:800; margin:12px 0 4px; }
  .pricing-box .price span { font-size:14px; color:var(--text-sub); font-weight:500; }
  .pricing-box .vat { font-size:12px; color:var(--text-sub); margin-bottom:26px; }
  .pricing-box ul { list-style:none; padding:0; margin:0 0 28px; text-align:left; }
  .pricing-box li { font-size:14px; color:var(--text-body); padding:8px 0; }
  .pricing-box li::before { content:"✓ "; color:var(--primary); font-weight:700; }
  .pricing-note { font-size:12px; color:var(--text-sub); margin-top:18px; line-height:1.6; }

  /* ===== FAQ 아코디언 (공통) ===== */
  .faq-section .wrap { max-width:720px; }
  .faq-list { border-top:1px solid var(--border); }
  .faq-item { border-bottom:1px solid var(--border); transition:background .18s ease; border-radius:10px; }
  .faq-item:hover { background:var(--bg-card-soft); }
  .faq-question {
    width:100%; display:flex; align-items:center; justify-content:space-between; gap:16px;
    background:none; border:none; padding:20px 12px; cursor:pointer; text-align:left;
    font-size:15px; font-weight:700; color:var(--text-main); font-family:inherit;
  }
  .faq-toggle { position:relative; width:20px; height:20px; flex:0 0 auto; border-radius:50%; background:var(--primary-light); }
  .faq-toggle span { position:absolute; background:var(--primary); border-radius:2px; transition:transform .2s ease, opacity .2s ease; }
  .faq-toggle span:first-child { width:10px; height:2px; top:50%; left:50%; transform:translate(-50%,-50%); }
  .faq-toggle span:last-child  { width:2px; height:10px; left:50%; top:50%; transform:translate(-50%,-50%); }
  .faq-item.is-open .faq-toggle span:last-child { transform:translate(-50%,-50%) rotate(90deg); opacity:0; }
  .faq-answer-wrap { max-height:0; overflow:hidden; transition:max-height .25s ease; }
  .faq-answer { padding:0 12px 20px; font-size:14px; color:var(--text-sub); line-height:1.7; }

  /* ===== 도구 전용 페이지(tool-consent-form.php)에서만 쓰는 스타일 ===== */
  .tool-hero { background:var(--primary-light); border-bottom:1px solid var(--border); padding:60px 0 44px; text-align:left; }
  .tool-hero .wrap { max-width:860px; }
  .tool-hero h1 { font-size:32px; font-weight:800; margin:0 0 14px; letter-spacing:-0.6px; }
  .tool-hero p { font-size:15px; color:var(--text-body); max-width:640px; margin:0 0 14px; }
  .tool-hero .meta { font-size:12px; color:var(--primary); font-weight:700; }

  .tool-grid { display:grid; grid-template-columns:1fr 1fr; gap:32px; align-items:start; }
  @media (max-width:860px){ .tool-grid { grid-template-columns:1fr; } }
  .tool-form .form-group { margin-bottom:16px; }
  .tool-form label { display:block; font-size:13px; font-weight:700; color:var(--text-body); margin-bottom:6px; }
  .tool-form input[type="text"], .tool-form textarea {
    width:100%; padding:11px 12px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit; background:var(--bg-card-soft); transition:border-color .18s ease, box-shadow .18s ease;
  }
  .tool-form input[type="text"]:focus, .tool-form textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-light); background:#fff; }
  .tool-checklist { display:flex; flex-direction:column; gap:8px; background:var(--bg-card-soft); border:1px solid var(--border); border-radius:10px; padding:12px 14px; }
  .tool-checklist label { display:flex; align-items:flex-start; gap:8px; font-size:13px; font-weight:400; color:var(--text-body); cursor:pointer; }
  .tool-checklist input { margin-top:2px; }
  .tool-note { font-size:11px; color:var(--text-sub); margin-top:10px; line-height:1.6; text-align:center; }

  .tool-preview-wrap { position:sticky; top:100px; }
  .tool-preview-label { font-size:13px; font-weight:700; margin-bottom:10px; }
  .tool-preview-label span { display:block; font-size:11px; color:var(--text-sub); font-weight:400; margin-top:2px; }
  .tool-preview-a4 {
    background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:var(--shadow-lift);
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
  .content-section h2 { font-size:20px; margin:24px 0 14px; }
  .content-section h2:first-child { margin-top:0; }
  .content-section p { font-size:14px; color:var(--text-body); margin:0 0 12px; }
  .content-disclaimer { font-size:12px; color:var(--text-sub); border-top:1px solid var(--border); padding-top:20px; max-width:760px; margin:0 auto; }

  .final-cta { position:relative; text-align:center; background:var(--gradient-brand); border-radius:var(--radius-xl); padding:64px 24px; color:#fff; margin:0 24px; overflow:hidden; }
  .final-cta::before { content:""; position:absolute; width:300px; height:300px; background:var(--gradient-warm); border-radius:50%; filter:blur(70px); opacity:0.35; top:-100px; right:-60px; }
  .final-cta h2 { font-size:28px; margin:0 0 14px; position:relative; }
  .final-cta p { color:rgba(255,255,255,0.85); margin:0 0 28px; position:relative; }
  .final-cta .btn-primary-lg { background:#fff; color:var(--primary); position:relative; }
  .final-cta .btn-primary-lg:hover { background:#f2f2f2; }

  /* ===== 하단 푸터 (사업자 정보 포함) ===== */
  .site-footer { background:#20291F; color:#B9C2B4; padding:56px 0 0; margin-top:24px; }
  .site-footer a { color:#B9C2B4; transition:color .15s ease; }
  .site-footer a:hover { color:#fff; }
  .footer-top { display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:32px; padding-bottom:36px; }
  @media (max-width:720px){ .footer-top { grid-template-columns:1fr; gap:28px; } }
  .footer-brand .footer-logo { display:flex; align-items:center; gap:8px; font-size:18px; font-weight:800; color:#fff; margin-bottom:10px; }
  .footer-brand .footer-logo .logo-mark { width:24px; height:24px; border-radius:8px; background:var(--gradient-warm); display:flex; align-items:center; justify-content:center; font-size:12px; color:#fff; }
  .footer-brand p { font-size:13px; color:#94A08E; margin:0; max-width:260px; line-height:1.6; }
  .footer-col h4 { font-size:13px; color:#fff; margin:0 0 14px; font-weight:700; }
  .footer-col ul { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:10px; }
  .footer-col ul li a { font-size:13px; }
  .footer-bizinfo { border-top:1px solid rgba(255,255,255,0.08); padding:22px 0; font-size:12px; color:#8A9484; display:flex; flex-wrap:wrap; gap:6px 18px; }
  .footer-bizinfo span { display:inline-flex; gap:4px; }
  .footer-bizinfo strong { color:#B9C2B4; font-weight:600; }
  .footer-bottom { border-top:1px solid rgba(255,255,255,0.08); padding:18px 0 28px; font-size:12px; color:#7C8776; text-align:center; }
  @media (max-width:640px){ .footer-bizinfo { flex-direction:column; gap:6px; } }

  @media (max-width:640px){
    .nav-links { display:none; }
    .nav-toggle { display:flex; }
    .hero h1 { font-size:28px; }
    .hero { padding:64px 0 48px; }
    .hero-blob { display:none; }
    .final-cta { margin:0 12px; padding:48px 20px; }
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="nav">
    <a href="landing.php" class="logo"><span class="logo-mark">C</span>CareForm</a>
    <nav class="nav-links">
      <a href="landing.php#features" class="<?php echo $activeNav === 'features' ? 'is-active' : ''; ?>">기능</a>
      <a href="tool-consent-form.php" class="<?php echo $activeNav === 'tool' ? 'is-active' : ''; ?>">무료 도구</a>
      <a href="landing.php#pricing" class="<?php echo $activeNav === 'pricing' ? 'is-active' : ''; ?>">요금</a>
      <a href="landing.php#faq" class="<?php echo $activeNav === 'faq' ? 'is-active' : ''; ?>">자주 묻는 질문</a>
      <a href="index.php">로그인</a>
    </nav>
    <div class="nav-right">
      <a href="signup.php" class="btn-cta">무료로 시작하기</a>
      <button type="button" class="nav-toggle" id="navToggle" aria-label="메뉴 열기" aria-expanded="false">
        <span></span>
      </button>
    </div>
  </div>
  <div class="mobile-panel" id="mobilePanel">
    <a href="landing.php#features">기능</a>
    <a href="tool-consent-form.php">무료 도구</a>
    <a href="landing.php#pricing">요금</a>
    <a href="landing.php#faq">자주 묻는 질문</a>
    <a href="index.php">로그인</a>
  </div>
</header>
