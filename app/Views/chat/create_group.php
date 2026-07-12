<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Criar Grupo<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('chat') ?>">Chat</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Criar Grupo</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>
                        Criar Novo Grupo
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= sp_flash(session()->getFlashdata('error')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form action="<?= site_url('chat/group/store') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <label for="name" class="form-label">
                                <i class="fas fa-tag me-1"></i>
                                Nome do Grupo *
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="name" 
                                   name="name" 
                                   placeholder="Ex: Equipe de Desenvolvimento"
                                   value="<?= sp_attr(old('name')) ?>"
                                   required
                                   maxlength="100">
                            <div class="form-text">O nome do grupo será visível para todos os membros.</div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left me-1"></i>
                                Descrição (opcional)
                            </label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="Descreva o propósito do grupo..."
                                      maxlength="500"><?= sp_text(old('description')) ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-user-plus me-1"></i>
                                Adicionar Membros *
                            </label>
                            <div class="border rounded p-3 sp-scroll-panel-300">
                                <?php if (!empty($employees)): ?>
                                    <?php foreach ($employees as $emp): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               name="members[]" 
                                               value="<?= sp_attr($emp['id']) ?>" 
                                               id="member_<?= (int) $emp['id'] ?>">
                                        <label class="form-check-label" for="member_<?= (int) $emp['id'] ?>">
                                            <span class="d-flex align-items-center">
                                                <span class="avatar-circle bg-primary text-white me-2 sp-avatar-xs">
                                                    <?= strtoupper(substr($emp['name'] ?? 'U', 0, 1)) ?>
                                                </span>
                                                <span>
                                                    <strong><?= esc($emp['name']) ?></strong>
                                                    <?php if (!empty($emp['department'])): ?>
                                                    <br><small class="text-muted"><?= esc($emp['department']) ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Nenhum colaborador disponível.</p>
                                <?php endif; ?>
                            </div>
                            <div class="form-text">Selecione pelo menos 2 membros para criar o grupo.</div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="<?= site_url('chat') ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check me-1"></i> Criar Grupo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
