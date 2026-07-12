<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advertência disciplinar - ciência e assinatura</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; max-width: 680px; margin: 0 auto; padding: 20px; background-color: #f3f4f6; }
        .container { background-color: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); }
        .header { text-align: center; border-bottom: 2px solid #dc3545; padding-bottom: 20px; margin-bottom: 24px; }
        .header h1 { color: #b91c1c; margin: 0 0 6px; font-size: 24px; }
        .header p { margin: 0; color: #6b7280; }
        .panel { background: #fff8e1; border: 1px solid #f3d58c; border-left: 4px solid #f0ad4e; border-radius: 6px; padding: 16px; margin: 20px 0; }
        .meta { width: 100%; border-collapse: collapse; margin: 18px 0; }
        .meta td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .meta td:first-child { width: 180px; color: #6b7280; font-weight: bold; }
        .button { display: inline-block; padding: 12px 20px; background: #b91c1c; color: #ffffff !important; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .muted { color: #6b7280; font-size: 13px; }
        .footer { text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 30px; }
        .note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px 16px; margin-top: 18px; }
    </style>
</head>
<body>
<?php
$employeeName = $employee_name ?? ($employee->name ?? 'colaborador(a)');
$warningType = $warning_type ?? 'Advertência';
$warningNumber = $warning_number ?? null;
$occurrenceDate = $occurrence_date ?? null;
$issuerName = $issuer_name ?? null;
$reason = trim((string) ($reason ?? ''));
$companyName = $company_name ?? 'Support Solo Sondagens';
$signUrl = $sign_url ?? ($link ?? null);
$showUrl = $show_url ?? null;
$supportEmail = $support_email ?? 'contato@supportsondagens.com.br';
?>
<div class="container">
    <div class="header">
        <h1>Advertência disciplinar registrada</h1>
        <p><?= esc($companyName) ?></p>
    </div>

    <p>Olá, <strong><?= esc($employeeName) ?></strong>.</p>

    <p>Informamos que foi registrada uma advertência disciplinar em seu nome no sistema <strong>SupportPONTO</strong>. Este comunicado tem finalidade de ciência formal e, quando aplicável, de coleta da sua assinatura eletrônica.</p>

    <table class="meta" role="presentation">
        <?php if (!empty($warningNumber)): ?>
        <tr>
            <td>Número do registro</td>
            <td>#<?= esc($warningNumber) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>Tipo de advertência</td>
            <td><?= esc($warningType) ?></td>
        </tr>
        <?php if (!empty($occurrenceDate)): ?>
        <tr>
            <td>Data da ocorrência</td>
            <td><?= esc($occurrenceDate) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($issuerName)): ?>
        <tr>
            <td>Emitida por</td>
            <td><?= esc($issuerName) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($reason !== ''): ?>
        <tr>
            <td>Resumo do motivo</td>
            <td><?= esc($reason) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="panel">
        <strong>Atenção:</strong> a assinatura eletrônica registra apenas a sua ciência sobre o conteúdo apresentado. Ela não impede o exercício do contraditório pelos canais internos competentes da empresa.
    </div>

    <?php if (!empty($signUrl)): ?>
        <p>
            <a href="<?= sp_safe_url($signUrl) ?>" class="button">Abrir advertência para ciência e assinatura</a>
        </p>
        <p class="muted">Se o botão não funcionar, copie e cole este endereço no navegador:<br><?= esc($signUrl) ?></p>
    <?php endif; ?>

    <?php if (!empty($showUrl)): ?>
        <p class="muted">Consulta direta do registro: <?= esc($showUrl) ?></p>
    <?php endif; ?>

    <div class="note">
        <strong>Orientações importantes</strong>
        <div>1. Confira os dados do registro antes de confirmar a assinatura.</div>
        <div>2. Utilize o método de assinatura disponível no sistema.</div>
        <div>3. Em caso de divergência, procure o gestor responsável ou o RH.</div>
    </div>

    <p class="muted">Suporte operacional: <?= esc($supportEmail) ?></p>

    <div class="footer">
        <p>Mensagem automática do SupportPONTO.</p>
        <p>Não responda este e-mail.</p>
    </div>
</div>
</body>
</html>
