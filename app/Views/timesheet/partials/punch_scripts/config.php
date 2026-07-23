<script <?= csp_script_nonce_attr() ?>>
window.SupportPontoPunchConfig = Object.assign({}, window.SupportPontoPunchConfig || {}, {
    debug: false,
    module: 'timesheet-punch',
    endpoints: {
        codigo: '<?= sp_route_url('timesheet.punch.code') ?>',
        cpf: '<?= sp_route_url('timesheet.punch.cpf') ?>',
        qr: '<?= sp_route_url('timesheet.punch.qr') ?>',
        face: '<?= sp_route_url('timesheet.punch.face') ?>',
        fingerprint: '<?= sp_route_url('timesheet.punch.fingerprint') ?>',
        sync: '<?= sp_route_url('timesheet.punch.sync') ?>',
    },
    methodReadiness: <?= json_encode($methodReadiness ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    enabledMethods: <?= json_encode($enabledMethods ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    supportsCamera: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
    supportsWebAuthn: !!window.PublicKeyCredential,
});
</script>
