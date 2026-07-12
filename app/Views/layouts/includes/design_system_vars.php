<?php
/**
 * Design System — CSS Variable Injection
 * Reads saved colors from the settings table and injects them as CSS custom
 * properties so the entire UI picks up the admin's personalization choices.
 *
 * Variables emitted:
 *   --sp-primary / --sp-secondary / --sp-success / --sp-warning
 *   --sp-danger  / --sp-info
 * Also overrides Bootstrap's semantic vars so btn-primary, alert-success, etc.
 * use the custom palette automatically.
 */

$_spColorDefaults = [
    'primary_color'   => '#4fa14f',
    'secondary_color' => '#3a7a3a',
    'success_color'   => '#1abc9c',
    'warning_color'   => '#f0c43f',
    'danger_color'    => '#e74c3c',
    'info_color'      => '#5b73e8',
];

$_spColors = $_spColorDefaults;
try {
    /** @var \App\Models\SettingModel $_spSm */
    $_spSm = model(\App\Models\SettingModel::class);
    foreach ($_spColorDefaults as $_spKey => $_spDefault) {
        $_spVal = $_spSm->get($_spKey, $_spDefault);
        if (is_string($_spVal) && preg_match('/^#[0-9A-Fa-f]{6}$/', $_spVal)) {
            $_spColors[$_spKey] = $_spVal;
        }
    }
} catch (\Throwable $_spEx) { /* use defaults */ }

// Convert hex #rrggbb → "r, g, b" for Bootstrap RGB variables
function _sp_hex2rgb(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return '0, 0, 0';
    return implode(', ', [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))]);
}

$_sp = [
    'primary'   => $_spColors['primary_color'],
    'secondary' => $_spColors['secondary_color'],
    'success'   => $_spColors['success_color'],
    'warning'   => $_spColors['warning_color'],
    'danger'    => $_spColors['danger_color'],
    'info'      => $_spColors['info_color'],
];
?>
<style id="sp-design-vars">
:root {
    /* SupportPONTO semantic colors */
    --sp-primary:   <?= esc($_sp['primary'])   ?>;
    --sp-secondary: <?= esc($_sp['secondary']) ?>;
    --sp-success:   <?= esc($_sp['success'])   ?>;
    --sp-warning:   <?= esc($_sp['warning'])   ?>;
    --sp-danger:    <?= esc($_sp['danger'])     ?>;
    --sp-info:      <?= esc($_sp['info'])       ?>;

    /* Bootstrap 5 color overrides — makes btn-primary, alert-success, etc.
       use the custom palette automatically */
    --bs-primary:         <?= esc($_sp['primary'])   ?>;
    --bs-secondary:       <?= esc($_sp['secondary']) ?>;
    --bs-success:         <?= esc($_sp['success'])   ?>;
    --bs-warning:         <?= esc($_sp['warning'])   ?>;
    --bs-danger:          <?= esc($_sp['danger'])     ?>;
    --bs-info:            <?= esc($_sp['info'])       ?>;

    --bs-primary-rgb:     <?= esc(_sp_hex2rgb($_sp['primary']))   ?>;
    --bs-secondary-rgb:   <?= esc(_sp_hex2rgb($_sp['secondary'])) ?>;
    --bs-success-rgb:     <?= esc(_sp_hex2rgb($_sp['success']))   ?>;
    --bs-warning-rgb:     <?= esc(_sp_hex2rgb($_sp['warning']))   ?>;
    --bs-danger-rgb:      <?= esc(_sp_hex2rgb($_sp['danger']))    ?>;
    --bs-info-rgb:        <?= esc(_sp_hex2rgb($_sp['info']))      ?>;

    /* DesignSystem legacy aliases */
    --color-primary:   <?= esc($_sp['primary'])   ?>;
    --color-secondary: <?= esc($_sp['secondary']) ?>;
    --color-success:   <?= esc($_sp['success'])   ?>;
    --color-warning:   <?= esc($_sp['warning'])   ?>;
    --color-danger:    <?= esc($_sp['danger'])     ?>;
    --color-info:      <?= esc($_sp['info'])       ?>;
}

/* Make Bootstrap btn-primary and similar components use the --bs-primary
   variable so the custom color flows through automatically */
.btn-primary {
    --bs-btn-bg:           var(--sp-primary);
    --bs-btn-border-color: var(--sp-primary);
    --bs-btn-hover-bg:     color-mix(in srgb, var(--sp-primary) 85%, #000);
    --bs-btn-hover-border-color: color-mix(in srgb, var(--sp-primary) 85%, #000);
    --bs-btn-active-bg:    color-mix(in srgb, var(--sp-primary) 75%, #000);
}
.btn-outline-primary {
    --bs-btn-color:        var(--sp-primary);
    --bs-btn-border-color: var(--sp-primary);
    --bs-btn-hover-bg:     var(--sp-primary);
    --bs-btn-hover-border-color: var(--sp-primary);
}
.btn-success {
    --bs-btn-bg:           var(--sp-success);
    --bs-btn-border-color: var(--sp-success);
}
.btn-danger {
    --bs-btn-bg:           var(--sp-danger);
    --bs-btn-border-color: var(--sp-danger);
}
.btn-warning {
    --bs-btn-bg:           var(--sp-warning);
    --bs-btn-border-color: var(--sp-warning);
}
</style>
