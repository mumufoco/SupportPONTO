<script <?= csp_script_nonce_attr() ?>>
// Safe HTML escape for dynamic API data inserted via innerHTML
function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
}

// Toggle backup options
document.getElementById('enable_auto_backup').addEventListener('change', function() {
    const display = this.checked ? 'block' : 'none';
    document.getElementById('backup_options').style.display = display;
    document.getElementById('backup_retention_group').style.display = display;
});

// Test password
function testPassword() {
    const password = document.getElementById('test_password').value;
    const resultDiv = document.getElementById('passwordTestResult');

    if (!password) {
        Toast.warning('Digite uma senha para testar');
        return;
    }

    resultDiv.innerHTML = '<div class="spinner spinner-sm"></div>';

    spFetch('<?= sp_route_url('admin.settings.security.test-password') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'password=' + encodeURIComponent(password)
    })
    .then(response => response.json())
    .then(data => {
        const strength = data.strength;
        const safeScore = parseFloat(strength.score) || 0;
        const progressBar = `
            <div class="sp-security-progress"><div class="sp-security-progress__bar" style="width: ${safeScore}%; background: var(--sp-${escHtml(strength.color)});"></div></div>
        `;

        if (data.success) {
            resultDiv.innerHTML = `
                <div class="sp-feedback-box sp-feedback-box--success">
                    <strong>✓ Senha válida!</strong><br>
                    ${progressBar}
                    <small>Força: <strong>${escHtml(strength.level)}</strong> (${safeScore}%)</small>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="sp-feedback-box sp-feedback-box--danger">
                    <strong>✗ Senha não atende os requisitos:</strong><br>
                    <ul class="sp-feedback-list">
                        ${data.errors.map(err => '<li>' + escHtml(err) + '</li>').join('')}
                    </ul>
                    ${progressBar}
                    <small>Força: <strong>${escHtml(strength.level)}</strong> (${safeScore}%)</small>
                </div>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="sp-feedback-box sp-feedback-box--danger">
                <strong>Erro ao testar senha</strong>
            </div>
        `;
        console.error(error);
    });
}

// View audit logs
function viewAuditLogs() {
    const container = document.getElementById('auditLogsContainer');
    container.style.display = 'block';
    container.innerHTML = '<div class="sp-state-loading"><div class="spinner"></div></div>';

    spFetch('<?= sp_route_url('admin.settings.security.audit-logs') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs.length > 0) {
                container.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Ação</th>
                                    <th>IP</th>
                                    <th>Data/Hora</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.logs.map(log => `
                                    <tr>
                                        <td>${escHtml(log.user)}</td>
                                        <td>${escHtml(log.action)}</td>
                                        <td><code>${escHtml(log.ip)}</code></td>
                                        <td>${escHtml(log.timestamp)}</td>
                                        <td>
                                            <span class="badge badge-${log.status === 'success' ? 'success' : 'danger'}">
                                                ${escHtml(log.status)}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <p class="sp-text-center-muted">
                        Mostrando ${parseInt(data.total) || 0} logs mais recentes
                    </p>
                `;
            } else {
                container.innerHTML = `
                    <div class="sp-state-empty">
                        <i class="fas fa-inbox sp-state-empty__icon"></i>
                        <p>Nenhum log de auditoria encontrado</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="sp-feedback-box--danger-md">
                    <strong>Erro ao carregar logs</strong>
                </div>
            `;
            console.error(error);
        });
}

// Create backup
function createBackup() {
    Confirm.show({
        title: 'Criar Backup',
        message: 'Deseja criar um backup do banco de dados agora?',
        icon: 'info',
        buttonText: 'Criar Backup',
        buttonClass: 'btn-modal-primary',
        onConfirm: function() {
            showLoading();
            const currentPassword = prompt('Confirme sua senha atual para criar o backup:');
            if (currentPassword === null) { hideLoading(); return; }
            const formData = new FormData();
            formData.append('current_password', currentPassword);
            spFetch('<?= sp_route_url('admin.settings.security.backup') ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success && data.queued) {
                    Toast.success('Backup enfileirado. Acompanhe o status até a liberação do download.');
                    if (data.status_url) {
                        window.open(data.status_url, '_blank');
                    }
                } else if (data.success) {
                    Toast.success(data.message || 'Backup criado com sucesso.');
                } else {
                    Toast.error(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                Toast.error('Erro ao criar backup');
                console.error(error);
            });
        }
    });
}

// Reset to defaults
function resetToDefaults() {
    Confirm.show({
        title: 'Restaurar Padrão',
        message: 'Deseja restaurar todas as configurações de segurança para o padrão? Esta ação não pode ser desfeita.',
        icon: 'danger',
        buttonText: 'Restaurar',
        onConfirm: function() {
            const currentPassword = prompt('Confirme sua senha atual para restaurar as configurações de segurança:');
            if (currentPassword === null) { return; }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= sp_route_url('admin.settings.security.reset') ?>';
            form.innerHTML = '<?= csrf_field() ?>';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'current_password';
            input.value = currentPassword;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>
