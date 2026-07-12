<script <?= csp_script_nonce_attr() ?>>
const SettingsEndpoints = {
    saveLogo: '<?= esc(sp_route_url('settings.logo.save')) ?>',
    saveGeneral: '<?= esc(sp_route_url('settings.save-general')) ?>',
    saveNotifications: '<?= esc(sp_route_url('settings.save-notifications')) ?>',
    saveApis: '<?= esc(sp_route_url('settings.save-apis')) ?>',
    saveIcpBrasil: '<?= esc(sp_route_url('settings.save-icp-brasil')) ?>',
    saveBackup: '<?= esc(sp_route_url('settings.save-backup')) ?>',
    workShifts: '<?= esc(sp_route_url('settings.work-shifts')) ?>',
    workShiftsStore: '<?= esc(sp_route_url('settings.work-shifts.store')) ?>',
    geofences: '<?= esc(sp_route_url('settings.geofences')) ?>',
    geofencesStore: '<?= esc(sp_route_url('settings.geofences.store')) ?>',
    holidays: '<?= esc(sp_route_url('settings.holidays.json')) ?>',
    holidaysStore: '<?= esc(sp_route_url('settings.holidays.store')) ?>',
    smtpTest: '<?= esc(sp_route_url('settings.smtp.test')) ?>'
};

const Toast = {
    show: function(type, title, message, duration = 4000) {
        const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas ${icons[type]}"></i></div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
        `;
        document.getElementById('toastContainer').appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },
    success: function(message, title = 'Sucesso') { this.show('success', title, message); },
    error: function(message, title = 'Erro') { this.show('error', title, message); },
    warning: function(message, title = 'Atenção') { this.show('warning', title, message); },
    info: function(message, title = 'Informação') { this.show('info', title, message); }
};

const Confirm = {
    modal: null,
    callback: null,
    show: function(options) {
        const { title = 'Confirmar Ação', message = 'Tem certeza?', icon = 'warning', buttonText = 'Confirmar', buttonClass = 'btn-confirm-danger', onConfirm } = options;
        const iconClasses = { warning: 'fa-exclamation-triangle', danger: 'fa-trash-alt', info: 'fa-question-circle' };
        document.getElementById('confirmTitle').textContent = title;
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmIcon').className = `modal-icon ${icon}`;
        document.getElementById('confirmIcon').innerHTML = `<i class="fas ${iconClasses[icon] || iconClasses.warning}"></i>`;
        document.getElementById('confirmBtn').className = `btn ${buttonClass}`;
        document.getElementById('confirmBtn').textContent = buttonText;
        this.callback = onConfirm;
        if (!this.modal) this.modal = new bootstrap.Modal('#confirmModal');
        this.modal.show();
    },
    execute: function() {
        if (this.callback) this.callback();
        if (this.modal) this.modal.hide();
    }
};
document.getElementById('confirmBtn').addEventListener('click', () => Confirm.execute());


let logoCropper = null;
let selectedLogoFile = null;

$('#logoFileHQ').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const img = new Image();
    img.onload = () => {
        if (img.width < 512 || img.height < 512) {
            Toast.error('Logo em baixa qualidade. Use no mínimo 512x512.');
            $('#logoFileHQ').val('');
            return;
        }
        selectedLogoFile = file;
        initLogoCropper(file);
    };
    img.src = URL.createObjectURL(file);
});

$('#logoAspectRatio').on('change', function() {
    if (logoCropper) {
        logoCropper.setAspectRatio(parseFloat(this.value));
    }
});

function initLogoCropper(file) {
    const reader = new FileReader();
    reader.onload = function(ev) {
        const preview = document.getElementById('logoCropPreview');
        preview.src = ev.target.result;
        if (logoCropper) logoCropper.destroy();
        logoCropper = new Cropper(preview, {
            aspectRatio: parseFloat($('#logoAspectRatio').val() || '1'),
            viewMode: 1,
            autoCropArea: 0.9,
            responsive: true,
            preview: '.logo-preview-box'
        });
    };
    reader.readAsDataURL(file);
}

$('#saveLogoCroppedBtn').on('click', function() {
    if (!selectedLogoFile || !logoCropper) {
        Toast.warning('Selecione uma logo e ajuste o recorte antes de salvar.');
        return;
    }

    const canvas = logoCropper.getCroppedCanvas({
        minWidth: 256,
        minHeight: 128,
        maxWidth: 2048,
        maxHeight: 2048,
        imageSmoothingQuality: 'high'
    });

    const cropData = canvas.toDataURL('image/png', 1.0);
    const fd = new FormData();
    fd.append('logo_file', selectedLogoFile);
    fd.append('crop_data', cropData);

    $.ajax({
        url: SettingsEndpoints.saveLogo,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(r) {
            if (r.success) {
                Toast.success('Logo salva com sucesso (original, crop e small).');
                setTimeout(() => window.location.reload(), 900);
            } else {
                Toast.error(r.message || 'Erro ao salvar logo.');
            }
        },
        error: function(xhr) {
            Toast.error(xhr.responseJSON?.message || 'Falha ao processar logo.');
        }
    });
});

$(document).ready(function() {
    $('.cnpj-mask').mask('00.000.000/0000-00');
    $('.cep-mask').mask('00000-000');
    $('.phone-mask').mask('(00) 00000-0000');
    
    loadShifts();
    loadGeofences();
    loadHolidays();

    $('#form-general').on('submit', function(e) {
        e.preventDefault();
        submitForm(SettingsEndpoints.saveGeneral, new FormData(this), 'Configurações gerais salvas com sucesso!');
    });
    $('#form-notifications').on('submit', function(e) {
        e.preventDefault();
        submitForm(SettingsEndpoints.saveNotifications, $(this).serialize(), 'Configurações de notificações salvas!');
    });
    $('#form-apis').on('submit', function(e) {
        e.preventDefault();
        submitForm(SettingsEndpoints.saveApis, $(this).serialize(), 'Chaves de API salvas com sucesso!');
    });
    $('#form-icp').on('submit', function(e) {
        e.preventDefault();
        submitForm(SettingsEndpoints.saveIcpBrasil, new FormData(this), 'Certificado digital salvo!');
    });
    $('#form-backup').on('submit', function(e) {
        e.preventDefault();
        submitForm(SettingsEndpoints.saveBackup, $(this).serialize(), 'Configurações de backup salvas!');
    });
});

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
}

function spSettingsEntityBase(type) {
    return `${spRouteBase('settings')}/${type}`;
}

function spRouteBase(name) {
    if (name === 'settings') {
        return '<?= esc(sp_settings_center_path()) ?>';
    }

    return '/';
}

function submitForm(url, data, successMsg) {
    const isFormData = data instanceof FormData;
    $.ajax({
        url: url, method: 'POST', data: data,
        processData: !isFormData, contentType: isFormData ? false : 'application/x-www-form-urlencoded',
        success: function(r) {
            if (r.success !== false) {
                Toast.success(successMsg);
            } else {
                Toast.error(r.message || 'Erro ao salvar configurações');
            }
        },
        error: function(xhr) {
            Toast.error('Erro de conexão: ' + xhr.status);
        }
    });
}

function loadShifts() {
    $.get(SettingsEndpoints.workShifts, function(r) {
        let html = '';
        (r.data || []).forEach(s => {
            const types = {morning:'Manhã',afternoon:'Tarde',night:'Noite',custom:'Personalizado'};
            html += `<tr>
                <td><span style="color:${escHtml(s.color||'#9DB89D')}">●</span> ${escHtml(s.name)}</td>
                <td>${escHtml(types[s.type]||s.type)}</td>
                <td>${escHtml(s.start_time)}</td><td>${escHtml(s.end_time)}</td>
                <td>${parseInt(s.break_duration)||0} min</td>
                <td><span class="badge bg-${s.active?'success':'secondary'}">${s.active?'Ativo':'Inativo'}</span></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editShift(${parseInt(s.id)})" title="Editar"><i class="bi bi-pencil-fill"></i></button>
                    <button class="btn btn-secondary btn-sm" onclick="toggleShift(${parseInt(s.id)})" title="Ativar/Desativar"><i class="bi bi-toggle-on"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteShift(${parseInt(s.id)}, ${JSON.stringify(s.name)})" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                </td>
            </tr>`;
        });
        $('#shiftsBody').html(html || '<tr><td colspan="7" class="text-center text-muted">Nenhuma jornada cadastrada</td></tr>');
    });
}

function openShiftModal(id=null) {
    $('#shiftForm')[0].reset();
    $('#shift_id').val('');
    $('#shift_color').val('#9DB89D');
    new bootstrap.Modal('#shiftModal').show();
}

function editShift(id) {
    $.get(SettingsEndpoints.workShifts, function(r) {
        const s = (r.data||[]).find(x=>x.id==id);
        if(s) {
            $('#shift_id').val(s.id);
            $('#shift_name').val(s.name);
            $('#shift_description').val(s.description);
            $('#shift_start').val(s.start_time);
            $('#shift_end').val(s.end_time);
            $('#shift_type').val(s.type);
            $('#shift_break').val(s.break_duration);
            $('#shift_color').val(s.color||'#9DB89D');
            new bootstrap.Modal('#shiftModal').show();
        }
    });
}

function saveShift() {
    const id = $('#shift_id').val();
    const url = id ? `${SettingsEndpoints.workShifts}/${id}/update` : SettingsEndpoints.workShiftsStore;
    $.post(url, $('#shiftForm').serialize(), function(r) {
        if(r.success) {
            bootstrap.Modal.getInstance('#shiftModal').hide();
            loadShifts();
            Toast.success(r.message || 'Jornada salva com sucesso!');
        } else {
            Toast.error(r.message || 'Erro ao salvar jornada');
        }
    });
}

function toggleShift(id) {
    $.post(`${SettingsEndpoints.workShifts}/${id}/toggle`, function(r) {
        loadShifts();
        Toast.info('Status da jornada alterado');
    });
}

function confirmDeleteShift(id, name) {
    Confirm.show({
        title: 'Excluir Jornada',
        message: `Tem certeza que deseja excluir a jornada "${name}"? Esta ação não pode ser desfeita.`,
        icon: 'danger',
        buttonText: 'Excluir',
        onConfirm: () => {
            $.post(`${SettingsEndpoints.workShifts}/${id}/delete`, function() {
                loadShifts();
                Toast.success('Jornada excluída com sucesso!');
            });
        }
    });
}

function loadGeofences() {
    $.get(SettingsEndpoints.geofences, function(r) {
        let html = '';
        (r.data || []).forEach(g => {
            html += `<tr>
                <td>${escHtml(g.name)}</td><td>${parseFloat(g.latitude)||0}</td><td>${parseFloat(g.longitude)||0}</td><td>${parseInt(g.radius)||0}m</td>
                <td><span class="badge bg-${g.active?'success':'secondary'}">${g.active?'Ativo':'Inativo'}</span></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editGeofence(${parseInt(g.id)})" title="Editar"><i class="bi bi-pencil-fill"></i></button>
                    <button class="btn btn-secondary btn-sm" onclick="toggleGeofenceItem(${parseInt(g.id)})" title="Ativar/Desativar"><i class="bi bi-toggle-on"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteGeofence(${parseInt(g.id)}, ${JSON.stringify(g.name)})" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                </td>
            </tr>`;
        });
        $('#geofencesBody').html(html || '<tr><td colspan="6" class="text-center text-muted">Nenhuma cerca cadastrada</td></tr>');
    });
}

function openGeofenceModal() { $('#geofenceForm')[0].reset(); $('#geofence_id').val(''); new bootstrap.Modal('#geofenceModal').show(); }

function editGeofence(id) {
    $.get(SettingsEndpoints.geofences, function(r) {
        const g = (r.data||[]).find(x=>x.id==id);
        if(g) { $('#geofence_id').val(g.id); $('#geofence_name').val(g.name); $('#geofence_lat').val(g.latitude); $('#geofence_lng').val(g.longitude); $('#geofence_radius').val(g.radius); new bootstrap.Modal('#geofenceModal').show(); }
    });
}

function saveGeofence() {
    const id = $('#geofence_id').val();
    const url = id ? `${SettingsEndpoints.geofences}/${id}/update` : SettingsEndpoints.geofencesStore;
    $.post(url, $('#geofenceForm').serialize(), function(r) {
        if(r.success) {
            bootstrap.Modal.getInstance('#geofenceModal').hide();
            loadGeofences();
            Toast.success(r.message || 'Cerca salva com sucesso!');
        } else {
            Toast.error(r.message || 'Erro ao salvar cerca');
        }
    });
}

function toggleGeofenceItem(id) {
    $.post(`${SettingsEndpoints.geofences}/${id}/toggle`, function() {
        loadGeofences();
        Toast.info('Status da cerca alterado');
    });
}

function confirmDeleteGeofence(id, name) {
    Confirm.show({
        title: 'Excluir Cerca Virtual',
        message: `Tem certeza que deseja excluir a cerca "${name}"? Esta ação não pode ser desfeita.`,
        icon: 'danger',
        buttonText: 'Excluir',
        onConfirm: () => {
            $.post(`${SettingsEndpoints.geofences}/${id}/delete`, function() {
                loadGeofences();
                Toast.success('Cerca excluída com sucesso!');
            });
        }
    });
}

function loadHolidays() {
    $.get(SettingsEndpoints.holidays, function(r) {
        let html = '';
        const types = {national:'Nacional',state:'Estadual',municipal:'Municipal',company:'Empresa','non_working':'Dia Nao Trabalhado'};
        const typeBadge = {national:'primary',state:'info',municipal:'secondary',company:'success','non_working':'warning'};
        (r.data || []).forEach(h => {
            html += `<tr>
                <td>${escHtml(h.date)}</td><td>${escHtml(h.name)}</td><td>${escHtml(types[h.type]||h.type)}</td>
                <td>${h.recurring?'Sim':'Não'}</td>
                <td><span class="badge bg-${h.active?'success':'secondary'}">${h.active?'Ativo':'Inativo'}</span></td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editHoliday(${parseInt(h.id)})" title="Editar"><i class="bi bi-pencil-fill"></i></button>
                    <button class="btn btn-secondary btn-sm" onclick="toggleHoliday(${parseInt(h.id)})" title="Ativar/Desativar"><i class="bi bi-toggle-on"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="confirmDeleteHoliday(${parseInt(h.id)}, ${JSON.stringify(h.name)})" title="Excluir"><i class="bi bi-trash-fill"></i></button>
                </td>
            </tr>`;
        });
        $('#holidaysBody').html(html || '<tr><td colspan="6" class="text-center text-muted">Nenhum feriado cadastrado</td></tr>');
    });
}

function openHolidayModal() { $('#holidayForm')[0].reset(); $('#holiday_id').val(''); new bootstrap.Modal('#holidayModal').show(); }

function editHoliday(id) {
    $.get(SettingsEndpoints.holidays, function(r) {
        const h = (r.data||[]).find(x=>x.id==id);
        if(h) { $('#holiday_id').val(h.id); $('#holiday_name').val(h.name); $('#holiday_date').val((h.date||'').substring(0,10)); $('#holiday_type').val(h.type); $('#holiday_description').val(h.description||''); $('#holiday_recurring').prop('checked',h.recurring==1); $('#holiday_blocks_punch').prop('checked',h.blocks_punch==1); new bootstrap.Modal('#holidayModal').show(); }
    });
}

function saveHoliday() {
    const id = $('#holiday_id').val();
    const url = id ? `${SettingsEndpoints.holidays}/${id}/update` : SettingsEndpoints.holidaysStore;
    $.post(url, $('#holidayForm').serialize(), function(r) {
        if(r.success) {
            bootstrap.Modal.getInstance('#holidayModal').hide();
            loadHolidays();
            Toast.success(r.message || 'Feriado salvo com sucesso!');
        } else {
            Toast.error(r.message || 'Erro ao salvar feriado');
        }
    });
}

function toggleHoliday(id) {
    $.post(`${SettingsEndpoints.holidays}/${id}/toggle`, function() {
        loadHolidays();
        Toast.info('Status do feriado alterado');
    });
}

function confirmDeleteHoliday(id, name) {
    Confirm.show({
        title: 'Excluir Feriado',
        message: `Tem certeza que deseja excluir o feriado "${name}"? Esta ação não pode ser desfeita.`,
        icon: 'danger',
        buttonText: 'Excluir',
        onConfirm: () => {
            $.post(`${SettingsEndpoints.holidays}/${id}/delete`, function() {
                loadHolidays();
                Toast.success('Feriado excluído com sucesso!');
            });
        }
    });
}

function toggleEntity(type, id) {
    $.post(`${spSettingsEntityBase(type)}/${id}/toggle`, function() {
        Toast.info('Status alterado');
        location.reload();
    });
}

function testSmtp() {
    Toast.info('Testando conexão SMTP...');
    $.post(SettingsEndpoints.smtpTest, function(r) {
        if(r.success) {
            Toast.success(r.message || 'Conexão SMTP bem-sucedida!');
        } else {
            Toast.error(r.message || 'Falha na conexão SMTP');
        }
    }).fail(function() {
        Toast.error('Erro ao testar conexão SMTP');
    });
}

// Auto-load holidays tab
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('btn-tab-holidays');
    if (btn) {
        btn.addEventListener('shown.bs.tab', loadHolidays);
        if (window.location.hash === '#tab-holidays') {
            setTimeout(function() { new bootstrap.Tab(btn).show(); }, 50);
        }
    }
});
</script>
