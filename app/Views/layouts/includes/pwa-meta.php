<?php
/**
 * PWA Meta Tags - SupportPONTO
 * Favicon e logo totalmente dinamicos a partir das configuracoes.
 */

$themeColor = '#9DB89D';
try {
    if (class_exists('\App\Models\SettingModel')) {
        $sm = new \App\Models\SettingModel();
        $themeColor = $sm->get('primary_color', $themeColor) ?: $themeColor;
    }
} catch (\Throwable $e) { /* default */ }

$baseUrl    = base_url();
$iconStatic = $baseUrl . '/assets/img';

// Closure local - sem risco de redeclaracao em includes multiplos
$spFavResolve = static function (string $key, string $fallback): string {
    try {
        $raw = system_setting($key, '');
        if (empty($raw)) { $raw = system_setting('favicon_path', ''); }
        if (empty($raw)) { $raw = system_setting('company_favicon', ''); }
        if (!empty($raw)) {
            if (preg_match('#^(https?:)?//#i', $raw)) { return $raw; }
            $abs = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, ltrim($raw, '/'));
            if (is_file($abs)) { return base_url(ltrim($raw, '/')) . '?v=' . filemtime($abs); }
        }
    } catch (\Throwable $e) { /* fallback */ }
    return $fallback;
};

$favicon16  = $spFavResolve('favicon_path_16',  $iconStatic . '/favicon.png');
$favicon32  = $spFavResolve('favicon_path_32',  $iconStatic . '/favicon.png');
$favicon48  = $spFavResolve('favicon_path_48',  $iconStatic . '/favicon.png');
$favicon180 = $spFavResolve('favicon_path_180', $iconStatic . '/apple-touch-icon.png');
$logoUrl    = support_logo_url('small');
?>

<!-- PWA Meta -->
<meta name="application-name" content="SupportPONTO">
<meta name="application-version" content="<?= esc(app_version(false)) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="theme-color" content="<?= esc($themeColor) ?>">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#1a1a2e">

<!-- Manifest -->
<link rel="manifest" href="<?= $baseUrl ?>/manifest.webmanifest?v=<?= esc(app_version(false)) ?>">

<!-- Favicons dinamicos -->
<link rel="icon" type="image/png" sizes="16x16"  href="<?= esc($favicon16) ?>">
<link rel="icon" type="image/png" sizes="32x32"  href="<?= esc($favicon32) ?>">
<link rel="icon" type="image/png" sizes="48x48"  href="<?= esc($favicon48) ?>">
<link rel="shortcut icon" href="<?= esc($favicon32) ?>">

<!-- Apple iOS -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="PONTO">
<meta name="format-detection" content="telephone=no">

<!-- Apple Touch Icons dinamicos -->
<link rel="apple-touch-icon"              href="<?= esc($favicon180) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= esc($favicon180) ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?= esc($favicon48) ?>">
<link rel="apple-touch-icon" sizes="120x120" href="<?= esc($favicon48) ?>">

<!-- MS Tiles -->
<meta name="msapplication-TileColor" content="<?= esc($themeColor) ?>">
<meta name="msapplication-TileImage" content="<?= esc($favicon48) ?>">
<meta name="msapplication-config" content="<?= $baseUrl ?>/browserconfig.xml">

<!-- Open Graph -->
<meta property="og:type"      content="website">
<meta property="og:site_name" content="SupportPONTO">
<meta property="og:title"     content="SupportPONTO - Registro de Ponto">
<meta property="og:image"     content="<?= esc($logoUrl) ?>">
<meta property="og:url"       content="<?= current_url() ?>">

<!-- SEO -->
<meta name="description" content="Sistema completo de registro de ponto eletronico">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= current_url() ?>">

<!-- Preconnect -->
<link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
