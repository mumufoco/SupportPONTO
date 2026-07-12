<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurações do Chat<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('chat') ?>">Chat</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Configurações</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Informações da Sala
                    </h6>
                </div>
                <div class="card-body text-center">
                    <div class="avatar-circle bg-primary text-white mx-auto mb-3 sp-avatar-xl">
                        <?php if (!empty($room['is_group'])): ?>
                            <i class="fas fa-users"></i>
                        <?php else: ?>
                            <?= strtoupper(substr($room['name'] ?? 'C', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h5 class="mb-1"><?= esc($room['name'] ?? 'Chat') ?></h5>
                    <?php if (!empty($room['description'])): ?>
                    <p class="text-muted small"><?= esc($room['description']) ?></p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <div class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= $room['member_count'] ?? 0 ?></span>
                            <br><small class="text-muted">Membros</small>
                        </div>
                        <div class="text-center">
                            <span class="badge bg-success rounded-pill"><?= $room['message_count'] ?? 0 ?></span>
                            <br><small class="text-muted">Mensagens</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($room['is_group'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-cog me-2"></i>
                        Ações
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($isAdmin ?? false): ?>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editGroupModal">
                            <i class="fas fa-edit me-1"></i> Editar Grupo
                        </button>
                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="fas fa-user-plus me-1"></i> Adicionar Membro
                        </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#leaveGroupModal">
                            <i class="fas fa-sign-out-alt me-1"></i> Sair do Grupo
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Membros (<?= count($members ?? []) ?>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($members)): ?>
                            <?php foreach ($members as $member): ?>
                            <div class="list-group-item d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-secondary text-white me-3 sp-avatar-md">
                                        <?= strtoupper(substr($member['name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <strong><?= esc($member['name'] ?? 'Usuário') ?></strong>
                                        <?php if (!empty($member['is_admin'])): ?>
                                        <span class="badge bg-warning text-dark ms-1">Admin</span>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?= esc($member['email'] ?? '') ?></small>
                                    </div>
                                </div>
                                <?php if (($isAdmin ?? false) && empty($member['is_current_user'])): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (empty($member['is_admin'])): ?>
                                        <li>
                                            <a class="dropdown-item" href="#">
                                                <i class="fas fa-crown me-2"></i> Tornar Admin
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#">
                                                <i class="fas fa-user-minus me-2"></i> Remover
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-4">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <p class="mb-0">Nenhum membro encontrado.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-bell me-2"></i>
                        Notificações
                    </h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="notifyMessages" checked>
                        <label class="form-check-label" for="notifyMessages">
                            Receber notificações de novas mensagens
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="notifyMentions" checked>
                        <label class="form-check-label" for="notifyMentions">
                            Receber notificações quando mencionado
                        </label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="soundEnabled" checked>
                        <label class="form-check-label" for="soundEnabled">
                            Ativar sons de notificação
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <a href="<?= route_to('chat.room', (int) ($room['id'] ?? 0)) ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao Chat
            </a>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: 600;
}
</style>
<?= $this->endSection() ?>
