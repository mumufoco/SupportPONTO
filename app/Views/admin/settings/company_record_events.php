<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Alterações cadastrais da empresa no REP<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-admin-listing-shell">
    <?= view('components/page_header', [
        'title' => 'Alterações cadastrais da empresa no REP (AFD)',
        'subtitle' => 'Declare mudanças nos dados cadastrais da empresa registrados no REP (razão social, CNPJ/CPF, CNO/CAEPF, local de prestação de serviços). Cada declaração gera um registro tipo "2" imutável no AFD, com NSR próprio e CPF do responsável.',
        'icon' => 'bi bi-building-gear',
        'actions' => [
                                            ],
    ]) ?>


    <div class="sp-callout-info mb-3">
        <strong><i class="bi bi-info-circle-fill me-2"></i>Quando declarar uma alteração cadastral</strong>
        <div>
            Sempre que a razão social, o CNPJ/CPF, o CNO/CAEPF ou o local de prestação de
            serviços da empresa mudar nas configurações do REP, declare aqui o novo "retrato"
            desses dados — a Portaria MTE 671/2021 exige esse registro (tipo "2") no AFD,
            com a data/hora da gravação e o CPF do responsável pela alteração.
            Declarações são <strong>imutáveis</strong> após o registro.
        </div>
    </div>

    <?php if (! empty($companyProfile)): ?>
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-building"></i> Cadastro atual da empresa (configurações do sistema)</span>
        </div>
        <div class="sp-card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="fw-semibold small text-muted">Razão social</div>
                    <div><?= esc($companyProfile['companyName'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold small text-muted">CNPJ/CPF</div>
                    <div><?= esc($companyProfile['companyCNPJ'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-2">
                    <div class="fw-semibold small text-muted">CNO/CAEPF</div>
                    <div><?= esc($companyProfile['companyCnoCaepf'] ?? '—') ?></div>
                </div>
                <div class="col-md-2">
                    <div class="fw-semibold small text-muted">Local de prestação</div>
                    <div><?= esc($companyProfile['companyAddress'] ?? '—') ?></div>
                </div>
            </div>
            <div class="small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i>Estes valores vêm das configurações do sistema (aba de
                informações da empresa) e são usados no cabeçalho de todo AFD gerado. Use-os como referência
                para preencher o "retrato" da declaração abaixo.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="sp-disciplinary-grid">
        <div class="span-8">
            <div class="sp-surface-card">
                <div class="sp-surface-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-plus-circle"></i>Declarar alteração cadastral</h2>
                </div>
                <div class="sp-surface-card__body">
                    <form action="<?= sp_route_url('admin.company-record-events.store') ?>" method="post" class="sp-form-layout">
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="recorded_at" class="form-label">Data/hora do registro</label>
                                <input type="datetime-local" id="recorded_at" name="recorded_at" class="form-control" step="1" required value="<?= esc(old('recorded_at')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="responsible_cpf" class="form-label">CPF do responsável</label>
                                <input type="text" id="responsible_cpf" name="responsible_cpf" class="form-control" maxlength="14" placeholder="000.000.000-00" required value="<?= esc(old('responsible_cpf')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="employer_doc_type" class="form-label">Tipo de documento do empregador</label>
                                <select id="employer_doc_type" name="employer_doc_type" class="form-select" required>
                                    <option value="1" <?= old('employer_doc_type', '1') === '1' ? 'selected' : '' ?>>CNPJ</option>
                                    <option value="2" <?= old('employer_doc_type') === '2' ? 'selected' : '' ?>>CPF</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="employer_doc" class="form-label">CNPJ/CPF do empregador</label>
                                <input type="text" id="employer_doc" name="employer_doc" class="form-control" maxlength="18" required value="<?= esc(old('employer_doc')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="cno_caepf" class="form-label">CNO/CAEPF (se houver)</label>
                                <input type="text" id="cno_caepf" name="cno_caepf" class="form-control" maxlength="14" value="<?= esc(old('cno_caepf')) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="company_name" class="form-label">Razão social / nome do empregador</label>
                                <input type="text" id="company_name" name="company_name" class="form-control" maxlength="150" required value="<?= esc(old('company_name')) ?>">
                            </div>
                            <div class="col-md-8">
                                <label for="service_location" class="form-label">Local de prestação de serviços</label>
                                <input type="text" id="service_location" name="service_location" class="form-control" maxlength="100" value="<?= esc(old('service_location')) ?>">
                            </div>
                            <div class="col-md-12">
                                <label for="reason" class="form-label">Motivo da alteração</label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" minlength="10" maxlength="2000" required placeholder="Ex.: alteração de razão social após reorganização societária; mudança de endereço da sede."><?= esc(old('reason')) ?></textarea>
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
                            <div>Após salvar, esta declaração recebe um NSR canônico (mesma sequência das marcações
                            de ponto e dos ajustes de relógio) e não poderá mais ser alterada ou removida — apenas
                            consultada. Ela entrará automaticamente em qualquer AFD gerado para um período que a
                            contenha.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-floppy-fill me-1"></i>Registrar alteração
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="sp-card mt-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-list-ul"></i> Alterações já declaradas</span>
        </div>
        <div class="sp-card-body p-0">
            <?php if (empty($events)): ?>
                <div class="sp-callout-neutral m-3">
                    <i class="bi bi-info-circle me-2"></i>Nenhuma alteração cadastral declarada até o momento.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>NSR</th>
                                <th>Gravação</th>
                                <th>Razão social</th>
                                <th>CNPJ/CPF</th>
                                <th>Local de prestação</th>
                                <th>CPF responsável</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?= esc($ev->nsr) ?></span></td>
                                    <td><?= esc($ev->recorded_at) ?></td>
                                    <td><?= esc($ev->company_name) ?></td>
                                    <td><?= esc($ev->employer_doc) ?></td>
                                    <td><?= esc($ev->service_location ?? '—') ?></td>
                                    <td><?= esc($ev->responsible_cpf) ?></td>
                                    <td class="text-truncate" style="max-width: 280px;" title="<?= esc($ev->reason) ?>"><?= esc($ev->reason) ?></td>
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

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
SupportPontoValidation.bindCpfField(document.getElementById('responsible_cpf'));
</script>
<?= $this->endSection() ?>
