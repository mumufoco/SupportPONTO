<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', () => {
    if (window.SupportPontoPunchUI && typeof window.SupportPontoPunchUI.initialize === 'function') {
        window.SupportPontoPunchUI.initialize();
    }
});
</script>
