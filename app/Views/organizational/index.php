<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Organizacional<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Estrutura Organizacional',
        'subtitle' => 'Gerencie unidades, departamentos, cargos, funções e feriados.',
        'icon'     => 'bi bi-diagram-3-fill',
        'actions'  => [
            ['label' => 'Configurações', 'icon' => 'bi bi-gear-fill', 'url' => sp_settings_center_url()],
            ['label' => 'Relatórios',    'icon' => 'bi bi-bar-chart-fill', 'url' => sp_reports_index_url()],
        ],
    ]) ?>

    <!-- Acesso rápido -->
    <div class="sp-shortcuts-grid mb-3">
        <a class="sp-shortcut-card" href="<?= sp_route_url('settings.work-units') ?>">
            <div class="icon"><i class="bi bi-building-fill"></i></div>
            <strong>Unidades</strong>
            <span>Gerencie unidades de trabalho.</span>
        </a>
        <a class="sp-shortcut-card" href="<?= sp_route_url('settings.departments') ?>">
            <div class="icon"><i class="bi bi-collection-fill"></i></div>
            <strong>Departamentos</strong>
            <span>Organize os departamentos.</span>
        </a>
        <a class="sp-shortcut-card" href="<?= sp_route_url('settings.positions') ?>">
            <div class="icon"><i class="bi bi-person-workspace"></i></div>
            <strong>Cargos</strong>
            <span>Defina os cargos do sistema.</span>
        </a>
            <div class="icon"><i class="bi bi-shield-fill-check"></i></div>
            <strong>Funções</strong>
            <span>Controle funções e perfis.</span>
        </a>
        <a class="sp-shortcut-card" href="<?= sp_route_url('settings.vacations') ?>">
            <div class="icon"><i class="bi bi-calendar2-heart-fill"></i></div>
            <strong>Férias</strong>
            <span>Gestão de períodos de férias.</span>
        </a>
    </div>

    <div class="row g-3">

        <!-- Resumo estrutural -->
        <div class="col-md-6">
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-list-columns-reverse"></i> Resumo Estrutural</h2>
                </div>
                <div class="sp-profile-card__body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ([
                            ['Unidades de Trabalho', count($workUnits),  'bi bi-building-fill',      'primary'],
                            ['Departamentos',        count($departments), 'bi bi-collection-fill',    'info'],
                            ['Cargos',               count($positions),   'bi bi-person-workspace',   'warning'],
                            ['Funções',              count($roles),       'bi bi-shield-fill-check',  'success'],
                            ['Feriados',             count($holidays),    'bi bi-calendar2-heart-fill','danger'],
                        ] as [$label, $count, $icon, $color]): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center"
                                style="background:transparent;border-color:var(--sp-border)">
                                <span><i class="<?= $icon ?> me-2 text-<?= $color ?>"></i><?= $label ?></span>
                                <span class="sp-badge sp-badge-neutral"><?= $count ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="col-md-6">
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-plug-fill"></i> Integrações</h2>
                </div>
                <div class="sp-profile-card__body d-flex flex-column gap-3">
                    <div class="sp-callout-info p-3">
                        <strong class="d-block mb-1">Permissões por Perfil</strong>
                        <p class="text-muted small mb-2">Controle de acessos e alçadas por função de usuário.</p>
                            Gerenciar permissões
                        </a>
                    </div>
                    <div class="sp-callout-neutral p-3">
                        <strong class="d-block mb-1">Vínculo com Colaboradores</strong>
                        <p class="text-muted small mb-2">Conecte a estrutura organizacional ao cadastro de funcionários.</p>
                        <a href="<?= site_url('employees') ?>" class="btn btn-sm btn-outline-secondary">
                            Validar em Funcionários
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
<?= $this->endSection() ?>
