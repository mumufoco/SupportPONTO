<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Matriz de permissões<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $rows = $rows ?? []; ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Matriz de permissões',
        'subtitle' => 'Defina e documente o escopo de acesso por perfil para reduzir risco e facilitar governança.',
        'icon' => 'bi bi-key-fill',
        'actions' => [
            ['label' => 'Segurança', 'icon' => 'bi bi-shield-lock-fill', 'url' => sp_admin_settings_security_url()],
            ['label' => 'LGPD', 'icon' => 'bi bi-person-lock', 'url' => site_url('compliance/lgpd')],
        ],
    ]) ?>

    <div class="sp-security-matrix">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Admin</th>
                        <th>Gestor</th>
                        <th>Colaborador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= esc($row['module']) ?></strong></td>
                            <td><?= esc($row['admin']) ?></td>
                            <td><?= esc($row['gestor']) ?></td>
                            <td><?= esc($row['funcionario']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
