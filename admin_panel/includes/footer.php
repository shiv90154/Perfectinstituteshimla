    </div><!-- /.admin-content -->
    <footer class="admin-footer">
        &copy; <?php echo date('Y'); ?> Perfect Institute. All rights reserved.
    </footer>
</main><!-- /main.admin-main (opened in sidebar.php) -->
</div><!-- /.admin-shell (opened in header.php) -->

<!-- Alpine.js for interactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Date pickers: show DD-MM-YYYY, submit YYYY-MM-DD -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<style>
    .flatpickr-wrapper { display: block; width: 100%; }
    .flatpickr-wrapper .flatpickr-calendar { z-index: 100000; }
    input.flatpickr-input.form-control, .flatpickr-wrapper > input[type="text"] { width: 100%; }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.flatpickr) return;
        document.querySelectorAll('input[type="date"]').forEach(function (el) {
            var proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
            var fp = flatpickr(el, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd-m-Y',
                allowInput: true,
                disableMobile: true,
                // Render the calendar inside a wrapper next to the input so it positions
                // correctly (and above the dimmed backdrop) inside modals.
                static: true
            });
            // form.reset() doesn't touch the visible alt input; resync it afterwards
            if (el.form) {
                el.form.addEventListener('reset', function () {
                    setTimeout(function () { fp.setDate(el.defaultValue || null, false); }, 0);
                });
            }
            // Keep the visible field in sync when scripts set .value directly (edit modals)
            // (guarded: flatpickr itself assigns el.value inside setDate, which would recurse forever)
            var syncing = false;
            Object.defineProperty(el, 'value', {
                get: function () { return proto.get.call(el); },
                set: function (v) {
                    proto.set.call(el, v);
                    if (syncing) return;
                    syncing = true;
                    try { fp.setDate(v || null, false); } finally { syncing = false; }
                }
            });
        });
    });
</script>

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
