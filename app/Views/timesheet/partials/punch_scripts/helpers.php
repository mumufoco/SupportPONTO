<script <?= csp_script_nonce_attr() ?>>
window.SupportPontoPunchHelpers = window.SupportPontoPunchHelpers || {
    qs(selector, scope = document) { return scope.querySelector(selector); },
    qsa(selector, scope = document) { return Array.from(scope.querySelectorAll(selector)); },
    show(el) { if (el) el.classList.remove('d-none'); },
    hide(el) { if (el) el.classList.add('d-none'); },
    setText(el, value) { if (el) el.textContent = value ?? ''; }
};
</script>
