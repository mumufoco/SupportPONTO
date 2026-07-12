<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Gerenciar biometria<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Gerenciar face e biometria',
        'subtitle' => 'Monitore o status dos cadastros biométricos, acompanhe cobertura e atue rapidamente nas pendências.',
        'icon' => 'bi bi-fingerprint',
        'actions' => [
            ['label' => 'Novo cadastro', 'icon' => 'bi bi-person-plus-fill', 'url' => route_to('biometric.index')],
            ['label' => 'Testar reconhecimento', 'icon' => 'bi bi-camera-video-fill', 'url' => route_to('biometric.index') . '#test-recognition'],
        ],
    ]) ?>

    <div class="sp-compliance-grid">
        <div class="span-3"><div class="sp-stat-block"><strong><?= esc($stats['total_users'] ?? 0) ?></strong><span>Total de usuários</span></div></div>
        <div class="span-3"><div class="sp-stat-block"><strong><?= esc($stats['with_face'] ?? 0) ?></strong><span>Com reconhecimento facial</span></div></div>
        <div class="span-3"><div class="sp-stat-block"><strong><?= esc($stats['with_fingerprint'] ?? 0) ?></strong><span>Com biometria digital</span></div></div>
        <div class="span-3"><div class="sp-stat-block"><strong><?= esc($stats['with_both'] ?? 0) ?></strong><span>Com ambos os métodos</span></div></div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title"><i class="bi bi-table"></i>Status dos cadastros</h2>
        </div>
        <div class="sp-data-card__body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Reconhecimento facial</th>
                            <th>Biometria digital</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= esc($user->name) ?></td>
                            <td><?= esc($user->email) ?></td>
                            <td>
                                <?php if ($user->has_face_biometric): ?>
                                    <span class="badge bg-success">Cadastrado</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user->has_fingerprint_biometric): ?>
                                    <span class="badge bg-success">Cadastrado</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('employees/show/' . $user->id) ?>"
                                   class="btn btn-sm btn-outline-primary" title="Ver perfil do colaborador">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= site_url('biometric/enroll-for/' . $user->id) ?>"
                                   class="btn btn-sm btn-outline-success" title="Cadastrar face deste colaborador">
                                    <i class="bi bi-camera-video-fill"></i>
                                </a>
                                <a href="<?= site_url('biometric/fingerprint/enroll/' . $user->id) ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Cadastrar digital deste colaborador">
                                    <i class="bi bi-fingerprint"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
