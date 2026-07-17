<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Templates de Termos de Consentimento<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$consentTypes = $consentTypes ?? [];
$activeType   = $activeType   ?? 'biometric_face';
$allTerms     = $allTerms     ?? [];
$activeTerm   = $activeTerm   ?? null;
$allVersions  = $allVersions  ?? [];

$typeMeta = [
    'biometric_face'        => ['bi-fingerprint',      'primary',  'Gate: cadastro facial do colaborador'],
    'biometric_fingerprint' => ['bi-fingerprint',      'info',     'Gate: cadastro de digital do colaborador'],
    'geolocation'           => ['bi-geo-alt-fill',     'success',  'Gate: primeiro uso de ponto GPS'],
    'data_processing'       => ['bi-person-fill-lock', 'tertiary', 'Gate: primeiro acesso ao sistema'],
    'marketing'             => ['bi-megaphone-fill',   'warning',  'Auto-serviço: página LGPD do colaborador'],
    'data_sharing'          => ['bi-share-fill',       'muted',    'Auto-serviço: página LGPD do colaborador'],
];
$integrationMap = [
    'biometric_face'        => ['url' => 'biometric/enroll-for/{id}',         'status' => 'integrated',  'label' => 'Gate ativo'],
    'biometric_fingerprint' => ['url' => 'biometric/fingerprint/enroll/{id}', 'status' => 'integrated',  'label' => 'Gate ativo'],
    'geolocation'           => ['url' => 'lgpd/consents',                    'status' => 'selfservice', 'label' => 'Auto-serviço'],
    'data_processing'       => ['url' => 'lgpd/consents',                    'status' => 'selfservice', 'label' => 'Auto-serviço'],
    'marketing'             => ['url' => 'lgpd/consents',                    'status' => 'selfservice', 'label' => 'Auto-serviço'],
    'data_sharing'          => ['url' => 'lgpd/consents',                    'status' => 'selfservice', 'label' => 'Auto-serviço'],
];

$totalTypes  = count($consentTypes);
$coveredCount = 0;
foreach (array_keys($consentTypes) as $t) {
    if (!empty($allTerms[$t]['active'])) { $coveredCount++; }
}
$coveragePct = $totalTypes > 0 ? round(($coveredCount / $totalTypes) * 100) : 0;

/**
 * Divide o texto integro do termo em secoes (titulo numerado + paragrafos),
 * pra deixar de exibir como um dump monoespacado e renderizar como um
 * documento juridico de verdade. Puramente cosmetico -- o texto salvo no
 * banco (esc()ado antes de sair) nao muda em nada.
 */
if (!function_exists('sp_lgpd_parse_sections')) {
    function sp_lgpd_parse_sections(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $sections = [];
        $current = ['heading' => null, 'body' => []];
        $hasContent = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $looksLikeHeading = $trimmed !== ''
                && preg_match('/^\d+\.\s*.+$/u', $trimmed) === 1
                && mb_strtoupper($trimmed, 'UTF-8') === $trimmed;

            if ($looksLikeHeading) {
                if ($hasContent) {
                    $sections[] = $current;
                }
                $current = ['heading' => $trimmed, 'body' => []];
                $hasContent = true;
                continue;
            }

            $current['body'][] = $line;
            $hasContent = true;
        }
        if ($hasContent) {
            $sections[] = $current;
        }

        return $sections;
    }
}
?>

<div class="lgpd-vault">

    <?= view('components/page_header', [
        'title'    => 'Templates de Termos de Consentimento',
        'subtitle' => 'Cada versão publicada vira registro legal — os colaboradores voltam a ser solicitados a aceitar.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [],
    ]) ?>

    <!-- Faixa de cobertura -->
    <div class="lgpd-coverage">
        <div class="lgpd-coverage__ring" style="--pct: <?= (int) $coveragePct ?>">
            <span class="lgpd-coverage__num"><?= (int) $coveragePct ?>%</span>
        </div>
        <div class="lgpd-coverage__text">
            <div class="lgpd-coverage__title">Cobertura de consentimento LGPD</div>
            <div class="lgpd-coverage__sub">
                <strong><?= (int) $coveredCount ?></strong> de <strong><?= (int) $totalTypes ?></strong> tipos de dado com termo publicado
            </div>
        </div>
        <div class="lgpd-coverage__legend">
            <span><i class="bi bi-square-fill"></i> Gate obrigatório</span>
            <span><i class="bi bi-square-fill"></i> Auto-serviço</span>
        </div>
    </div>

    <div class="lgpd-layout">

        <!-- Trilho de tipos -->
        <aside class="lgpd-rail" aria-label="Tipos de termo">
            <?php foreach ($consentTypes as $type => $label): ?>
                <?php
                    [$icon, $accent, $desc] = $typeMeta[$type] ?? ['bi-file-text', 'muted', ''];
                    $integration = $integrationMap[$type] ?? [];
                    $hasTerm = !empty($allTerms[$type]['active']);
                    $isActive = $activeType === $type;
                ?>
                <a href="?type=<?= esc($type) ?>"
                   class="lgpd-rail__item <?= $isActive ? 'is-active' : '' ?>"
                   style="--accent: var(--sp-<?= $accent === 'muted' ? 'text-muted' : $accent ?>)">
                    <span class="lgpd-rail__icon"><i class="bi <?= esc($icon) ?>"></i></span>
                    <span class="lgpd-rail__text">
                        <strong><?= esc($label) ?></strong>
                        <small><?= esc($desc) ?></small>
                    </span>
                    <span class="lgpd-rail__status">
                        <?php if ($hasTerm): ?>
                            <span class="lgpd-dot lgpd-dot--ok" title="v<?= esc($allTerms[$type]['active']->version) ?> ativo"></span>
                        <?php else: ?>
                            <span class="lgpd-dot lgpd-dot--warn" title="Sem termo publicado"></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </aside>

        <!-- Leitor de documento -->
        <main class="lgpd-main">
            <?php
                [$icon, $accent, $desc] = $typeMeta[$activeType] ?? ['bi-file-text', 'muted', ''];
                $accentVar = 'var(--sp-' . ($accent === 'muted' ? 'text-muted' : $accent) . ')';
                $typeLabel = $consentTypes[$activeType] ?? $activeType;
                $integration = $integrationMap[$activeType] ?? [];
            ?>
            <div class="lgpd-paper" style="--accent: <?= $accentVar ?>">
                <?php if ($activeTerm): ?>
                    <header class="lgpd-paper__head">
                        <div class="lgpd-paper__kicker">
                            <i class="bi <?= esc($icon) ?>"></i>
                            <span>TERMO ATIVO</span>
                            <span class="lgpd-paper__version">v<?= esc($activeTerm->version) ?></span>
                        </div>
                        <h2 class="lgpd-paper__title"><?= esc($activeTerm->title) ?></h2>
                        <div class="lgpd-paper__meta">
                            <?php if ($activeTerm->legal_basis): ?>
                                <span class="lgpd-pill"><i class="bi bi-book me-1"></i><?= esc($activeTerm->legal_basis) ?></span>
                            <?php endif; ?>
                            <span class="lgpd-paper__date">
                                <i class="bi bi-calendar-check me-1"></i>
                                Publicado em <?= $activeTerm->created_at ? date('d/m/Y \à\s H:i', strtotime($activeTerm->created_at)) : '—' ?>
                            </span>
                            <span class="lgpd-paper__integration">
                                <i class="bi bi-diagram-3 me-1"></i><?= esc($integration['label'] ?? '') ?>
                            </span>
                        </div>
                    </header>

                    <div class="lgpd-paper__body">
                        <?php if (trim(strip_tags($activeTerm->body)) !== trim($activeTerm->body)): ?>
                            <?php // Termo redigido no editor rico -- ja sanitizado (ConsentTermSanitizerService) no momento do save, seguro pra ecoar direto. ?>
                            <div class="lgpd-rich-body"><?= $activeTerm->body ?></div>
                        <?php else: ?>
                            <?php foreach (sp_lgpd_parse_sections($activeTerm->body) as $section): ?>
                                <?php if ($section['heading']): ?>
                                    <section class="lgpd-clause">
                                        <h4 class="lgpd-clause__title"><?= esc($section['heading']) ?></h4>
                                        <?php foreach (array_filter($section['body'], fn($l) => trim($l) !== '') as $para): ?>
                                            <p><?= esc(trim($para)) ?></p>
                                        <?php endforeach; ?>
                                    </section>
                                <?php else: ?>
                                    <p class="lgpd-clause__intro"><?php foreach ($section['body'] as $l): ?><?= esc(trim($l)) ?><br><?php endforeach; ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($allVersions)): ?>
                        <footer class="lgpd-paper__foot">
                            <div class="lgpd-timeline">
                                <span class="lgpd-timeline__label">Histórico</span>
                                <div class="lgpd-timeline__track">
                                    <?php foreach ($allVersions as $v): ?>
                                        <span class="lgpd-timeline__node <?= $v->active ? 'is-active' : '' ?>">
                                            v<?= esc($v->version) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </footer>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="lgpd-empty">
                        <i class="bi bi-file-earmark-x"></i>
                        <p class="fw-semibold mb-1">Nenhum termo publicado para "<?= esc($typeLabel) ?>"</p>
                        <p class="small mb-0">Use o compositor abaixo para publicar o primeiro termo.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Compositor de nova versão -->
            <details class="lgpd-composer">
                <summary>
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Publicar nova versão — <?= esc($typeLabel) ?></span>
                    <i class="bi bi-chevron-down lgpd-composer__chevron"></i>
                </summary>
                <div class="lgpd-composer__body">
                    <div class="lgpd-notice">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        Ao publicar, o termo atual será desativado. Colaboradores sem aceite na nova versão serão solicitados novamente nos pontos de integração.
                    </div>
                    <form action="<?= site_url('settings/consent-terms/save') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="term_type" value="<?= esc($activeType) ?>">

                        <div class="mb-3">
                            <label class="lgpd-label">Título do Termo</label>
                            <input type="text" name="title" class="lgpd-input" required maxlength="255"
                                   value="<?= esc($activeTerm->title ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="lgpd-label">Base Legal</label>
                            <input type="text" name="legal_basis" class="lgpd-input" maxlength="500"
                                   value="<?= esc($activeTerm->legal_basis ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="lgpd-label">Texto Íntegro do Termo</label>
                            <textarea name="body" id="lgpdBodyEditor" class="lgpd-textarea" rows="14" required><?= esc($activeTerm->body ?? '') ?></textarea>
                            <div class="lgpd-hint">
                                Use o botão <i class="bi bi-code-slash"></i> na barra do editor pra ver/editar o código-fonte (HTML) diretamente.
                                Este texto será exibido ao colaborador no momento do aceite e gravado no registro.
                            </div>
                        </div>
                        <button type="submit" class="lgpd-submit"
                                onclick="return confirm('Confirma a publicação de nova versão? O termo atual será desativado.')">
                            <i class="bi bi-cloud-upload-fill me-2"></i>Publicar Nova Versão
                        </button>
                    </form>
                </div>
            </details>
        </main>
    </div>

    <!-- Pipeline de integração -->
    <div class="lgpd-pipeline">
        <h3 class="lgpd-pipeline__title"><i class="bi bi-diagram-3-fill me-2"></i>Mapa de Integração dos Termos</h3>
        <div class="lgpd-pipeline__row">
            <?php foreach ($consentTypes as $type => $label): ?>
                <?php
                    [$tIcon, $tAccent] = $typeMeta[$type] ?? ['bi-file-text', 'muted'];
                    $tAccentVar = 'var(--sp-' . ($tAccent === 'muted' ? 'text-muted' : $tAccent) . ')';
                    $integration = $integrationMap[$type] ?? [];
                    $hasTerm = !empty($allTerms[$type]['active']);
                    $version = $hasTerm ? 'v' . $allTerms[$type]['active']->version : null;
                    $isGate = ($integration['status'] ?? '') === 'integrated';
                ?>
                <div class="lgpd-node <?= $activeType === $type ? 'is-active' : '' ?>" style="--accent: <?= $tAccentVar ?>">
                    <a href="?type=<?= esc($type) ?>" class="lgpd-node__link">
                        <span class="lgpd-node__icon"><i class="bi <?= esc($tIcon) ?>"></i></span>
                        <span class="lgpd-node__label"><?= esc($label) ?></span>
                        <span class="lgpd-node__version">
                            <?= $version ? esc($version) : '<span class="lgpd-node__missing">sem termo</span>' ?>
                        </span>
                        <span class="lgpd-node__mode <?= $isGate ? 'is-gate' : 'is-self' ?>">
                            <i class="bi <?= $isGate ? 'bi-shield-lock-fill' : 'bi-person-check-fill' ?>"></i>
                            <?= $isGate ? 'Gate' : 'Auto-serviço' ?>
                        </span>
                        <code class="lgpd-node__url"><?= esc($integration['url'] ?? '-') ?></code>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<style>
.lgpd-vault { --lgpd-radius: var(--sp-radius-lg, 10px); }

/* Faixa de cobertura */
.lgpd-coverage {
    display: flex; align-items: center; gap: 1.25rem;
    background: var(--sp-bg-surface); border: 1px solid var(--sp-border);
    border-radius: var(--lgpd-radius); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;
}
.lgpd-coverage__ring {
    --pct: 0; width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: conic-gradient(var(--sp-success) calc(var(--pct) * 1%), var(--sp-border) 0);
    position: relative;
}
.lgpd-coverage__ring::before {
    content: ''; position: absolute; inset: 6px; border-radius: 50%; background: var(--sp-bg-surface);
}
.lgpd-coverage__num { position: relative; font-weight: 700; font-size: .95rem; color: var(--sp-text-primary); }
.lgpd-coverage__text { flex: 1; min-width: 0; }
.lgpd-coverage__title { font-weight: 600; color: var(--sp-text-primary); font-size: .95rem; }
.lgpd-coverage__sub { color: var(--sp-text-secondary); font-size: .85rem; margin-top: .1rem; }
.lgpd-coverage__legend { display: flex; gap: 1rem; font-size: .75rem; color: var(--sp-text-muted); flex-shrink: 0; }
.lgpd-coverage__legend span:first-child i { color: var(--sp-primary); }
.lgpd-coverage__legend span:last-child i { color: var(--sp-info); }
.lgpd-coverage__legend i { font-size: .55rem; margin-right: .25rem; vertical-align: middle; }

/* Layout de duas colunas */
.lgpd-layout { display: grid; grid-template-columns: 300px 1fr; gap: 1.25rem; align-items: start; }
@media (max-width: 991px) { .lgpd-layout { grid-template-columns: 1fr; } }

/* Trilho lateral */
.lgpd-rail {
    display: flex; flex-direction: column; gap: .35rem;
    background: var(--sp-bg-surface); border: 1px solid var(--sp-border);
    border-radius: var(--lgpd-radius); padding: .6rem;
    position: sticky; top: 1rem;
}
.lgpd-rail__item {
    display: flex; align-items: center; gap: .65rem; padding: .6rem .65rem;
    border-radius: var(--sp-radius-md, 6px); text-decoration: none; color: var(--sp-text-primary);
    border-left: 3px solid transparent; transition: var(--sp-transition, all .15s ease);
}
.lgpd-rail__item:hover { background: var(--sp-bg-page); }
.lgpd-rail__item.is-active { background: var(--sp-bg-page); border-left-color: var(--accent); }
.lgpd-rail__icon {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: color-mix(in srgb, var(--accent) 16%, transparent); color: var(--accent);
}
.lgpd-rail__text { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
.lgpd-rail__text strong { font-size: .85rem; }
.lgpd-rail__text small { font-size: .72rem; color: var(--sp-text-muted); }
.lgpd-rail__status { flex-shrink: 0; }
.lgpd-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; }
.lgpd-dot--ok { background: var(--sp-success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--sp-success) 20%, transparent); }
.lgpd-dot--warn { background: var(--sp-warning); box-shadow: 0 0 0 3px color-mix(in srgb, var(--sp-warning) 20%, transparent); }

/* Documento */
.lgpd-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
.lgpd-paper {
    background: var(--sp-bg-surface); border: 1px solid var(--sp-border); border-top: 3px solid var(--accent);
    border-radius: var(--lgpd-radius); box-shadow: var(--sp-shadow-card); overflow: hidden;
}
.lgpd-paper__head { padding: 1.5rem 1.75rem 1.1rem; border-bottom: 1px dashed var(--sp-border); }
.lgpd-paper__kicker {
    display: flex; align-items: center; gap: .4rem; font-size: .7rem; font-weight: 700;
    letter-spacing: .08em; color: var(--accent); text-transform: uppercase; margin-bottom: .5rem;
}
.lgpd-paper__version {
    background: color-mix(in srgb, var(--accent) 18%, transparent); color: var(--accent);
    padding: .1rem .5rem; border-radius: var(--sp-radius-full, 999px); font-size: .68rem;
}
.lgpd-paper__title { font-size: 1.25rem; font-weight: 700; color: var(--sp-text-primary); margin: 0 0 .65rem; }
.lgpd-paper__meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
.lgpd-pill {
    background: var(--sp-info-light); color: var(--sp-info); border-radius: var(--sp-radius-full, 999px);
    padding: .25rem .7rem; font-size: .75rem; font-weight: 500;
}
.lgpd-paper__date, .lgpd-paper__integration { font-size: .75rem; color: var(--sp-text-muted); }

.lgpd-paper__body { padding: 1.5rem 1.75rem; max-height: 480px; overflow-y: auto; }
.lgpd-clause { margin-bottom: 1.35rem; }
.lgpd-clause:last-child { margin-bottom: 0; }
.lgpd-clause__title {
    font-size: .8rem; font-weight: 700; letter-spacing: .03em; color: var(--accent);
    margin-bottom: .45rem; padding-bottom: .35rem; border-bottom: 1px solid var(--sp-border-soft);
}
.lgpd-clause p { font-size: .85rem; color: var(--sp-text-primary); line-height: 1.65; margin-bottom: .5rem; }
.lgpd-clause p:last-child { margin-bottom: 0; }
.lgpd-clause__intro { font-size: .85rem; color: var(--sp-text-secondary); font-style: italic; text-align: center; margin-bottom: 1.35rem; }

.lgpd-paper__foot { padding: 1rem 1.75rem; border-top: 1px solid var(--sp-border); background: var(--sp-bg-page); }
.lgpd-timeline { display: flex; align-items: center; gap: .75rem; }
.lgpd-timeline__label { font-size: .72rem; font-weight: 600; color: var(--sp-text-muted); text-transform: uppercase; letter-spacing: .04em; flex-shrink: 0; }
.lgpd-timeline__track { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.lgpd-timeline__node {
    font-size: .72rem; padding: .2rem .6rem; border-radius: var(--sp-radius-full, 999px);
    background: var(--sp-border-soft); color: var(--sp-text-muted); position: relative;
}
.lgpd-timeline__node.is-active { background: color-mix(in srgb, var(--sp-success) 18%, transparent); color: var(--sp-success); font-weight: 600; }
.lgpd-timeline__node:not(:last-child)::after {
    content: ''; position: absolute; right: -.5rem; top: 50%; width: .4rem; height: 1px; background: var(--sp-border);
}

.lgpd-empty { text-align: center; padding: 3.5rem 1.5rem; color: var(--sp-text-muted); }
.lgpd-empty i { font-size: 2.5rem; display: block; margin-bottom: 1rem; opacity: .5; }

/* Compositor */
.lgpd-composer { background: var(--sp-bg-surface); border: 1px dashed var(--sp-border); border-radius: var(--lgpd-radius); }
.lgpd-composer summary {
    cursor: pointer; list-style: none; padding: 1rem 1.5rem; display: flex; align-items: center; gap: .6rem;
    font-weight: 600; color: var(--sp-text-primary); font-size: .9rem;
}
.lgpd-composer summary::-webkit-details-marker { display: none; }
.lgpd-composer summary i.bi-plus-circle-fill { color: var(--sp-primary); }
.lgpd-composer__chevron { margin-left: auto; transition: transform .2s ease; color: var(--sp-text-muted); }
.lgpd-composer[open] .lgpd-composer__chevron { transform: rotate(180deg); }
.lgpd-composer__body { padding: 0 1.5rem 1.5rem; }

.lgpd-notice {
    background: var(--sp-warning-light); color: var(--sp-warning); border-radius: var(--sp-radius-md, 6px);
    padding: .75rem 1rem; font-size: .8rem; margin-bottom: 1.1rem;
}
.lgpd-label { display: block; font-size: .78rem; font-weight: 600; color: var(--sp-text-secondary); margin-bottom: .35rem; }
.lgpd-input, .lgpd-textarea {
    width: 100%; background: var(--sp-bg-page); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-md, 6px);
    color: var(--sp-text-primary); padding: .55rem .75rem; font-size: .85rem; font-family: var(--sp-font-family);
}
.lgpd-textarea { font-family: 'SFMono-Regular', Consolas, monospace; line-height: 1.6; resize: vertical; }
.lgpd-input:focus, .lgpd-textarea:focus { outline: none; border-color: var(--sp-primary); box-shadow: 0 0 0 3px var(--sp-primary-light); }
.lgpd-hint { font-size: .72rem; color: var(--sp-text-muted); margin-top: .35rem; }
.lgpd-submit {
    width: 100%; background: var(--sp-primary); color: #fff; border: none; border-radius: var(--sp-radius-md, 6px);
    padding: .7rem; font-weight: 600; font-size: .88rem; transition: var(--sp-transition, all .15s ease);
}
.lgpd-submit:hover { background: var(--sp-primary-dark); }

/* Pipeline de integração */
.lgpd-pipeline { margin-top: 1.75rem; }
.lgpd-pipeline__title { font-size: .95rem; font-weight: 700; color: var(--sp-text-primary); margin-bottom: 1rem; }
.lgpd-pipeline__row { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .85rem; }
.lgpd-node {
    background: var(--sp-bg-surface); border: 1px solid var(--sp-border); border-radius: var(--sp-radius-md, 6px);
    transition: var(--sp-transition, all .15s ease); position: relative; overflow: hidden;
}
.lgpd-node::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--accent); }
.lgpd-node.is-active { box-shadow: 0 0 0 2px var(--accent); }
.lgpd-node:hover { transform: translateY(-2px); box-shadow: var(--sp-shadow-md); }
.lgpd-node__link { display: flex; flex-direction: column; gap: .35rem; padding: 1rem; text-decoration: none; color: inherit; }
.lgpd-node__icon { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: color-mix(in srgb, var(--accent) 16%, transparent); color: var(--accent); margin-bottom: .15rem; }
.lgpd-node__label { font-weight: 600; font-size: .85rem; color: var(--sp-text-primary); }
.lgpd-node__version { font-size: .72rem; color: var(--sp-success); font-weight: 600; }
.lgpd-node__missing { color: var(--sp-warning); font-weight: 500; }
.lgpd-node__mode {
    display: inline-flex; align-items: center; gap: .3rem; width: fit-content; font-size: .68rem;
    padding: .15rem .5rem; border-radius: var(--sp-radius-full, 999px);
}
.lgpd-node__mode.is-gate { background: var(--sp-danger-light); color: var(--sp-danger); }
.lgpd-node__mode.is-self { background: var(--sp-info-light); color: var(--sp-info); }
.lgpd-node__url { font-size: .68rem; color: var(--sp-text-muted); word-break: break-all; }

/* Termo redigido no editor rico -- tipografia consistente com .lgpd-clause */
.lgpd-rich-body { font-size: .85rem; color: var(--sp-text-primary); line-height: 1.65; }
.lgpd-rich-body h3, .lgpd-rich-body h4, .lgpd-rich-body h5, .lgpd-rich-body h6 {
    color: var(--accent); font-size: .85rem; font-weight: 700; margin: 1.1rem 0 .45rem;
}
.lgpd-rich-body h3:first-child, .lgpd-rich-body h4:first-child { margin-top: 0; }
.lgpd-rich-body p { margin-bottom: .65rem; }
.lgpd-rich-body ul, .lgpd-rich-body ol { margin-bottom: .65rem; padding-left: 1.4rem; }
.lgpd-rich-body a { color: var(--sp-info); }
.lgpd-rich-body table { width: 100%; border-collapse: collapse; margin-bottom: .65rem; font-size: .8rem; }
.lgpd-rich-body th, .lgpd-rich-body td { border: 1px solid var(--sp-border); padding: .4rem .6rem; }
.lgpd-rich-body blockquote { border-left: 3px solid var(--accent); padding-left: .9rem; color: var(--sp-text-secondary); margin: .65rem 0; }

/* Summernote adaptado ao tema escuro/claro do app via tokens --sp-* */
.note-editor.note-frame { background: var(--sp-bg-page); border: 1px solid var(--sp-border) !important; border-radius: var(--sp-radius-md, 6px); }
.note-editor .note-toolbar { background: var(--sp-bg-surface); border-bottom: 1px solid var(--sp-border) !important; }
.note-editor .note-editing-area .note-editable { background: var(--sp-bg-page); color: var(--sp-text-primary); }
.note-editor .note-statusbar { background: var(--sp-bg-surface); border-top: 1px solid var(--sp-border); }
.note-btn { background: var(--sp-bg-surface) !important; border-color: var(--sp-border) !important; color: var(--sp-text-primary) !important; }
.note-btn:hover { background: var(--sp-bg-page) !important; }
.note-codable { background: #1c1f26 !important; color: #cccccc !important; font-family: 'SFMono-Regular', Consolas, monospace !important; }
</style>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php // Summernote 0.8.x depende de jQuery; a app nao carrega jQuery globalmente, entao carregamos aqui mesmo. ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.20/summernote-bs5.min.js"></script>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    var el = document.getElementById('lgpdBodyEditor');
    if (!el || typeof jQuery === 'undefined' || !jQuery.fn.summernote) { return; }

    jQuery(el).summernote({
        height: 320,
        lang: 'pt-BR',
        placeholder: 'Digite o texto integral do termo...',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'table']],
            ['view', ['codeview']],
        ],
    });
})();
</script>
<?= $this->endSection() ?>
