<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Colaboradores<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- TEST_MARKER_12345 -->
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Colaboradores',
        'subtitle' => 'Gerencie cadastros, acompanhe status e acesse rapidamente as ações administrativas da equipe.',
        'icon'     => 'bi bi-people-fill',
        'actions'  => [
            ['label' => 'Novo colaborador',     'icon' => 'bi bi-person-plus-fill', 'url' => site_url('employees/create')],
            ['label' => 'Convidar funcionário', 'icon' => 'bi bi-envelope-plus-fill', 'url' => '#', 'variant' => 'outline-primary', 'attrs' => 'data-bs-toggle="modal" data-bs-target="#inviteModal"'],
            ['label' => 'Dependentes',          'icon' => 'bi bi-person-hearts', 'url' => site_url('employees/dependents')],

        ],
    ]) ?>


    <div class="sp-card">
        <div class="sp-card-header">
            <h5 class="sp-card-title"><i class="bi bi-people"></i> Lista de colaboradores</h5>
            <div class="sp-card-actions">
                <span class="text-muted small" id="empCount"><?= count($employees ?? []) ?> registros</span>
            </div>
        </div>
        <div class="sp-table-container">
            <table class="sp-table" id="employeesTable">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Departamento</th>
                        <th>Cargo</th>
                        <th class="text-center">Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees ?? [])): ?>
                        <tr>
                            <td colspan="6">
                                <div class="sp-empty">
                                    <div class="sp-empty-icon"><i class="bi bi-people"></i></div>
                                    <p class="sp-empty-title">Nenhum colaborador encontrado</p>
                                    <a href="<?= site_url('employees/create') ?>" class="sp-btn sp-btn-primary sp-btn-sm">
                                        <i class="bi bi-person-plus-fill"></i> Adicionar primeiro colaborador
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                                $empId     = (int) ($emp->id ?? 0);
                                $isActive  = (bool) ($emp->active ?? false);
                                $isAdmin   = strtolower((string)($emp->role ?? '')) === 'admin';
                            ?>
                            <tr id="emp-row-<?= $empId ?>" class="<?= $isActive ? '' : 'table-secondary opacity-75' ?>">
                                <td>
                                    <strong><?= esc($emp->name ?? '') ?></strong><br>
                                    <small class="text-muted"><?= esc(ucfirst($emp->role ?? 'funcionario')) ?></small>
                                </td>
                                <td><?= esc($emp->email ?? '-') ?></td>
                                <td><?= esc($emp->department ?? '-') ?></td>
                                <td><?= esc($emp->position ?? '-') ?></td>
                                <td class="text-center">
                                    <?php if ($isAdmin): ?>
                                        <!-- Admin não pode ser desativado pela listagem -->
                                        <span class="sp-badge sp-badge-success">
                                            <i class="bi bi-shield-fill me-1"></i>Ativo
                                        </span>
                                    <?php else: ?>
                                        <span id="badge-<?= $empId ?>"
                                              class="sp-badge <?= $isActive ? 'sp-badge-success' : 'sp-badge-danger' ?>">
                                            <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                            <?= $isActive ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-icon-actions">
                                        <a href="<?= site_url('employees/' . $empId) ?>"
                                           class="icon-action" title="Visualizar">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        <?php if ($isActive): ?>
                                            <a href="<?= site_url('employees/' . $empId . '/edit') ?>"
                                               class="icon-action icon-action-edit" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$isAdmin): ?>
                                            <button type="button"
                                                    class="icon-action emp-toggle-btn <?= $isActive ? 'icon-action-warning' : 'icon-action-success' ?>"
                                                    id="toggle-<?= $empId ?>"
                                                    data-id="<?= $empId ?>"
                                                    data-name="<?= esc(addslashes($emp->name ?? '')) ?>"
                                                    title="<?= $isActive ? 'Desativar' : 'Ativar' ?>">
                                                <i class="bi <?= $isActive ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($pager)): ?>
            <div class="sp-card-footer">
                <?= $pager->links('default', 'default_full') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Convites pendentes -->
    <?php if (!empty($activeInvites ?? [])): ?>
    <div class="sp-card mt-3">
        <div class="sp-card-header d-flex justify-content-between align-items-center">
            <span class="sp-card-title">
                <i class="bi bi-envelope-check-fill me-1"></i> Convites enviados
                <span class="badge bg-warning text-dark ms-1"><?= count($activeInvites) ?> aguardando</span>
            </span>
            <a href="<?= site_url('employees/pending') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-clock-history me-1"></i>Ver cadastros pendentes
            </a>
        </div>
        <div class="sp-table-container">
            <table class="sp-table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>E-mail convidado</th>
                        <th>Nome</th>
                        <th>Departamento</th>
                        <th>Cargo</th>
                        <th>Expira em</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeInvites as $inv): ?>
                    <?php $expiraEm = strtotime($inv->expires_at) - time(); $h = round($expiraEm/3600); ?>
                    <tr>
                        <td><?= esc($inv->email) ?></td>
                        <td><?= esc($inv->name ?: '-') ?></td>
                        <td><?= esc($inv->department ?: '-') ?></td>
                        <td><?= esc($inv->position ?: '-') ?></td>
                        <td>
                            <span class="badge <?= $h < 24 ? 'bg-danger' : 'bg-success' ?>">
                                <?= $h >= 24 ? ceil($h/24) . 'd' : $h . 'h' ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="copyInviteLink('<?= esc(site_url('convite/' . $inv->token)) ?>')"
                                    title="Copiar link">
                                <i class="bi bi-link-45deg"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Convidar Funcionário -->
    <div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalTitle" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;overflow:hidden;">

          <!-- Header -->
          <div class="modal-header border-0 pb-0" style="background:var(--sp-primary-dark);padding:1.5rem 1.75rem 1.25rem;">
            <div class="d-flex align-items-center gap-3">
              <div style="background:rgba(255,255,255,.15);border-radius:.75rem;width:3rem;height:3rem;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-envelope-plus-fill fs-5 text-white"></i>
              </div>
              <div>
                <h5 class="modal-title mb-0 text-white fw-bold" id="inviteModalTitle">Convidar Colaborador</h5>
                <p class="mb-0 text-white-50 small">Envie um link de auto-cadastro por e-mail</p>
              </div>
            </div>
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>

          <style>
          /* INVITE MODAL — segue o tema (claro/escuro) via design system */
          #inviteModal .modal-header {
            background: var(--sp-primary-dark) !important;
            border-bottom: none !important;
            padding: 1.5rem 1.75rem 1.25rem !important;
          }
          #inviteModal .modal-header,
          #inviteModal .modal-header * { color: #fff !important; }
          #inviteModal .btn-close { filter: invert(1) !important; opacity: .85 !important; }
          #inviteModal .modal-dialog { pointer-events: auto !important; }
          </style>
          <div class="modal-body p-0">

            <!-- Formulário -->
            <div id="inviteForm">
              <form id="formInvite" autocomplete="off" novalidate>
                <?= csrf_field() ?>

                <!-- Seção 1: Identificação -->
                <div class="px-4 pt-4 pb-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-person-fill text-primary"></i> Identificação
                  </p>
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label fw-semibold" for="inv_email">
                        E-mail <span class="text-danger">*</span>
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                          <i class="bi bi-envelope-fill text-muted"></i>
                        </span>
                        <input type="email"
                               id="inv_email"
                               name="email"
                               class="form-control border-start-0 ps-0"
                               placeholder="colaborador@empresa.com.br"
                               autocomplete="off"
                               required
                               style="box-shadow:none;">
                      </div>
                      <div class="form-text">O colaborador receberá o link de cadastro neste endereço.</div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="inv_name">Nome <span class="text-muted small fw-normal">(opcional)</span></label>
                      <input type="text"
                             id="inv_name"
                             name="name"
                             class="form-control"
                             placeholder="Nome completo"
                             autocomplete="off">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="inv_dept">Departamento</label>
                      <select id="inv_dept" name="department" class="form-select">
                        <option value="">— Selecione —</option>
                        <?php foreach (($formOptions['departments'] ?? []) as $dept):
                              $dId   = is_object($dept) ? ($dept->id ?? '') : ($dept['id'] ?? '');
                              $dName = is_object($dept) ? ($dept->name ?? '') : ($dept['name'] ?? '');
                              if (empty($dName)) continue; ?>
                          <option value="<?= esc($dName) ?>"><?= esc($dName) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="inv_position">Cargo / Função</label>
                      <?php if (!empty($formOptions['positions'] ?? [])): ?>
                        <select id="inv_position" name="position" class="form-select">
                          <option value="">— Selecione —</option>
                          <?php foreach (($formOptions['positions'] ?? []) as $pos):
                                $pName = is_object($pos) ? ($pos->name ?? '') : ($pos['name'] ?? '');
                                if (empty($pName)) continue; ?>
                            <option value="<?= esc($pName) ?>"><?= esc($pName) ?></option>
                          <?php endforeach; ?>
                        </select>
                      <?php else: ?>
                        <input type="text" id="inv_position" name="position" class="form-control"
                               placeholder="Ex: Analista de RH" autocomplete="off">
                      <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-semibold" for="inv_role">Nível de acesso</label>
                      <select id="inv_role" name="role" class="form-select">
                        <option value="funcionario" selected>Funcionário</option>
                        <option value="gestor">Gestor</option>
                        <option value="rh">RH</option>
                        <option value="dpo">DPO / LGPD</option>
                      </select>
                    </div>
                  </div>
                </div>

                <hr class="mx-4 my-0" style="opacity:.08;">

                <!-- Seção 2: Configurações do link -->
                <div class="px-4 py-3">
                  <p class="text-muted small text-uppercase fw-semibold mb-3 d-flex align-items-center gap-2">
                    <i class="bi bi-link-45deg text-primary"></i> Configurações do convite
                  </p>
                  <div class="row g-3">
                    <div class="col-md-5">
                      <label class="form-label fw-semibold" for="inv_expires">Validade do link</label>
                      <select id="inv_expires" name="expires_hours" class="form-select">
                        <option value="24">⏱ 24 horas</option>
                        <option value="72" selected>📅 72 horas (3 dias)</option>
                        <option value="168">🗓 7 dias</option>
                      </select>
                    </div>
                    <div class="col-md-7">
                      <label class="form-label fw-semibold" for="inv_message">Mensagem personalizada <span class="text-muted small fw-normal">(opcional)</span></label>
                      <textarea id="inv_message" name="message" class="form-control" rows="1"
                                placeholder="Bem-vindo! Preencha seus dados para acessar o sistema."
                                style="resize:none;"></textarea>
                    </div>
                    <div class="col-12">
                      <div class="form-check form-switch d-flex align-items-center gap-2 p-3 rounded-3"
                           style="background:var(--sp-primary-light);border:1px solid var(--sp-primary);">
                        <input class="form-check-input mt-0 flex-shrink-0"
                               type="checkbox"
                               id="sendEmailCheck"
                               name="send_email"
                               value="1"
                               checked
                               role="switch"
                               style="width:2.5rem;height:1.25rem;cursor:pointer;">
                        <label class="form-check-label ms-2" for="sendEmailCheck" style="cursor:pointer;">
                          <span class="fw-semibold">Enviar convite por e-mail automaticamente</span><br>
                          <span class="text-muted small">O link também ficará disponível para copiar após a criação.</span>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </form>
            </div>

            <!-- Resultado (pós-criação) -->
            <div id="inviteResult" style="display:none;" class="p-4">
              <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                     style="width:4rem;height:4rem;background:var(--sp-success-light);">
                  <i class="bi bi-check2-circle text-success fs-2"></i>
                </div>
                <h6 class="fw-bold text-success mb-1">Convite criado com sucesso!</h6>
                <p class="text-muted small mb-0" id="inviteEmailNote"></p>
              </div>
              <div class="row g-3 align-items-start">
                <div class="col-md-8">
                  <label class="form-label fw-semibold">Link de cadastro</label>
                  <div class="input-group">
                    <input type="text" class="form-control font-monospace bg-light" id="inviteLink" readonly
                           style="font-size:.8rem;">
                    <button class="btn btn-outline-primary" type="button"
                            onclick="copyInviteLink(document.getElementById('inviteLink').value)">
                      <i class="bi bi-clipboard me-1"></i>Copiar
                    </button>
                  </div>
                  <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary" type="button" onclick="openInviteLink()">
                      <i class="bi bi-box-arrow-up-right me-1"></i>Testar link
                    </button>
                    <button class="btn btn-sm btn-outline-success" type="button" id="btnWhatsApp">
                      <i class="bi bi-whatsapp me-1"></i>WhatsApp
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            onclick="document.getElementById('inviteResult').style.display='none';document.getElementById('inviteForm').style.display='block';document.getElementById('formInvite').reset();document.getElementById('btnSendInvite').style.display='';">
                      <i class="bi bi-plus-circle me-1"></i>Novo convite
                    </button>
                  </div>
                </div>
                <div class="col-md-4 text-center">
                  <label class="form-label fw-semibold d-block">QR Code</label>
                  <img id="inviteQR" src="" alt="QR Code"
                       style="width:150px;height:150px;border:1px solid var(--sp-border);border-radius:.75rem;padding:.5rem;background:#fff;"
                       loading="lazy">
                  <div class="form-text">Escaneie para abrir no celular</div>
                </div>
              </div>
            </div>

          </div><!-- /modal-body -->

          <div class="modal-footer border-0 px-4 py-3 d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
              <i class="bi bi-x-lg me-1"></i>Cancelar
            </button>
            <button type="button" class="btn btn-primary px-5" id="btnSendInvite" onclick="sendInvite()">
              <i class="bi bi-send-fill me-2"></i>Gerar convite
            </button>
          </div>

        </div><!-- /modal-content -->
      </div><!-- /modal-dialog -->
    </div><!-- /#inviteModal -->

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
'use strict';

// ─── URLs das rotas ───────────────────────────────────────────────────────────
const EMP_ACTIVATE_URL   = '<?= esc(site_url('employees/')) ?>';
const INVITE_URL         = '<?= esc(site_url("employees/invite")) ?>';

SupportPontoValidation.bindEmailFormatField(document.getElementById('inv_email'));

// ─── Toast simples ────────────────────────────────────────────────────────────
function empToast(msg, ok) {
    const colors = ok ? '#166534,#dcfce7' : '#991b1b,#fee2e2';
    const [fg, bg] = colors.split(',');
    const box = document.createElement('div');
    box.style.cssText = `position:fixed;top:1.25rem;right:1.25rem;z-index:9999;padding:.75rem 1.1rem;border-radius:.5rem;background:${bg};color:${fg};font-size:.875rem;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.15);display:flex;align-items:center;gap:.5rem;max-width:360px`;
    box.innerHTML = `<i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}" style="font-size:1.1rem"></i><span>${msg}</span>`;
    document.body.appendChild(box);
    setTimeout(() => { box.style.transition = 'opacity .35s'; box.style.opacity = '0'; setTimeout(() => box.remove(), 400); }, 4000);
}

// ─── Toggle ativar / desativar colaborador ────────────────────────────────────
document.addEventListener('click', async function(e) {
    const toggle = e.target.closest('.emp-toggle-btn');
    if (!toggle) return;

    const id     = parseInt(toggle.dataset.id, 10);
    const name   = toggle.dataset.name || 'Colaborador';
    const url    = EMP_ACTIVATE_URL + id + '/toggle-active';

    toggle.disabled = true;

    try {
        // Montar FormData com token CSRF do meta tag (cookie é HttpOnly)
        const fd = new FormData();
        const csrfName = document.querySelector('meta[name="csrf-token-name"]')?.content || 'csrf_token';
        const csrfHash = document.querySelector('meta[name="csrf-hash"]')?.content || '';
        if (csrfHash) { fd.append(csrfName, csrfHash); }
        const resp = await spFetch(url, { method: 'POST', body: fd });

        // Tratar respostas de redirect (302) ou erro de parse
        if (!resp.ok && resp.status !== 200) {
            throw new Error('HTTP ' + resp.status);
        }

        let json;
        const raw = await resp.text();
        try {
            json = JSON.parse(raw);
            // Normalizar: se o servidor retornou string vazia ou não-objeto
            if (typeof json !== 'object' || json === null) {
                throw new Error('HTTP ' + resp.status + ' — sem autorização ou sessão expirada.');
            }
        } catch (_) {
            if (resp.status === 401 || resp.status === 403) {
                throw new Error('Sem permissão ou sessão expirada. Faça login novamente.');
            }
            throw new Error('Resposta inesperada do servidor (HTTP ' + resp.status + ').');
        }

        if (json.success) {
            const isNowActive = json.active === true;

            // Atualizar badge
            const badge = document.getElementById('badge-' + id);
            if (badge) {
                badge.className = 'sp-badge ' + (isNowActive ? 'sp-badge-success' : 'sp-badge-danger');
                badge.innerHTML = '<i class="bi ' + (isNowActive ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + '"></i> ' + (isNowActive ? 'Ativo' : 'Inativo');
            }

            // Atualizar ícone do toggle para refletir estado real do servidor
            toggle.className = 'icon-action emp-toggle-btn ' + (isNowActive ? 'icon-action-warning' : 'icon-action-success');
            toggle.title = isNowActive ? 'Desativar' : 'Ativar';
            toggle.querySelector('i').className = 'bi ' + (isNowActive ? 'bi-toggle-on' : 'bi-toggle-off');

            // Atualizar row opacity
            const row = document.getElementById('emp-row-' + id);
            if (row) {
                row.classList.toggle('table-secondary', !isNowActive);
                row.classList.toggle('opacity-75', !isNowActive);
            }

            // Atualizar meta CSRF para a próxima requisição (evita falha por regeneração)
            if (json.csrf_hash) {
                const csrfMeta = document.querySelector('meta[name="csrf-hash"]');
                if (csrfMeta) csrfMeta.setAttribute('content', json.csrf_hash);
            }
            empToast(json.message || (isNowActive ? name + ' ativado.' : name + ' desativado.'), true);

        } else {
            empToast(json.message || 'Não foi possível alterar o status.', false);
        }

    } catch (err) {
        empToast('Erro: ' + (err.message || 'Falha de comunicação com o servidor.'), false);
    } finally {
        toggle.disabled = false;
    }
});

// ─── Convite ──────────────────────────────────────────────────────────────────
function copyInviteLink(url) {
    navigator.clipboard.writeText(url).then(function() {
        const btn = event.currentTarget;
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Copiado!';
        setTimeout(function() { btn.innerHTML = orig; }, 2000);
    }).catch(function() { prompt('Copie o link:', url); });
}

function openInviteLink() {
    const url = document.getElementById('inviteLink').value;
    if (url) window.open(url, '_blank');
}

async function sendInvite() {
    const form = document.getElementById('formInvite');
    const btn  = document.getElementById('btnSendInvite');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde…';
    const fd = new FormData(form);
    let j;
    try {
        const r = await spFetch(INVITE_URL, { method: 'POST', body: fd });
        j = await r.json();
    } catch (err) {
        btn.disabled = false; btn.innerHTML = orig;
        alert('Erro de comunicação com o servidor.');
        return;
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
    if (!j.success) { alert(j.message || 'Erro ao criar convite.'); return; }
    const url = j.url;
    document.getElementById('inviteForm').style.display = 'none';
    document.getElementById('inviteResult').style.display = 'block';
    document.getElementById('inviteLink').value = url;
    document.getElementById('inviteQR').src =
        'https://api.qrserver.com/v1/create-qr-code/?size=150x150&color=1B3A6B&data=' + encodeURIComponent(url);
    document.getElementById('btnWhatsApp').onclick = function() {
        window.open('https://wa.me/?text=' + encodeURIComponent('Acesse o link para se cadastrar: ' + url), '_blank');
    };
    const emailNote = document.getElementById('inviteEmailNote');
    emailNote.textContent = fd.get('send_email')
        ? 'E-mail enviado para ' + fd.get('email')
        : 'Link criado — copie e envie manualmente.';
    document.getElementById('btnSendInvite').style.display = 'none';
}

document.getElementById('inviteModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('inviteForm').style.display = 'block';
    document.getElementById('inviteResult').style.display = 'none';
    document.getElementById('formInvite').reset();
    document.getElementById('btnSendInvite').style.display = '';
});
</script>
<?= $this->endSection() ?>
