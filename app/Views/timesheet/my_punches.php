<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Minhas marcações<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-clock me-2"></i>Minhas marcações</h2>
            <p class="text-muted mb-0">Consulte suas marcações do mês, acompanhe método utilizado e acesse comprovantes quando disponíveis.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= sp_timesheet_history_url() ?>" class="btn btn-outline-primary"><i class="fas fa-history me-2"></i>Histórico completo</a>
            <a href="<?= sp_timesheet_punch_url() ?>" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Registrar ponto</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= current_url() ?>" class="row g-3 align-items-end">
                <div class="col-md-4 col-lg-3">
                    <label for="month" class="form-label">Competência</label>
                    <input type="month" id="month" name="month" value="<?= esc($currentMonth ?? date('Y-m')) ?>" class="form-control">
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="small text-muted mb-2">Total de registros no mês</div>
                    <div class="fs-4 fw-semibold"><?= count($punches ?? []) ?></div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="small text-muted mb-2">Última atualização</div>
                    <div class="fw-semibold"><?= date('d/m/Y H:i') ?></div>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-2"></i>Aplicar</button>
                    <a href="<?= current_url() ?>" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Registros encontrados</h5>
            <span class="badge bg-light text-dark"><?= esc($currentMonth ?? date('Y-m')) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($punches ?? [])): ?>
                <div class="p-4 text-center text-muted">Nenhuma marcação encontrada para o período selecionado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Método</th>
                                <th>NSR</th>
                                <th>Geofence</th>
                                <th class="text-end">Comprovante</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($punches ?? []) as $punch): ?>
                                <?php
                                    $time = isset($punch->punch_time) ? strtotime((string) $punch->punch_time) : null;
                                    $type = (string) ($punch->punch_type ?? '-');
                                    $method = (string) ($punch->method ?? '-');
                                ?>
                                <tr>
                                    <td><?= $time ? date('d/m/Y', $time) : '-' ?></td>
                                    <td><strong><?= $time ? date('H:i:s', $time) : '-' ?></strong></td>
                                    <td><?= esc(ucwords(str_replace('_', ' ', $type))) ?></td>
                                    <td><?= esc(ucwords(str_replace('_', ' ', $method))) ?></td>
                                    <td><?= esc((string) ($punch->nsr ?? '-')) ?></td>
                                    <td>
                                        <?php if (!empty($punch->within_geofence)): ?>
                                            <span class="badge bg-success">Dentro<?= !empty($punch->geofence_name) ? ' · ' . esc((string) $punch->geofence_name) : '' ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Fora / não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!empty($punch->id)): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="spDownloadReceipt(this, '<?= esc(sp_timesheet_receipt_url((int) $punch->id), 'js') ?>')">
                                                <i class="fas fa-file-download me-1"></i>Comprovante
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">Indisponível</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (isset($pager) && $pager): ?>
            <div class="card-footer bg-white">
                <?= $pager->links() ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
async function spDownloadReceipt(btn, url) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    try {
        const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await resp.json();
        if (json.success !== false && json.data && json.data.download_url) {
            window.location.href = json.data.download_url;
        } else {
            alert(json.message || 'Erro ao gerar comprovante.');
        }
    } catch (err) {
        alert('Erro de comunicação ao gerar comprovante.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}
</script>
<?= $this->endSection() ?>