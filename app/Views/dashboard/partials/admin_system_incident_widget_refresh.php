<script <?= csp_script_nonce_attr() ?>>
(function () {
    if (!window.SupportPontoDashboardRefreshRuntime) {
        return;
    }
    document.addEventListener('DOMContentLoaded', function () {
        window.SupportPontoDashboardRefreshRuntime.refreshAll();
    });
})();
</script>
