<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Novo colaborador<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/* ── Section cards ───────────────────────────────────────────────────── */
.sp-form-card {
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
    padding: 0;
}
.sp-form-card__head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--sp-border);
    background: var(--sp-gray-50, rgba(0,0,0,.02));
}
.sp-form-card__icon {
    width: 2.1rem; height: 2.1rem;
    border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.sp-form-card__icon.c-blue   { background: rgba(13,110,253,.12);  color: #0d6efd }
.sp-form-card__icon.c-green  { background: rgba(25,135, 84,.12);  color: #198754 }
.sp-form-card__icon.c-purple { background: rgba(111, 66,193,.12); color: #6f42c1 }
.sp-form-card__title { font-weight: 700; font-size: 1rem; margin: 0 }
.sp-form-card__sub   { font-size: .78rem; color: var(--sp-text-muted); margin: 0 }
.sp-form-card__body  { padding: 1.5rem }
/* ── Sub-section divider ─────────────────────────────────────────────── */
.sp-form-divider {
    display: flex; align-items: center; gap: .75rem;
    margin: 1.5rem 0 1rem;
    font-size: .75rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: .06em; color: var(--sp-text-muted);
}
.sp-form-divider::before, .sp-form-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--sp-border);
}
.sp-form-divider::before { flex: 0 0 0 }
/* ── Wizard nav ──────────────────────────────────────────────────────── */
.sp-wizard-nav {
    display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap;
    position: sticky; top: 64px; z-index: 10;
    background: var(--sp-bg-page); padding: .5rem 0; border-bottom: 1px solid var(--sp-border);
}
.sp-wizard-pill {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .4rem .9rem; border-radius: 999px; font-size: .82rem; font-weight: 600;
    color: var(--sp-text-muted); background: transparent;
    border: 1px solid var(--sp-border); transition: all .15s; cursor: pointer;
}
.sp-wizard-pill.is-active { background: var(--sp-primary); color: #fff; border-color: var(--sp-primary) }
.sp-wizard-pill.is-done { background: var(--sp-primary-soft); color: var(--sp-primary); border-color: var(--sp-primary-soft) }
.sp-wizard-pill.is-locked { opacity: .55; cursor: not-allowed }
.sp-wizard-pill:disabled { cursor: default }
/* ── Wizard step actions ─────────────────────────────────────────────── */
.sp-wizard-actions {
    display: flex; justify-content: flex-end; align-items: center; gap: .75rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--sp-border);
}
fieldset.sp-wizard-step { border: 0; margin: 0; min-width: 0; }
</style>

<div class="container-fluid sp-employee-shell sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => esc('Novo colaborador'),
        'subtitle' => 'Preencha os dados abaixo, em etapas, para cadastrar um novo colaborador.',
        'icon'     => 'bi bi-person-plus-fill',
        'actions'  => [
            ['label' => 'Voltar para listagem', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('employees')],
        ],
    ]) ?>

    <?php if (!empty($formOptions['warnings'] ?? [])): ?>
        <div class="sp-callout-warning mb-3">
            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Atenção</strong>
            <ul class="mb-0 mt-2">
                <?php foreach (($formOptions['warnings'] ?? []) as $warning): ?>
                    <li><?= esc($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ── Navegação do wizard ──────────────────────────────────────── -->
    <nav class="sp-wizard-nav" id="wizardNav">
        <button type="button" class="sp-wizard-pill is-active" data-step-target="1" id="pillStep1">
            <i class="bi bi-person-vcard-fill"></i> 1. Dados Pessoais
        </button>
        <button type="button" class="sp-wizard-pill" data-step-target="2" id="pillStep2" disabled>
            <i class="bi bi-briefcase-fill"></i> 2. Dados Profissionais
        </button>
        <button type="button" class="sp-wizard-pill" data-step-target="3" id="pillStep3" disabled>
            <i class="bi bi-journal-bookmark-fill"></i> 3. Documentação Geral
        </button>
        <button type="button" class="sp-wizard-pill is-locked" disabled title="Disponível após salvar o colaborador">
            <i class="bi bi-lock-fill"></i> 4. Upload de Documentos
        </button>
    </nav>

    <form action="<?= site_url('employees') ?>" method="post" class="sp-employee-shell" id="spEmployeeForm">
        <?= csrf_field() ?>

        <!-- ── 1. Dados pessoais ─────────────────────────────────────── -->
        <fieldset class="sp-form-card sp-wizard-step" data-step="1" id="wizardStep1">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-blue"><i class="bi bi-person-vcard-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Dados pessoais e identificação</p>
                    <p class="sp-form-card__sub">Dados para login, LGPD, identificação trabalhista e cadastro obrigatório.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <?= $this->include('employees/partials/_personal_data') ?>
            </div>
            <div class="sp-wizard-actions">
                <a href="<?= site_url('employees') ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="button" class="btn btn-primary" data-wizard-next="2">
                    Avançar <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </fieldset>

        <!-- ── 2. Dados profissionais ────────────────────────────────── -->
        <fieldset class="sp-form-card sp-wizard-step" data-step="2" id="wizardStep2" style="display:none" disabled>
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-green"><i class="bi bi-briefcase-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Dados profissionais</p>
                    <p class="sp-form-card__sub">Unidade, departamento, cargo, tipo de contrato, admissão e perfil de acesso.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <?= $this->include('employees/partials/_professional_data') ?>
                <?= $this->include('employees/partials/_operational_settings') ?>
            </div>
            <div class="sp-wizard-actions">
                <button type="button" class="btn btn-outline-secondary" data-wizard-back="1">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </button>
                <button type="button" class="btn btn-primary" data-wizard-next="3">
                    Avançar <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </div>
        </fieldset>

        <!-- ── 3. Documentação geral ─────────────────────────────────── -->
        <fieldset class="sp-form-card sp-wizard-step" data-step="3" id="wizardStep3" style="display:none" disabled>
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-purple"><i class="bi bi-journal-bookmark-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Documentação geral</p>
                    <p class="sp-form-card__sub">Título de eleitor, CNH, CTPS Digital e demais dados gerais.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <?= $this->include('employees/partials/_documentation_data') ?>
            </div>
            <div class="sp-wizard-actions">
                <button type="button" class="btn btn-outline-secondary" data-wizard-back="2">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </button>
                <button type="submit" class="btn btn-primary btn-lg px-4">
                    <i class="bi bi-save-fill me-1"></i> Cadastrar colaborador
                </button>
            </div>
        </fieldset>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('employees/partials/create_scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    var form = document.getElementById('spEmployeeForm');
    if (!form) return;

    var steps = Array.prototype.slice.call(form.querySelectorAll('.sp-wizard-step'));
    var pills = {
        1: document.getElementById('pillStep1'),
        2: document.getElementById('pillStep2'),
        3: document.getElementById('pillStep3'),
    };

    function stepEl(n) {
        return document.getElementById('wizardStep' + n);
    }

    function showStep(n) {
        steps.forEach(function (el) {
            el.style.display = (String(el.dataset.step) === String(n)) ? '' : 'none';
        });
        Object.keys(pills).forEach(function (key) {
            var pill = pills[key];
            if (!pill) return;
            pill.classList.toggle('is-active', Number(key) === n);
            if (Number(key) < n) pill.classList.add('is-done');
        });
        var target = stepEl(n);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Avançar: valida só o fieldset atual (reportValidity ignora o que está
    // desabilitado, então CNH/etc. condicionais não bloqueiam quando ocultos).
    form.querySelectorAll('[data-wizard-next]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var current = btn.closest('.sp-wizard-step');
            if (current && !current.reportValidity()) return;

            var next = Number(btn.dataset.wizardNext);
            var nextFieldset = stepEl(next);
            if (nextFieldset) nextFieldset.disabled = false;
            var nextPill = pills[next];
            if (nextPill) nextPill.disabled = false;

            showStep(next);
        });
    });

    // Voltar: só troca a etapa visível, sem re-validar nem desabilitar nada
    // (os campos já habilitados continuam valendo para o submit final).
    form.querySelectorAll('[data-wizard-back]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(Number(btn.dataset.wizardBack));
        });
    });

    // Clique direto no pill (só funciona para etapas já alcançadas -- os
    // pills de etapas futuras ficam com o atributo disabled).
    Object.keys(pills).forEach(function (key) {
        var pill = pills[key];
        if (!pill) return;
        pill.addEventListener('click', function () {
            if (pill.disabled) return;
            showStep(Number(key));
        });
    });
})();
</script>
<?= $this->endSection() ?>
