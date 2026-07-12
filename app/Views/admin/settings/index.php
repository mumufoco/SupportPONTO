<?php
$title = 'Centro de Configurações';
$breadcrumbs = [['label' => 'Configurações', 'url' => '']];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
/* ── Settings Index ──────────────────────────────────────────── */
.sp-cfg-section { margin-bottom: 2rem; }

.sp-cfg-section__header {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: 1rem;
    padding-bottom: .6rem;
    border-bottom: 1px solid var(--sp-border);
}
.sp-cfg-section__header-icon {
    width: 28px; height: 28px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
    flex-shrink: 0;
}
.sp-cfg-section__header-title {
    font-size: .8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--sp-text-secondary);
    margin: 0;
}

/* ── Tile grid ───────────────────────────────────────────────── */
.sp-cfg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: .875rem;
}

/* ── Single tile ─────────────────────────────────────────────── */
.sp-cfg-tile {
    display: flex;
    flex-direction: column;
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: var(--sp-radius-lg);
    padding: 1.25rem;
    text-decoration: none;
    color: inherit;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    position: relative;
    overflow: hidden;
}
.sp-cfg-tile::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--sp-tile-accent, var(--sp-primary));
    opacity: 0;
    transition: opacity .18s ease;
}
.sp-cfg-tile:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,.45);
    border-color: var(--sp-tile-accent, var(--sp-primary));
    color: inherit;
    text-decoration: none;
}
.sp-cfg-tile:hover::before { opacity: 1; }

/* icon */
.sp-cfg-tile__icon {
    width: 44px; height: 44px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 1rem;
    background: var(--sp-tile-icon-bg, var(--sp-primary-light));
    color: var(--sp-tile-accent, var(--sp-primary));
    transition: background .18s ease;
    flex-shrink: 0;
}
.sp-cfg-tile:hover .sp-cfg-tile__icon {
    background: var(--sp-tile-accent, var(--sp-primary));
    color: #fff;
}

.sp-cfg-tile__title {
    font-size: .95rem;
    font-weight: 700;
    color: var(--sp-text-primary);
    margin: 0 0 .35rem;
    line-height: 1.3;
}
.sp-cfg-tile__desc {
    font-size: .8rem;
    color: var(--sp-text-secondary);
    line-height: 1.5;
    flex: 1;
}
.sp-cfg-tile__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: .875rem;
    padding-top: .75rem;
    border-top: 1px solid var(--sp-border);
}
.sp-cfg-tile__tag {
    font-size: .7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: .2rem .55rem;
    border-radius: var(--sp-radius-full);
    background: var(--sp-gray-200);
    color: var(--sp-text-secondary);
}
.sp-cfg-tile__arrow {
    color: var(--sp-text-muted);
    font-size: .9rem;
    transition: transform .18s ease, color .18s ease;
}
.sp-cfg-tile:hover .sp-cfg-tile__arrow {
    transform: translateX(3px);
    color: var(--sp-tile-accent, var(--sp-primary));
}

/* ── Accent themes ───────────────────────────────────────────── */
.sp-cfg-tile--green  { --sp-tile-accent: var(--sp-primary);  --sp-tile-icon-bg: var(--sp-primary-light); }
.sp-cfg-tile--teal   { --sp-tile-accent: var(--sp-success);  --sp-tile-icon-bg: var(--sp-success-light); }
.sp-cfg-tile--blue   { --sp-tile-accent: var(--sp-info);     --sp-tile-icon-bg: var(--sp-info-light); }
.sp-cfg-tile--amber  { --sp-tile-accent: var(--sp-warning);  --sp-tile-icon-bg: var(--sp-warning-light); }
.sp-cfg-tile--red    { --sp-tile-accent: var(--sp-danger);   --sp-tile-icon-bg: var(--sp-danger-light); }
.sp-cfg-tile--purple { --sp-tile-accent: #9b59b6;            --sp-tile-icon-bg: rgba(155,89,182,.15); }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 576px) {
    .sp-cfg-grid { grid-template-columns: 1fr 1fr; gap: .625rem; }
    .sp-cfg-tile { padding: 1rem; }
    .sp-cfg-tile__desc { display: none; }
}
@media (max-width: 380px) {
    .sp-cfg-grid { grid-template-columns: 1fr; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/settings/partials/_settings_validation_notes') ?>
<div class="sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Centro de Configurações',
        'subtitle' => 'Gerencie todas as configurações do sistema em um único lugar.',
        'icon'     => 'bi bi-gear-fill',
        'actions'  => [
            ['label' => 'Saúde do sistema', 'icon' => 'bi bi-heart-pulse-fill', 'url' => route_to('admin.health')],
            ['label' => 'Diagnóstico',      'icon' => 'bi bi-cpu-fill',         'url' => route_to('admin.health.diagnostics')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- ── Empresa & Identidade ──────────────────────────────── -->
    <div class="sp-cfg-section">
        <div class="sp-cfg-section__header">
            <div class="sp-cfg-section__header-icon" style="background:var(--sp-primary-light);color:var(--sp-primary);">
                <i class="bi bi-building-fill"></i>
            </div>
            <h2 class="sp-cfg-section__header-title">Empresa &amp; Identidade</h2>
        </div>
        <div class="sp-cfg-grid">

            <a href="<?= sp_admin_settings_information_url() ?>" class="sp-cfg-tile sp-cfg-tile--green">
                <div class="sp-cfg-tile__icon"><i class="bi bi-building-fill"></i></div>
                <div class="sp-cfg-tile__title">Informações</div>
                <div class="sp-cfg-tile__desc">Razão social, CNPJ, endereço, contato e dados cadastrais da empresa.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Empresa</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_admin_settings_personalization_url() ?>" class="sp-cfg-tile sp-cfg-tile--blue">
                <div class="sp-cfg-tile__icon"><i class="bi bi-palette-fill"></i></div>
                <div class="sp-cfg-tile__title">Personalização</div>
                <div class="sp-cfg-tile__desc">Cores, logos, favicon e identidade visual do sistema.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Visual</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_admin_settings_appearance_url() ?>" class="sp-cfg-tile sp-cfg-tile--purple">
                <div class="sp-cfg-tile__icon"><i class="bi bi-sliders2-vertical"></i></div>
                <div class="sp-cfg-tile__title">Aparência</div>
                <div class="sp-cfg-tile__desc">Tema, modo escuro/claro, layout da interface e preferências visuais.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">UI</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_admin_settings_pwa_url() ?>" class="sp-cfg-tile sp-cfg-tile--teal">
                <div class="sp-cfg-tile__icon"><i class="bi bi-phone-fill"></i></div>
                <div class="sp-cfg-tile__title">PWA</div>
                <div class="sp-cfg-tile__desc">Configurações do app mobile: nome, ícone, cores e instalação.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Mobile</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

        </div>
    </div>

    <!-- ── Comunicação ───────────────────────────────────────── -->
    <div class="sp-cfg-section">
        <div class="sp-cfg-section__header">
            <div class="sp-cfg-section__header-icon" style="background:var(--sp-info-light);color:var(--sp-info);">
                <i class="bi bi-envelope-fill"></i>
            </div>
            <h2 class="sp-cfg-section__header-title">Comunicação</h2>
        </div>
        <div class="sp-cfg-grid">

            <a href="<?= sp_admin_settings_email_url() ?>" class="sp-cfg-tile sp-cfg-tile--blue">
                <div class="sp-cfg-tile__icon"><i class="bi bi-envelope-fill"></i></div>
                <div class="sp-cfg-tile__title">E-mail</div>
                <div class="sp-cfg-tile__desc">Servidor SMTP, remetente padrão e configurações de envio automático.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">SMTP</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_route_url('admin.settings.email-templates') ?>" class="sp-cfg-tile sp-cfg-tile--blue">
                <div class="sp-cfg-tile__icon"><i class="bi bi-envelope-paper-fill"></i></div>
                <div class="sp-cfg-tile__title">Templates de E-mail</div>
                <div class="sp-cfg-tile__desc">Personalize os textos enviados pelo sistema em cada situação de notificação.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Mensagens</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

        </div>
    </div>

    <!-- ── Calendário & RH ───────────────────────────────────── -->
    <div class="sp-cfg-section">
        <div class="sp-cfg-section__header">
            <div class="sp-cfg-section__header-icon" style="background:var(--sp-success-light);color:var(--sp-success);">
                <i class="bi bi-calendar3"></i>
            </div>
            <h2 class="sp-cfg-section__header-title">Calendário &amp; RH</h2>
        </div>
        <div class="sp-cfg-grid">

            <a href="<?= sp_route_url('settings.holidays') ?>" class="sp-cfg-tile sp-cfg-tile--teal">
                <div class="sp-cfg-tile__icon"><i class="bi bi-calendar-heart-fill"></i></div>
                <div class="sp-cfg-tile__title">Feriados</div>
                <div class="sp-cfg-tile__desc">Gerencie feriados nacionais, estaduais e corporativos que bloqueiam o ponto.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Calendário</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

        </div>
    </div>

    <!-- ── Segurança & Acesso ─────────────────────────────────── -->
    <div class="sp-cfg-section">
        <div class="sp-cfg-section__header">
            <div class="sp-cfg-section__header-icon" style="background:var(--sp-danger-light);color:var(--sp-danger);">
                <i class="bi bi-shield-fill-check"></i>
            </div>
            <h2 class="sp-cfg-section__header-title">Segurança &amp; Acesso</h2>
        </div>
        <div class="sp-cfg-grid">

            <a href="<?= sp_admin_settings_authentication_url() ?>" class="sp-cfg-tile sp-cfg-tile--amber">
                <div class="sp-cfg-tile__icon"><i class="bi bi-key-fill"></i></div>
                <div class="sp-cfg-tile__title">Autenticação</div>
                <div class="sp-cfg-tile__desc">Regras de login, duração de sessão, tentativas e políticas de acesso.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Acesso</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_route_url('admin.settings.two-factor') ?>" class="sp-cfg-tile sp-cfg-tile--amber">
                <div class="sp-cfg-tile__icon"><i class="bi bi-shield-lock-fill"></i></div>
                <div class="sp-cfg-tile__title">Autenticação 2FA</div>
                <div class="sp-cfg-tile__desc">Autenticação em duas etapas via TOTP para maior segurança no acesso.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Segurança</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_admin_settings_security_url() ?>" class="sp-cfg-tile sp-cfg-tile--red">
                <div class="sp-cfg-tile__icon"><i class="bi bi-shield-fill-check"></i></div>
                <div class="sp-cfg-tile__title">Segurança</div>
                <div class="sp-cfg-tile__desc">Permissões, criptografia, auditoria e políticas de proteção de dados.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag"><?= ($stats['security'] ?? 0) ?> configs</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_route_url('settings.roles') ?>" class="sp-cfg-tile sp-cfg-tile--purple">
                <div class="sp-cfg-tile__icon"><i class="bi bi-people-fill"></i></div>
                <div class="sp-cfg-tile__title">Níveis de Acesso</div>
                <div class="sp-cfg-tile__desc">Perfis RBAC, permissões granulares e controle de alçadas por função.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">RBAC</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_admin_settings_certificate_url() ?>" class="sp-cfg-tile sp-cfg-tile--teal">
                <div class="sp-cfg-tile__icon"><i class="bi bi-patch-check-fill"></i></div>
                <div class="sp-cfg-tile__title">Certificado Digital</div>
                <div class="sp-cfg-tile__desc">Certificados A1/A3 para assinatura de documentos e relatórios oficiais.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">ICP-Brasil</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

        </div>
    </div>

    <!-- ── Integrações & Dados ───────────────────────────────── -->
    <div class="sp-cfg-section">
        <div class="sp-cfg-section__header">
            <div class="sp-cfg-section__header-icon" style="background:var(--sp-warning-light);color:var(--sp-warning);">
                <i class="bi bi-plug-fill"></i>
            </div>
            <h2 class="sp-cfg-section__header-title">Integrações &amp; Dados</h2>
        </div>
        <div class="sp-cfg-grid">

            <a href="<?= sp_admin_settings_integrations_url() ?>" class="sp-cfg-tile sp-cfg-tile--amber">
                <div class="sp-cfg-tile__icon"><i class="bi bi-plug-fill"></i></div>
                <div class="sp-cfg-tile__title">Integrações</div>
                <div class="sp-cfg-tile__desc">APIs externas, reconhecimento facial (DeepFace), biometria e geolocalização.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">APIs</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= sp_admin_settings_backup_url() ?>" class="sp-cfg-tile sp-cfg-tile--green">
                <div class="sp-cfg-tile__icon"><i class="bi bi-cloud-arrow-down-fill"></i></div>
                <div class="sp-cfg-tile__title">Backup</div>
                <div class="sp-cfg-tile__desc">Agendamento automático, retenção e download de backups do banco de dados.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Dados</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

        </div>
    </div>

    <!-- ── Sistema & Diagnóstico ─────────────────────────────── -->
    <div class="sp-cfg-section" style="margin-bottom:0">
        <div class="sp-cfg-section__header">
            <div class="sp-cfg-section__header-icon" style="background:var(--sp-gray-200);color:var(--sp-text-secondary);">
                <i class="bi bi-cpu-fill"></i>
            </div>
            <h2 class="sp-cfg-section__header-title">Sistema &amp; Diagnóstico</h2>
        </div>
        <div class="sp-cfg-grid">

            <a href="<?= route_to('admin.health') ?>" class="sp-cfg-tile sp-cfg-tile--teal">
                <div class="sp-cfg-tile__icon"><i class="bi bi-heart-pulse-fill"></i></div>
                <div class="sp-cfg-tile__title">Saúde do Sistema</div>
                <div class="sp-cfg-tile__desc">Monitore banco de dados, filas, cache e serviços em tempo real.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Status</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

            <a href="<?= route_to('admin.health.diagnostics') ?>" class="sp-cfg-tile sp-cfg-tile--blue">
                <div class="sp-cfg-tile__icon"><i class="bi bi-cpu-fill"></i></div>
                <div class="sp-cfg-tile__title">Diagnóstico</div>
                <div class="sp-cfg-tile__desc">Informações técnicas: PHP, extensões, permissões e configurações do ambiente.</div>
                <div class="sp-cfg-tile__footer">
                    <span class="sp-cfg-tile__tag">Técnico</span>
                    <i class="bi bi-arrow-right sp-cfg-tile__arrow"></i>
                </div>
            </a>

        </div>
    </div>

</div>
<?= $this->endSection() ?>
