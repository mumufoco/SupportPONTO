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
/* ── Nav strip ───────────────────────────────────────────────────────── */
.sp-form-nav {
    display: flex; gap: .5rem; margin-bottom: 1.5rem;
    position: sticky; top: 64px; z-index: 10;
    background: var(--sp-bg-page); padding: .5rem 0; border-bottom: 1px solid var(--sp-border);
}
.sp-form-nav a {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .35rem .85rem; border-radius: 999px; font-size: .82rem; font-weight: 600;
    color: var(--sp-text-muted); text-decoration: none;
    border: 1px solid transparent; transition: all .15s;
}
.sp-form-nav a:hover { background: var(--sp-primary-soft); color: var(--sp-primary); border-color: var(--sp-primary-soft) }
/* ── Form actions ────────────────────────────────────────────────────── */
.sp-form-actions {
    display: flex; justify-content: flex-end; align-items: center; gap: .75rem;
    padding: 1.25rem 1.5rem;
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
</style>

<div class="container-fluid sp-employee-shell sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => esc('Novo colaborador'),
        'subtitle' => 'Preencha os dados abaixo para cadastrar um novo colaborador.',
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

    <!-- ── Section nav ──────────────────────────────────────────────── -->
    <nav class="sp-form-nav">
        <a href="#sec-pessoal"><i class="bi bi-person-vcard-fill"></i>Dados pessoais</a>
        <a href="#sec-profissional"><i class="bi bi-briefcase-fill"></i>Dados profissionais</a>
        <a href="#sec-acesso"><i class="bi bi-sliders"></i>Acesso e operação</a>
    </nav>

    <form action="<?= site_url('employees') ?>" method="post" class="sp-employee-shell" id="spEmployeeForm">
        <?= csrf_field() ?>

        <!-- ── 1. Dados pessoais ─────────────────────────────────────── -->
        <div class="sp-form-card" id="sec-pessoal">
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
        </div>

        <!-- ── 2. Dados profissionais ────────────────────────────────── -->
        <div class="sp-form-card" id="sec-profissional">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-green"><i class="bi bi-briefcase-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Dados profissionais</p>
                    <p class="sp-form-card__sub">Vínculo, unidade, departamento, cargo, jornada e dados contratuais.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <?= $this->include('employees/partials/_professional_data') ?>
            </div>
        </div>

        <!-- ── 3. Acesso e operação ──────────────────────────────────── -->
        <div class="sp-form-card" id="sec-acesso">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-purple"><i class="bi bi-sliders"></i></div>
                <div>
                    <p class="sp-form-card__title">Documentos, acesso e operação</p>
                    <p class="sp-form-card__sub">CTPS/PIS, dados bancários, senha inicial e parâmetros de ativação.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <?= $this->include('employees/partials/_operational_settings') ?>
            </div>
        </div>

        <!-- ── Actions ──────────────────────────────────────────────── -->
        <div class="sp-form-actions">
            <a href="<?= site_url('employees') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-save-fill me-1"></i> Cadastrar colaborador
            </button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('employees/partials/create_scripts') ?>
<?= $this->endSection() ?>
