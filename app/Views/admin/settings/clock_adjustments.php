<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Ajustes de relógio do REP<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-admin-listing-shell">
    <?= view('components/page_header', [
        'title' => 'Ajustes de relógio do REP (AFD)',
        'subtitle' => 'Declare alterações conscientes no relógio do servidor (migração, correção de fuso/horário). Cada declaração gera um registro tipo "4" imutável no AFD, com NSR próprio e CPF do responsável.',
        'icon' => 'bi bi-clock-history',
        'actions' => [
                                            ],
    ]) ?>


    <div class="sp-callout-info mb-3">
        <strong><i class="bi bi-info-circle-fill me-2"></i>Quando declarar um ajuste de relógio</strong>
        <div>
            O SupportPONTO é um REP-P (registrador eletrônico de ponto por programa) — seu "relógio" é o
            relógio do servidor onde o sistema roda. Diferente de um terminal físico, mudanças nele são
            raras e conscientes: migração de servidor, correção manual de horário ou fuso. Sempre que isso
            acontecer, declare aqui a data/hora <strong>antes</strong> e <strong>depois</strong> do ajuste e
            o CPF do responsável — a Portaria MTE 671/2021 exige esse registro (tipo "4") no AFD.
            Declarações são <strong>imutáveis</strong> após o registro.
        </div>
    </div>

    <div class="sp-disciplinary-grid">
        <div class="span-8">
            <div class="sp-surface-card">
                <div class="sp-surface-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-plus-circle"></i>Declarar novo ajuste</h2>
                </div>
                <div class="sp-surface-card__body">
                    <form action="<?= sp_route_url('admin.clock-adjustments.store') ?>" method="post" class="sp-form-layout">
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="previous_datetime" class="form-label">Data/hora ANTES do ajuste</label>
                                <input type="datetime-local" id="previous_datetime" name="previous_datetime" class="form-control" step="1" required value="<?= esc(old('previous_datetime')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="adjusted_datetime" class="form-label">Data/hora DEPOIS do ajuste</label>
                                <input type="datetime-local" id="adjusted_datetime" name="adjusted_datetime" class="form-control" step="1" required value="<?= esc(old('adjusted_datetime')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="responsible_cpf" class="form-label">CPF do responsável</label>
                                <input type="text" id="responsible_cpf" name="responsible_cpf" class="form-control" maxlength="14" placeholder="000.000.000-00" required value="<?= esc(old('responsible_cpf')) ?>">
                            </div>
                            <div class="col-md-8">
                                <label for="reason" class="form-label">Motivo do ajuste</label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" minlength="10" maxlength="2000" required placeholder="Ex.: migração de servidor para novo datacenter; correção de horário após queda de energia."><?= esc(old('reason')) ?></textarea>
                            </div>
                        </div>

                        <?php if (! empty($errors ?? session('errors'))): ?>
                            <div class="sp-callout-danger">
                                <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Corrija os campos abaixo</strong>
                                <ul class="mb-0">
                                    <?php foreach (($errors ?? session('errors')) as $error): ?>
                                        <li><?= esc($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="sp-callout-info">
                            <strong><i class="bi bi-shield-lock-fill me-2"></i>Registro imutável</strong>
                            <div>Após salvar, esta declaração recebe um NSR canônico (mesma sequência das marcações de
                            ponto) e não poderá mais ser alterada ou removida — apenas consultada. Ela entrará
                            automaticamente em qualquer AFD gerado para um período que a contenha.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-floppy-fill me-1"></i>Registrar ajuste
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="sp-card mt-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-list-ul"></i> Ajustes já declarados</span>
        </div>
        <div class="sp-card-body p-0">
            <?php if (empty($adjustments)): ?>
                <div class="sp-callout-neutral m-3">
                    <i class="bi bi-info-circle me-2"></i>Nenhum ajuste de relógio declarado até o momento.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>NSR</th>
                                <th>Antes do ajuste</th>
                                <th>Depois do ajuste</th>
                                <th>CPF responsável</th>
                                <th>Motivo</th>
                                <th>Declarado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adjustments as $adj): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= esc($adj->nsr) ?></span></td>
                                    <td><?= esc($adj->previous_datetime) ?></td>
                                    <td><?= esc($adj->adjusted_datetime) ?></td>
                                    <td><?= esc($adj->responsible_cpf) ?></td>
                                    <td class="text-truncate" style="max-width: 320px;" title="<?= esc($adj->reason) ?>"><?= esc($adj->reason) ?></td>
                                    <td><?= esc($adj->created_at) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
