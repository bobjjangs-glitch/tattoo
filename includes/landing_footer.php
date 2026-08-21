<footer class="site-footer">
  © <?php echo date('Y'); ?> CareForm. All rights reserved.
</footer>

<script>
/* ===== FAQ 아코디언 (모든 랜딩 계열 페이지 공통) ===== */
document.querySelectorAll('.faq-question').forEach(function (btn) {
  var item = btn.closest('.faq-item');
  var wrap = item.querySelector('.faq-answer-wrap');
  if (item.classList.contains('is-open')) {
    wrap.style.maxHeight = wrap.scrollHeight + 'px';
  }
  btn.addEventListener('click', function () {
    var isOpen = item.classList.contains('is-open');
    if (isOpen) {
      item.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
      wrap.style.maxHeight = '0px';
    } else {
      item.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      wrap.style.maxHeight = wrap.scrollHeight + 'px';
    }
  });
});
</script>
</body>
</html>
