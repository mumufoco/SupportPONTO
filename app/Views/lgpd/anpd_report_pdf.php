<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório ANPD</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        h2 { font-size: 15px; margin: 18px 0 8px; }
        .muted { color: #666; }
        .box { border: 1px solid #ccc; border-radius: 6px; padding: 10px; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Relatório de Conformidade ANPD</h1>
    <div class="muted">Documento gerado pelo SupportPONTO para fins administrativos e de compliance.</div>

    <div class="box">
        <strong>Resumo:</strong><br>
        Este relatório consolida informações gerais de tratamento de dados pessoais e operação de biometria
        para suporte a análise administrativa e conformidade.
    </div>

    <h2>Indicadores</h2>
    <table>
        <thead>
            <tr>
                <th>Indicador</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($indicators ?? []) as $item): ?>
                <tr>
                    <td><?= esc($item['label'] ?? '-') ?></td>
                    <td><?= esc($item['value'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($indicators ?? [])): ?>
                <tr>
                    <td colspan="2">Sem indicadores consolidados no momento.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Observações</h2>
    <div class="box">
        <?= esc($notes ?? 'Sem observações adicionais.') ?>
    </div>
</body>
</html>
