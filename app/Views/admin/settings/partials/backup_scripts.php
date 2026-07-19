<script <?= csp_script_nonce_attr() ?>>
function toast(msg, type) {
    type = type || 'success';
    const el     = document.getElementById('sp-toast');
    const msgEl  = document.getElementById('sp-toast-msg');
    el.className = 'toast align-items-center text-white border-0 bg-' + type;
    msgEl.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
}

let actionPasswordCallback = null;

/**
 * Janela flutuante que substitui prompt()/confirm() nativos do navegador
 * para acoes sensiveis (gerar/baixar backup, registrar teste de restauracao)
 * -- todas exigem reconfirmar a senha atual.
 */
function openPasswordModal(title, message, callback, opts) {
    opts = opts || {};
    document.getElementById('actionPasswordModalTitle').innerHTML = '<i class="bi bi-shield-lock-fill me-2"></i>' + title;
    document.getElementById('actionPasswordModalMessage').textContent = message;

    const passwordInput = document.getElementById('actionPasswordInput');
    const notesGroup = document.getElementById('actionPasswordNotesGroup');
    const notesInput = document.getElementById('actionPasswordNotesInput');
    passwordInput.value = '';
    notesGroup.style.display = opts.showNotes ? '' : 'none';
    notesInput.value = '';

    actionPasswordCallback = callback;

    const modalEl = document.getElementById('actionPasswordModal');
    new bootstrap.Modal(modalEl).show();
    modalEl.addEventListener('shown.bs.modal', function focusOnce() {
        passwordInput.focus();
        modalEl.removeEventListener('shown.bs.modal', focusOnce);
    });
}

function confirmActionPassword() {
    const password = document.getElementById('actionPasswordInput').value;
    if (!password) {
        document.getElementById('actionPasswordInput').focus();
        return;
    }
    const notes = document.getElementById('actionPasswordNotesInput').value;
    const callback = actionPasswordCallback;
    bootstrap.Modal.getInstance(document.getElementById('actionPasswordModal')).hide();
    if (callback) { callback(password, notes); }
}

document.getElementById('actionPasswordConfirmBtn').addEventListener('click', confirmActionPassword);
document.getElementById('actionPasswordInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); confirmActionPassword(); }
});

function runBackupCheck(btn) {
    btn.disabled = true;
    spFetch('<?= sp_route_url('admin.settings.backup.check') ?>', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                toast(data.message || 'Erro ao verificar backup.', 'danger');
            }
        })
        .catch(() => toast('Erro de comunicação com o servidor.', 'danger'))
        .finally(() => { btn.disabled = false; });
}

function generateBackup() {
    openPasswordModal(
        'Gerar backup agora',
        'Um novo backup do banco de dados será gerado agora. Confirme sua senha para continuar.',
        function (password) {
            const formData = new FormData();
            formData.append('current_password', password);

            spFetch('<?= sp_route_url('admin.settings.controls.backup') ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.queued) {
                        toast('Backup enfileirado. Acompanhe o status até a liberação do download.', 'info');
                        if (data.status_url) { window.open(data.status_url, '_blank'); }
                    } else if (data.success) {
                        toast(data.message || 'Backup gerado com sucesso.', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        toast(data.message || 'Não foi possível gerar o backup.', 'danger');
                    }
                })
                .catch(() => toast('Erro de comunicação com o servidor.', 'danger'));
        }
    );
}

function downloadBackup(filename) {
    openPasswordModal(
        'Baixar backup',
        'Confirme sua senha para baixar "' + filename + '".',
        function (password) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= site_url('admin/settings/backup/download/') ?>' + encodeURIComponent(filename);
            form.innerHTML = '<?= csrf_field() ?>';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'current_password';
            input.value = password;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    );
}

function recordRestoreTest() {
    openPasswordModal(
        'Registrar teste de restauração',
        'Confirma que este backup foi testado e restaura corretamente? Confirme sua senha para registrar.',
        function (password, notes) {
            const formData = new FormData();
            formData.append('current_password', password);
            formData.append('notes', notes);

            spFetch('<?= sp_route_url('admin.settings.backup.restore-test') ?>', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        toast(data.message || 'Teste de restauração registrado.', 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        toast(data.message || 'Erro ao registrar teste de restauração.', 'danger');
                    }
                })
                .catch(() => toast('Erro de comunicação com o servidor.', 'danger'));
        },
        { showNotes: true }
    );
}
</script>
