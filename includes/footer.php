</main><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (e) {
        const sidebar = document.getElementById('sidebar');
        const toggle  = document.querySelector('.btn-sidebar-toggle');
        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            e.target !== toggle) {
            sidebar.classList.remove('open');
        }
    });
</script>
</body>
</html>