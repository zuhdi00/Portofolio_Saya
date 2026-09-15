<!-- ======= Footer ======= -->
<footer id="footer" class="footer">
    <div class="copyright">
        &copy; Copyright <strong><span>S-CORE</span></strong>. All Rights Reserved
    </div>
   
</footer><!-- End Footer -->

<a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

<!-- Vendor JS Files -->
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/chart.js/chart.umd.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/echarts/echarts.min.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/quill/quill.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/tinymce/tinymce.min.js"></script>
<script src="<?php echo BASE_URL; ?>/s-core/assets/vendor/php-email-form/validate.js"></script>

<!-- Template Main JS File -->
<script src="<?php echo BASE_URL; ?>/s-core/assets/js/main.js"></script>


<!-- ===== Perbaikan dropdown header (profil & notifikasi) ===== -->
<script>
(function () {
  function initDropdown() {
    var toggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');

    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
      // Bootstrap tersedia - inisialisasi manual (aman walau data-api sudah jalan)
      toggles.forEach(function (el) {
        if (!bootstrap.Dropdown.getInstance(el)) new bootstrap.Dropdown(el);
      });
      return;
    }

    // CADANGAN: Bootstrap JS tidak termuat -> buka/tutup dropdown secara manual
    console.warn('[S-CORE] Bootstrap JS tidak termuat. Dropdown memakai mode cadangan.');
    toggles.forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var menu = el.parentElement.querySelector('.dropdown-menu');
        if (!menu) return;
        var terbuka = menu.classList.contains('show');
        // tutup semua dropdown lain
        document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
          m.classList.remove('show');
        });
        if (!terbuka) {
          menu.classList.add('show');
          menu.style.position = 'absolute';
          menu.style.right = '0';
          menu.style.top = '100%';
        }
      });
    });
    // klik di luar -> tutup
    document.addEventListener('click', function () {
      document.querySelectorAll('.dropdown-menu.show').forEach(function (m) {
        m.classList.remove('show');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDropdown);
  } else {
    initDropdown();
  }
})();
</script>

</body>


</html>