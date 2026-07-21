<?php
/** @var object $employee */
/** @var array $documentsByType */
$empId = (int) ($employee->id ?? 0);
$hasPhoto = !empty($employee->photo_path ?? null);
$photoUrl = $hasPhoto ? site_url('employees/' . $empId . '/photo') : null;
$uploadUrl = site_url('employees/' . $empId . '/photo');
$documentTypes = $documentTypes ?? [];
?>
<div class="sp-form-card">
    <div class="sp-form-card__head">
        <div class="sp-form-card__icon c-blue"><i class="bi bi-person-badge-fill"></i></div>
        <div>
            <p class="sp-form-card__title">Foto do colaborador</p>
            <p class="sp-form-card__sub">Usada no perfil, na listagem, em relatórios e na auditoria — fonte única.</p>
        </div>
    </div>
    <div class="sp-form-card__body">
        <div class="d-flex flex-column flex-md-row align-items-center gap-4">
            <div class="text-center" style="width:120px;flex-shrink:0">
                <div class="sp-emp-avatar-wrap" style="width:96px;height:96px;margin:0 auto">
                    <?php if ($photoUrl): ?>
                        <img src="<?= esc($photoUrl) ?>" alt="Foto de <?= esc($employee->name ?? '') ?>" id="empAvatarImg"
                             style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--sp-border,#e5e7eb)">
                    <?php else: ?>
                        <div class="sp-emp-avatar-initials" style="width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;background:var(--sp-primary-soft);color:var(--sp-primary)">
                            <?= esc(mb_substr($employee->name ?? 'C', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex-grow-1 w-100">
                <div class="text-center mb-2" id="photoPreviewWrap" style="display:none">
                    <img id="photoPreviewImg" src="" alt="Preview" class="rounded shadow-sm img-fluid" style="max-height:160px;object-fit:cover">
                </div>

                <ul class="nav nav-pills nav-fill mb-3 gap-1" id="photoTabNav">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" id="tabCameraBtn" onclick="switchPhotoTab('camera')">
                            <i class="bi bi-camera-fill me-1"></i>Câmera
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" id="tabFileBtn" onclick="switchPhotoTab('file')">
                            <i class="bi bi-upload me-1"></i>Arquivo
                        </button>
                    </li>
                </ul>

                <div id="tabCamera">
                    <div class="ratio ratio-4x3 rounded overflow-hidden bg-dark mb-2" id="cameraBox" style="max-height:220px">
                        <video id="photoVideo" autoplay muted playsinline style="object-fit:cover;width:100%;height:100%"></video>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnPhotoCapture">
                            <i class="bi bi-camera me-2"></i>Tirar foto
                        </button>
                        <label class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2" id="labelNativeCamera">
                            <i class="bi bi-phone me-1"></i>Usar câmera do celular
                            <input type="file" accept="image/*" capture="user" id="inputNativeCamera" class="d-none">
                        </label>
                    </div>
                    <canvas id="photoCanvas" class="d-none"></canvas>
                </div>

                <div id="tabFile" style="display:none">
                    <label class="d-block border rounded p-4 text-center text-muted" style="cursor:pointer;border-style:dashed!important" id="dropZone">
                        <i class="bi bi-cloud-upload fs-2 d-block mb-2"></i>
                        <span>Clique para selecionar ou arraste uma imagem aqui</span>
                        <small class="d-block mt-1">JPG, PNG, WEBP — máx. 5 MB</small>
                        <input type="file" accept="image/jpeg,image/png,image/webp" id="inputFilePhoto" class="d-none">
                    </label>
                </div>

                <div id="photoUploadMsg" class="mt-2"></div>

                <div class="d-flex justify-content-end mt-2">
                    <button type="button" class="btn btn-primary d-none" id="btnPhotoSave">
                        <i class="bi bi-check-circle me-1"></i>Salvar foto
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($documentTypes as $type): ?>
    <?php $items = $documentsByType[$type->value] ?? []; ?>
    <div class="sp-form-card">
        <div class="sp-form-card__head">
            <div class="sp-form-card__icon c-purple"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div>
                <p class="sp-form-card__title"><?= esc($type->label()) ?></p>
                <p class="sp-form-card__sub">PDF, JPG, PNG ou WEBP — máx. 10 MB por arquivo.</p>
            </div>
        </div>
        <div class="sp-form-card__body">
            <?php if (!empty($items)): ?>
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($items as $doc): ?>
                                <tr id="doc-row-<?= (int) $doc->id ?>">
                                    <td><i class="bi bi-file-earmark-fill me-2 text-muted"></i><?= esc($doc->original_filename) ?></td>
                                    <td class="text-muted small"><?= esc($doc->file_size ? round($doc->file_size / 1024) . ' KB' : '') ?></td>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <a href="<?= site_url('employees/' . $empId . '/documents/' . (int) $doc->id . '/download') ?>"
                                               class="icon-action" title="Baixar">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <button type="button" class="icon-action icon-action-danger doc-delete-btn"
                                                    data-doc-id="<?= (int) $doc->id ?>" title="Excluir">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <form class="doc-upload-form" data-document-type="<?= esc($type->value) ?>">
                <label class="d-block border rounded p-3 text-center text-muted doc-drop-zone" style="cursor:pointer;border-style:dashed!important">
                    <i class="bi bi-cloud-upload fs-4 d-block mb-1"></i>
                    <span>Clique para selecionar ou arraste arquivos aqui</span>
                    <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple class="d-none doc-file-input">
                </label>
                <div class="doc-upload-msg mt-2"></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/camera-manager.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?>>
/* ── Upload de foto (adaptado de employees/profile.php, sem modal) ── */
(function () {
    var cam = CameraManager.getInstance();
    var photoVideo  = document.getElementById('photoVideo');
    var photoCanvas = document.getElementById('photoCanvas');
    var previewWrap = document.getElementById('photoPreviewWrap');
    var previewImg  = document.getElementById('photoPreviewImg');
    var btnCapture  = document.getElementById('btnPhotoCapture');
    var btnSave     = document.getElementById('btnPhotoSave');
    var msgEl       = document.getElementById('photoUploadMsg');
    var cameraBox   = document.getElementById('cameraBox');
    var pendingDataUrl = null;
    var pendingFile    = null;
    var activeTab   = 'camera';
    var camStarted  = false;

    function escHtml(s) {
        var d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML;
    }
    function showMsg(text, type) {
        type = type || 'info';
        msgEl.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-0">' + text + '</div>';
    }
    function clearMsg() { msgEl.innerHTML = ''; }

    function showPreview(src) {
        previewImg.src = src;
        previewWrap.style.removeProperty('display');
        btnSave.classList.remove('d-none');
    }

    async function startCam() {
        if (camStarted) return;
        try {
            await cam.start(photoVideo, { facingMode: 'user', width: 640, height: 480 });
            camStarted = true;
        } catch (e) {
            cameraBox.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted small p-3 text-center"><div><i class="bi bi-camera-video-off-fill fs-2 d-block mb-2"></i>Câmera não disponível: ' + escHtml(e.message) + '<br><br>Use a opção <strong>Câmera do celular</strong> ou <strong>Arquivo</strong>.</div></div>';
        }
    }

    btnCapture?.addEventListener('click', function () {
        if (!camStarted) { showMsg('Câmera não iniciada.', 'warning'); return; }
        photoCanvas.width  = photoVideo.videoWidth  || 640;
        photoCanvas.height = photoVideo.videoHeight || 480;
        photoCanvas.getContext('2d').drawImage(photoVideo, 0, 0);
        pendingDataUrl = photoCanvas.toDataURL('image/jpeg', 0.88);
        pendingFile    = null;
        cam.stop(); camStarted = false;
        cameraBox.style.display = 'none';
        btnCapture.classList.add('d-none');
        showPreview(pendingDataUrl);
        showMsg('<i class="bi bi-check-circle me-1"></i>Foto capturada! Clique em <strong>Salvar foto</strong>.', 'success');
    });

    var inputNative = document.getElementById('inputNativeCamera');
    inputNative?.addEventListener('change', function () {
        var f = this.files[0];
        if (!f) return;
        if (f.size > 5 * 1024 * 1024) { showMsg('Arquivo muito grande (máx. 5 MB).', 'warning'); return; }
        pendingFile    = f;
        pendingDataUrl = null;
        var url = URL.createObjectURL(f);
        showPreview(url);
        showMsg('<i class="bi bi-check-circle me-1"></i>Foto selecionada! Clique em <strong>Salvar foto</strong>.', 'success');
    });

    var inputFile = document.getElementById('inputFilePhoto');
    var dropZone  = document.getElementById('dropZone');

    function handleFile(f) {
        if (!f) return;
        if (!f.type.match(/^image\//)) { showMsg('Arquivo inválido. Use JPG, PNG ou WEBP.', 'danger'); return; }
        if (f.size > 5 * 1024 * 1024) { showMsg('Arquivo muito grande (máx. 5 MB).', 'warning'); return; }
        pendingFile = f; pendingDataUrl = null;
        var url = URL.createObjectURL(f);
        showPreview(url);
        showMsg('<i class="bi bi-check-circle me-1"></i>Imagem selecionada! Clique em <strong>Salvar foto</strong>.', 'success');
    }

    inputFile?.addEventListener('change', function () { handleFile(this.files[0]); });
    dropZone?.addEventListener('click', function () { inputFile?.click(); });
    dropZone?.addEventListener('dragover', function (e) { e.preventDefault(); this.classList.add('border-primary'); });
    dropZone?.addEventListener('dragleave', function () { this.classList.remove('border-primary'); });
    dropZone?.addEventListener('drop', function (e) {
        e.preventDefault(); this.classList.remove('border-primary');
        handleFile(e.dataTransfer.files[0]);
    });

    window.switchPhotoTab = function (tab) {
        activeTab = tab;
        document.getElementById('tabCameraBtn').classList.toggle('active', tab === 'camera');
        document.getElementById('tabFileBtn').classList.toggle('active', tab === 'file');
        document.getElementById('tabCamera').style.display = tab === 'camera' ? '' : 'none';
        document.getElementById('tabFile').style.display   = tab === 'file'   ? '' : 'none';
        clearMsg();
        if (tab === 'camera') {
            cameraBox.style.removeProperty('display');
            btnCapture.classList.remove('d-none');
            startCam();
        } else {
            cam.stop(); camStarted = false;
        }
    };

    btnSave?.addEventListener('click', async function () {
        if (!pendingDataUrl && !pendingFile) { showMsg('Selecione ou capture uma foto primeiro.', 'warning'); return; }
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
        clearMsg();
        try {
            var formData = new FormData();
            formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            if (pendingFile) {
                formData.append('photo', pendingFile, pendingFile.name);
            } else {
                formData.append('photo_base64', pendingDataUrl);
            }
            var r = await spFetch('<?= $uploadUrl ?>', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
            var data = {}; try { data = await r.json(); } catch (_) {}
            if (data.success) {
                showMsg('<i class="bi bi-check-circle me-2"></i>' + (data.message || 'Foto salva!'), 'success');
                var newUrl = data.data?.photo_url;
                if (newUrl) {
                    var wrap = document.querySelector('.sp-emp-avatar-wrap');
                    if (wrap) {
                        var img = wrap.querySelector('img') || document.createElement('img');
                        img.src = newUrl + '?t=' + Date.now();
                        img.alt = 'Foto'; img.id = 'empAvatarImg';
                        img.style.cssText = 'width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--sp-border,#e5e7eb)';
                        var initials = wrap.querySelector('.sp-emp-avatar-initials');
                        if (initials) initials.replaceWith(img);
                    }
                }
                previewWrap.style.display = 'none';
                btnSave.classList.add('d-none');
            } else {
                showMsg('<i class="bi bi-x-circle me-2"></i>' + escHtml(data.message || 'Erro ao salvar.'), 'danger');
            }
        } catch (e) {
            showMsg('Erro de comunicação: ' + escHtml(e.message), 'danger');
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Salvar foto';
    });

    startCam();
})();

/* ── Upload de documentos por tipo ─────────────────── */
(function () {
    var csrfName = '<?= csrf_token() ?>';

    document.querySelectorAll('.doc-upload-form').forEach(function (form) {
        var input = form.querySelector('.doc-file-input');
        var zone  = form.querySelector('.doc-drop-zone');
        var msg   = form.querySelector('.doc-upload-msg');
        var documentType = form.dataset.documentType;

        function showMsg(text, type) {
            msg.innerHTML = '<div class="alert alert-' + (type || 'info') + ' py-2 mb-0">' + text + '</div>';
        }

        async function upload(files) {
            if (!files || !files.length) return;
            var formData = new FormData();
            formData.append(csrfName, '<?= csrf_hash() ?>');
            formData.append('document_type', documentType);
            for (var i = 0; i < files.length; i++) {
                formData.append('documents[]', files[i]);
            }
            showMsg('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...', 'info');
            try {
                var r = await spFetch('<?= site_url('employees/' . $empId . '/documents') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                var data = {}; try { data = await r.json(); } catch (_) {}
                if (data.success !== false) {
                    showMsg('Documento(s) enviado(s). Recarregue a página para ver a lista atualizada.', 'success');
                } else {
                    showMsg(data.message || 'Erro ao enviar.', 'danger');
                }
            } catch (e) {
                showMsg('Erro de comunicação com o servidor.', 'danger');
            }
        }

        input.addEventListener('change', function () { upload(this.files); });
        zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('border-primary'); });
        zone.addEventListener('dragleave', function () { zone.classList.remove('border-primary'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('border-primary');
            upload(e.dataTransfer.files);
        });
    });

    document.querySelectorAll('.doc-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var docId = this.dataset.docId;
            var row = document.getElementById('doc-row-' + docId);
            this.disabled = true;
            try {
                var formData = new FormData();
                formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
                var r = await spFetch('<?= site_url('employees/' . $empId . '/documents') ?>/' + docId + '/delete', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData,
                });
                var data = {}; try { data = await r.json(); } catch (_) {}
                if (data.success !== false && row) {
                    row.remove();
                } else {
                    this.disabled = false;
                }
            } catch (e) {
                this.disabled = false;
            }
        });
    });
})();
</script>
