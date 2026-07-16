<script <?= csp_script_nonce_attr() ?>>
function runBackupCheck() {
    const btn = event.target.closest('button');
    btn.disabled = true;
    spFetch('<?= sp_route_url('admin.settings.backup.check') ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Erro ao verificar backup.');
            }
        })
        .catch(() => alert('Erro de comunicação com o servidor.'))
        .finally(() => { btn.disabled = false; });
}

function generateBackup() {
    if (!confirm('Gerar um novo backup do banco de dados agora?')) return;

    const currentPassword = prompt('Confirme sua senha atual para gerar o backup:');
    if (currentPassword === null) return;

    const formData = new FormData();
    formData.append('current_password', currentPassword);

    spFetch('<?= sp_route_url('admin.settings.controls.backup') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.queued) {
                alert('Backup enfileirado. Acompanhe o status até a liberação do download.');
                if (data.status_url) { window.open(data.status_url, '_blank'); }
            } else if (data.success) {
                alert(data.message || 'Backup gerado com sucesso.');
                location.reload();
            } else {
                alert(data.message || 'Não foi possível gerar o backup.');
            }
        })
        .catch(() => alert('Erro de comunicação com o servidor.'));
}

function downloadBackup(filename) {
    const currentPassword = prompt('Confirme sua senha atual para baixar este backup:');
    if (currentPassword === null) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= site_url('admin/settings/backup/download/') ?>' + encodeURIComponent(filename);
    form.innerHTML = '<?= csrf_field() ?>';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'current_password';
    input.value = currentPassword;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

function recordRestoreTest() {
    if (!confirm('Confirma que este backup foi testado e restaura corretamente?')) return;

    const currentPassword = prompt('Confirme sua senha atual para registrar o teste:');
    if (currentPassword === null) return;

    const notes = document.getElementById('restoreTestNotes').value;
    const formData = new FormData();
    formData.append('current_password', currentPassword);
    formData.append('notes', notes);

    spFetch('<?= sp_route_url('admin.settings.backup.restore-test') ?>', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Erro ao registrar teste de restauração.');
            }
        })
        .catch(() => alert('Erro de comunicação com o servidor.'));
}
</script>
