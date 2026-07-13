<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Templates de Termos de Consentimento<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$consentTypes = $consentTypes ?? [];
$activeType   = $activeType   ?? 'biometric_face';
$allTerms     = $allTerms     ?? [];
$activeTerm   = $activeTerm   ?? null;
$allVersions  = $allVersions  ?? [];

$typeIcons = [
    'biometric_face'        => ['bi-fingerprint',        'primary',  'Biometria Facial',     'Gate: cadastro facial do colaborador'],
    'biometric_fingerprint' => ['bi-fingerprint',        'info',     'Biometria Digital',    'Gate: cadastro de digital do colaborador'],
    'geolocation'           => ['bi-geo-alt-fill',       'success',  'Geolocalização',       'Gate: primeiro uso de ponto GPS'],
    'data_processing'       => ['bi-person-fill-lock',   'secondary','Dados Pessoais',        'Gate: primeiro acesso ao sistema'],
    'marketing'             => ['bi-megaphone-fill',     'warning',  'Marketing',             'Auto-serviço: página LGPD do colaborador'],
    'data_sharing'          => ['bi-share-fill',         'dark',     'Compartilhamento',      'Auto-serviço: página LGPD do colaborador'],
];
$integrationMap = [
    'biometric_face'        => ['url' => 'biometric/enroll-for/{id}',      'status' => 'integrated',    'label' => 'Integrado — gate ativo'],
    'biometric_fingerprint' => ['url' => 'biometric/fingerprint/enroll/{id}', 'status' => 'integrated', 'label' => 'Integrado — gate ativo'],
    'geolocation'           => ['url' => 'lgpd/consents',                   'status' => 'selfservice',   'label' => 'Auto-serviço LGPD'],
    'data_processing'       => ['url' => 'lgpd/consents',                   'status' => 'selfservice',   'label' => 'Auto-serviço LGPD'],
    'marketing'             => ['url' => 'lgpd/consents',                   'status' => 'selfservice',   'label' => 'Auto-serviço LGPD'],
    'data_sharing'          => ['url' => 'lgpd/consents',                   'status' => 'selfservice',   'label' => 'Auto-serviço LGPD'],
];
$statusClass = ['integrated' => 'bg-success', 'selfservice' => 'bg-info text-dark'];
?>

<div class="container-fluid">
    <?= view('components/page_header', [
        'title'    => 'Templates de Termos de Consentimento',
        'subtitle' => 'Gerencie e versione os termos LGPD de cada tipo. Ao publicar nova versão, os colaboradores são solicitados a aceitar novamente.',
        'icon'     => 'bi bi-file-earmark-text-fill',
        'actions'  => [
                    ],
    ]) ?>


    <!-- Visão geral dos 6 tipos -->
    <div class="row g-3 mb-4">
        <?php foreach ($consentTypes as $type => $label): ?>
        <?php
            [$icon, $color, , $desc] = $typeIcons[$type] ?? ['bi-file-text', 'secondary', $label, ''];
            $integration = $integrationMap[$type] ?? [];
            $hasTerm = !empty($allTerms[$type]['active']);
        ?>
        <div class="col-sm-6 col-xl-4">
            <a href="?type=<?= $type ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $activeType === $type ? 'border-primary border-2 border' : '' ?>">
                    <div class="card-body d-flex gap-3 align-items-start py-3">
                        <div class="rounded-circle bg-<?= $color ?> bg-opacity-10 p-2 flex-shrink-0">
                            <i class="bi <?= $icon ?> text-<?= $color ?> fs-5"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold"><?= esc($label) ?></div>
                            <div class="small text-muted"><?= esc($desc) ?></div>
                            <div class="mt-1">
                                <?php if ($hasTerm): ?>
                                    <span class="badge bg-success rounded-pill" style="font-size:.65rem;">
                                        <i class="bi bi-check me-1"></i>v<?= esc($allTerms[$type]['active']->version) ?> ativo
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark rounded-pill" style="font-size:.65rem;">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Sem termo
                                    </span>
                                <?php endif; ?>
                                <span class="badge <?= $statusClass[$integration['status'] ?? 'selfservice'] ?> rounded-pill ms-1" style="font-size:.65rem;">
                                    <?= esc($integration['label'] ?? '') ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($activeType === $type): ?>
                        <i class="bi bi-chevron-right text-primary fs-5 flex-shrink-0 mt-1"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Editor do tipo selecionado -->
    <?php
        [$icon, $color, $typeLabel] = $typeIcons[$activeType] ?? ['bi-file-text', 'secondary', $consentTypes[$activeType] ?? $activeType];
        $integration = $integrationMap[$activeType] ?? [];
    ?>
    <div class="row g-4">
        <!-- Termo ativo -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-<?= $color ?> text-white d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi <?= $icon ?> me-2"></i>
                        <strong><?= esc($typeLabel) ?></strong> — Termo Ativo
                        <?php if ($activeTerm): ?>
                            <span class="badge bg-white text-<?= $color ?> ms-2">v<?= esc($activeTerm->version) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-white bg-opacity-25 small">
                        <?= esc($integration['label'] ?? '') ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($activeTerm): ?>
                        <h6 class="fw-bold mb-2"><?= esc($activeTerm->title) ?></h6>
                        <?php if ($activeTerm->legal_basis): ?>
                        <div class="alert alert-info small py-2 mb-3">
                            <i class="bi bi-book me-1"></i><?= esc($activeTerm->legal_basis) ?>
                        </div>
                        <?php endif; ?>
                        <p class="small text-muted mb-2">
                            Publicado em: <?= $activeTerm->created_at ? date('d/m/Y H:i', strtotime($activeTerm->created_at)) : '-' ?>
                        </p>
                        <div class="border rounded p-3 bg-light" style="max-height:340px;overflow-y:auto;font-size:.8rem;white-space:pre-wrap;font-family:inherit;">
                            <?= esc($activeTerm->body) ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-file-earmark-x fs-1 d-block mb-3 opacity-50"></i>
                            <p class="fw-semibold mb-1">Nenhum termo publicado para este tipo</p>
                            <p class="small">Use o formulário ao lado para publicar o primeiro termo.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Histórico de versões -->
                <?php if (!empty($allVersions)): ?>
                <div class="card-footer bg-transparent">
                    <div class="small text-muted fw-semibold mb-2">Histórico de versões</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($allVersions as $v): ?>
                        <span class="badge <?= $v->active ? 'bg-success' : 'bg-light text-muted border' ?>">
                            v<?= esc($v->version) ?>
                            <?php if ($v->active): ?><i class="bi bi-check ms-1"></i><?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulário nova versão -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-plus-circle-fill me-2"></i>
                    Publicar Nova Versão —
                    <?= esc($typeLabel) ?>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Ao publicar, o termo atual será desativado. Colaboradores sem aceite na nova versão serão solicitados novamente nos pontos de integração.
                    </div>
                    <form action="<?= site_url('settings/consent-terms/save') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="term_type" value="<?= esc($activeType) ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Título do Termo</label>
                            <input type="text" name="title" class="form-control form-control-sm" required maxlength="255"
                                   value="<?= esc($activeTerm->title ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Base Legal</label>
                            <input type="text" name="legal_basis" class="form-control form-control-sm" maxlength="500"
                                   value="<?= esc($activeTerm->legal_basis ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Texto Íntegro do Termo</label>
                            <textarea name="body" class="form-control form-control-sm" rows="14" required
                                      style="font-size:.8rem;font-family:inherit;"><?= esc($activeTerm->body ?? '') ?></textarea>
                            <div class="form-text">Este texto será exibido ao colaborador no momento do aceite e gravado no registro.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"
                                onclick="return confirm('Confirma a publicação de nova versão? O termo atual será desativado.')">
                            <i class="bi bi-cloud-upload-fill me-1"></i>Publicar Nova Versão
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapa de integração -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-transparent">
            <h6 class="fw-semibold mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Mapa de Integração dos Termos</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tipo</th>
                        <th>Versão Ativa</th>
                        <th>Ponto de Integração</th>
                        <th>Modo</th>
                        <th class="pe-3">URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consentTypes as $type => $label): ?>
                    <?php
                        [$tIcon, $tColor] = $typeIcons[$type] ?? ['bi-file-text', 'secondary'];
                        $integration = $integrationMap[$type] ?? [];
                        $hasTerm = !empty($allTerms[$type]['active']);
                        $version = $hasTerm ? 'v' . $allTerms[$type]['active']->version : null;
                    ?>
                    <tr class="<?= $activeType === $type ? 'table-primary' : '' ?>">
                        <td class="ps-3">
                            <i class="bi <?= $tIcon ?> text-<?= $tColor ?> me-2"></i>
                            <a href="?type=<?= $type ?>" class="text-decoration-none fw-semibold"><?= esc($label) ?></a>
                        </td>
                        <td>
                            <?php if ($version): ?>
                                <span class="badge bg-success rounded-pill"><?= esc($version) ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark rounded-pill">Sem termo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= esc($typeIcons[$type][3] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $statusClass[$integration['status'] ?? 'selfservice'] ?> rounded-pill">
                                <?= esc($integration['label'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="pe-3 text-muted" style="font-size:.75rem;">
                            <?= esc($integration['url'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
