<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Acesso Negado</title>
    <style>
        :root { color-scheme:light; --brand:#17834a; --brand-soft:#1f9d57; --text:#18202b; --muted:#8a93a0; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1.5rem; background:#f4f6f8; font-family:Inter,"Segoe UI",Arial,sans-serif; }
        .error-card { width:min(620px,100%); background:#ffffff; border:1px solid #e4e8ed; border-radius:16px; box-shadow:0 12px 32px rgba(16,24,40,.10); padding:3rem 2rem; text-align:center; }
        .badge { display:inline-flex; align-items:center; justify-content:center; width:84px; height:84px; border-radius:999px; background:rgba(31,157,87,.12); color:var(--brand); font-size:2.5rem; font-weight:800; margin-bottom:1.25rem; }
        h1 { margin:0; color:var(--text); font-size:clamp(3rem,9vw,6rem); line-height:1; letter-spacing:-.06em; }
        h2 { margin:.75rem 0 .5rem; color:var(--text); font-size:1.6rem; }
        p { margin:0 auto 1.75rem; color:var(--muted); line-height:1.65; max-width:460px; }
        .actions { display:flex; gap:.75rem; justify-content:center; flex-wrap:wrap; }
        a { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:.75rem 1.25rem; border-radius:12px; text-decoration:none; font-weight:700; transition:background .15s; }
        .primary { color:#fff; background:var(--brand); }
        .primary:hover { color:#fff; background:var(--brand-soft); }
        .secondary { color:var(--brand); background:rgba(31,157,87,.12); }
        .secondary:hover { background:rgba(31,157,87,.18); }
    </style>
</head>
<body>
    <?php
        $description = $message ?? 'Você não tem permissão para acessar esta área.';
        $target = $backUrl ?? base_url('/dashboard');
    ?>
    <main class="error-card" role="main" aria-labelledby="title-403">
        <div class="badge" aria-hidden="true">!</div>
        <h1>403</h1>
        <h2 id="title-403">Acesso negado</h2>
        <p><?= esc($description) ?></p>
        <div class="actions">
            <a class="primary" href="<?= esc($target) ?>">Voltar para área permitida</a>
            <a class="secondary" href="<?= base_url('/') ?>">Ir para o início</a>
        </div>
    </main>
</body>
</html>
