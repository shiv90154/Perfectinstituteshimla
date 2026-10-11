    </div><!-- /.admin-content -->
    <footer class="admin-footer">
        &copy; <?php echo date('Y'); ?> Perfect Institute. All rights reserved.
    </footer>
</main><!-- /main.admin-main (opened in sidebar.php) -->
</div><!-- /.admin-shell (opened in header.php) -->

<!-- Alpine.js for interactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Custom JavaScript -->
<script src="assets/js/custom.js"></script>

<!-- Mobile sidebar toggle -->
<script>
    (function () {
        var toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                window.dispatchEvent(new Event('toggle-mobile-sidebar'));
            });
        }
    })();
</script>

</body>
</html>
