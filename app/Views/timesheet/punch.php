<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Registrar Ponto<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Registrar Ponto',
        'subtitle' => 'Escolha o tipo de marcação e o método de identificação.',
        'icon'     => 'bi bi-fingerprint',
        'actions'  => !empty($canAccessTerminal)
            ? [['label' => 'Terminal', 'icon' => 'bi bi-display-fill', 'url' => sp_timesheet_punch_kiosk_url()]]
            : [],
    ]) ?>


    <?php if (session()->getFlashdata('error')): ?>
        <div class="sp-status-banner error">
            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Erro</strong>
            <div><?= esc(session()->getFlashdata('error')) ?></div>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="sp-status-banner success">
            <strong><i class="bi bi-check-circle-fill me-2"></i>Sucesso</strong>
            <div><?= esc(session()->getFlashdata('success')) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($todayHoliday)): ?>
        <?php $blocksPunch = !empty($todayHoliday->blocks_punch); ?>
        <div class="alert <?= $blocksPunch ? 'alert-danger' : 'alert-warning' ?> d-flex align-items-start gap-3 mb-3" role="alert" id="sp-holiday-banner">
            <i class="bi <?= $blocksPunch ? 'bi-calendar-x-fill' : 'bi-calendar-event-fill' ?> fs-4 flex-shrink-0 mt-1"></i>
            <div class="flex-grow-1">
                <strong><?= $blocksPunch ? 'Ponto bloqueado — ' : 'Atenção — ' ?><?= esc($todayHoliday->name) ?></strong>
                <div class="small mt-1">
                    <?= esc(\App\Models\HolidayModel::typeLabel($todayHoliday->type ?? '')) ?>
                    <?php if (!empty($todayHoliday->recurring)): ?> &bull; Recorrente<?php endif; ?>
                    <?php if (!empty($todayHoliday->description)): ?> &bull; <?= esc($todayHoliday->description) ?><?php endif; ?>
                </div>
                <?php if ($blocksPunch && !empty($canOverride)): ?>
                    <div class="mt-2">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="sp-holiday-override" value="1">
                            <label class="form-check-label fw-semibold" for="sp-holiday-override">
                                Autorizar ponto mesmo assim (privilégio de gestor/admin)
                            </label>
                        </div>
                    </div>
                <?php elseif ($blocksPunch): ?>
                    <div class="small text-danger mt-1 fw-semibold">
                        <i class="bi bi-lock-fill me-1"></i>Registro de ponto não permitido. Contate o administrador.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ETAPA 1: Tipo de marcação -->
    <div class="sp-punch-stage">
        <div class="sp-punch-stage__header">
            <h2 class="sp-profile-card__title">
                <i class="bi bi-ui-checks-grid"></i> 1. Tipo de marcação
            </h2>
        </div>
        <div class="sp-punch-stage__body">
            <div class="sp-punch-type-grid">
                <button type="button" class="btn sp-punch-type-btn is-entry" data-punch-type="entrada">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Entrada</span>
                </button>
                <button type="button" class="btn sp-punch-type-btn is-break-start" data-punch-type="intervalo_inicio">
                    <i class="bi bi-cup-hot-fill"></i>
                    <span>Início do Intervalo</span>
                </button>
                <button type="button" class="btn sp-punch-type-btn is-break-end" data-punch-type="intervalo_fim">
                    <i class="bi bi-play-circle-fill"></i>
                    <span>Fim do Intervalo</span>
                </button>
                <button type="button" class="btn sp-punch-type-btn is-exit" data-punch-type="saida">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Saída</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ETAPA 2: Método de identificação -->
    <div class="sp-punch-stage" id="punchMethodSelection">
        <div class="sp-punch-stage__header">
            <h2 class="sp-profile-card__title">
                <i class="bi bi-person-badge-fill"></i> 2. Método de identificação
            </h2>
        </div>
        <div class="sp-punch-stage__body">
            <div class="sp-choice-method-grid">
                <?php
                    $methodOrder = ['codigo', 'cpf', 'qrcode', 'facial', 'biometria'];
                    $buttonMethodMap = ['qrcode' => 'qr', 'facial' => 'face', 'biometria' => 'fingerprint'];
                ?>
                <?php foreach ($methodOrder as $key): ?>
                <?php if (!isset($methodReadiness[$key])) continue; ?>
                <?php $method = $methodReadiness[$key]; $buttonMethod = $buttonMethodMap[$key] ?? $key; ?>
                    <button
                        type="button"
                        class="sp-choice-method-card<?= !($method['enabled'] ?? false) ? ' opacity-50' : '' ?>"
                        data-method="<?= sp_attr($buttonMethod) ?>"
                        <?= !($method['enabled'] ?? false) ? 'disabled aria-disabled="true"' : '' ?>
                        title="<?= esc(($method['enabled'] ?? false) ? $method['description'] : 'Método desabilitado nas configurações.') ?>"
                    >
                        <div class="method-icon"><i class="<?= esc($method['icon']) ?>"></i></div>
                        <strong><?= esc($method['label']) ?></strong>
                        <span><?= esc($method['enabled'] ? $method['description'] : 'Desabilitado') ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ETAPA 3: Executar marcação -->
    <div class="sp-punch-stage" id="punchOperationStage">
        <div class="sp-punch-stage__header">
            <h2 class="sp-profile-card__title">
                <i class="bi bi-person-check-fill"></i> 3. Executar marcação
            </h2>
        </div>
        <div class="sp-punch-stage__body">
            <div class="alert alert-secondary small mb-3" id="punchSelectionSummary">
                Selecione o tipo de marcação e o método de identificação para iniciar.
            </div>

            <div id="punchCapabilityAlert"></div>

            <form id="form-codigo" class="row g-3 sp-method-panel d-none">
                <?= csrf_field() ?>
                <div class="col-lg-8">
                    <label class="form-label" for="punch_unique_code">Código do colaborador</label>
                    <input class="form-control" id="punch_unique_code" name="unique_code" placeholder="Digite o código único" autocomplete="off" required>
                </div>
                <div class="col-lg-4 d-flex align-items-end">
                    <button class="btn btn-success w-100" type="submit">Registrar por código</button>
                </div>
            </form>

            <form id="form-cpf" class="row g-3 sp-method-panel d-none">
                <?= csrf_field() ?>
                <div class="col-lg-8">
                    <label class="form-label" for="punch_cpf">CPF do colaborador</label>
                    <input class="form-control" id="punch_cpf" name="cpf" placeholder="000.000.000-00" inputmode="numeric" autocomplete="off" required>
                </div>
                <div class="col-lg-4 d-flex align-items-end">
                    <button class="btn btn-success w-100" type="submit">Registrar por CPF</button>
                </div>
            </form>

            <div id="form-qr" class="sp-method-panel d-none">
                <p class="text-muted small mb-2">Use a câmera para escanear o QR Code do colaborador.</p>
                <div id="punch-qr-reader" class="sp-quick-reader"></div>
            </div>

            <div id="form-face" class="sp-method-panel d-none">
                <div class="d-flex flex-column align-items-center">
                    <div style="position:relative;display:inline-block;width:100%;max-width:480px">
                        <video id="punchFaceVideo" autoplay playsinline class="sp-quick-video" style="width:100%;border-radius:.375rem;display:block"></video>
                        <svg style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:.375rem">
                            <defs><mask id="pfMask"><rect width="100%" height="100%" fill="white"/><ellipse cx="50%" cy="44%" rx="27%" ry="37%" fill="black"/></mask></defs>
                            <rect width="100%" height="100%" fill="rgba(0,0,0,0.38)" mask="url(#pfMask)"/>
                            <ellipse cx="50%" cy="44%" rx="27%" ry="37%" fill="none" stroke="#22c55e" stroke-width="2.5"/>
                        </svg>
                        <div style="position:absolute;bottom:14%;left:0;right:0;text-align:center;color:#fff;font-size:.75rem;pointer-events:none;text-shadow:0 1px 3px rgba(0,0,0,.9)">
                            <i class="bi bi-person-bounding-box me-1"></i>Posicione seu rosto no oval
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-success" id="captureFacePunch">Capturar e registrar</button>
                    </div>
                </div>
            </div>

            <div id="form-fingerprint" class="sp-method-panel d-none">
                <div id="punchFingerprintMessage" class="alert alert-secondary d-flex align-items-center gap-2">
                    <div class="spinner-border spinner-border-sm flex-shrink-0" role="status"></div>
                    <span>Aguardando selecao do metodo biometrico...</span>
                </div>
            </div>

            <div id="punchResult" class="mt-3" aria-live="polite"></div>
        </div>
    </div>

    <!-- Métodos disponíveis — colapsável -->
    <?php if (!empty($methodReadiness)): ?>
    <div class="sp-callout-neutral mt-2">
        <details>
            <summary class="fw-semibold small" style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:.5rem">
                <i class="bi bi-info-circle"></i> Métodos disponíveis e suas condições
            </summary>
            <div class="mt-2">
                <ul class="mb-0 small ps-3">
                    <?php foreach ($methodReadiness as $method): ?>
                        <li class="mb-1">
                            <strong><?= esc($method['label']) ?></strong>
                            <?php if (!($method['enabled'] ?? false)): ?>
                                <span class="text-danger"> — Desabilitado</span>
                            <?php else: ?>
                                — <?= esc($method['description']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
    </div>
    <?php endif; ?>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<?= $this->include('timesheet/partials/punch_scripts') ?>

<?php
// Pass holiday blocking config to JS
$blocksPunch = !empty($todayHoliday) && !empty($todayHoliday->blocks_punch);
$canOverride  = !empty($canOverride);
?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    const blocksPunch = <?= $blocksPunch ? 'true' : 'false' ?>;
    const canOverride = <?= $canOverride ? 'true' : 'false' ?>;

    if (!blocksPunch) return; // nothing to do on regular days

    function togglePunchForms(enabled) {
        // Disable / enable all punch-type buttons
        document.querySelectorAll('.sp-punch-type-btn, .sp-method-btn, #form-codigo button[type="submit"], #form-cpf button[type="submit"], #captureFacePunch, #submitFingerprintPunch').forEach(el => {
            el.disabled = !enabled;
            el.style.opacity = enabled ? '' : '0.45';
        });
    }

    if (!canOverride) {
        // Non-manager: keep forms permanently disabled
        document.addEventListener('DOMContentLoaded', () => togglePunchForms(false));
        return;
    }

    // Manager: toggle forms based on override checkbox
    document.addEventListener('DOMContentLoaded', () => {
        togglePunchForms(false); // start locked
        const cb = document.getElementById('sp-holiday-override');
        if (cb) {
            cb.addEventListener('change', () => togglePunchForms(cb.checked));
        }
    });
})();
</script>
<?= $this->endSection() ?>
