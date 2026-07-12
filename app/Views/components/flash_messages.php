<?php
$session = session();
$flashMessages = [
    'success' => [
        'type' => 'success',
        'icon' => 'bi bi-check-circle-fill fa-solid fa-circle-check',
        'title' => 'Operação realizada com sucesso',
        'message' => $session->getFlashdata('success'),
    ],
    'error' => [
        'type' => 'danger',
        'icon' => 'bi bi-exclamation-triangle-fill fa-solid fa-triangle-exclamation',
        'title' => 'Não foi possível concluir a operação',
        'message' => $session->getFlashdata('error'),
    ],
    'warning' => [
        'type' => 'warning',
        'icon' => 'bi bi-exclamation-circle-fill fa-solid fa-circle-exclamation',
        'title' => 'Atenção',
        'message' => $session->getFlashdata('warning'),
    ],
    'info' => [
        'type' => 'info',
        'icon' => 'bi bi-info-circle-fill fa-solid fa-circle-info',
        'title' => 'Informação importante',
        'message' => $session->getFlashdata('info'),
    ],
    'message' => [
        'type' => 'success',
        'icon' => 'bi bi-check-circle-fill fa-solid fa-circle-check',
        'title' => 'Atualização',
        'message' => $session->getFlashdata('message'),
    ],
];

$validationErrors = $session->getFlashdata('errors');
if (! empty($validationErrors) && is_array($validationErrors)) {
    $flashMessages['validation'] = [
        'type' => 'danger',
        'icon' => 'bi bi-card-checklist fa-solid fa-list-check',
        'title' => 'Revise os campos destacados',
        'message' => null,
        'errors' => array_values(array_filter(array_map(static fn ($error) => trim((string) $error), $validationErrors))),
    ];
}
?>

<?php foreach ($flashMessages as $item): ?>
    <?php
    $message = trim((string) ($item['message'] ?? ''));
    $errors = $item['errors'] ?? [];
    if ($message === '' && empty($errors)) {
        continue;
    }
    ?>
    <div class="alert alert-<?= esc($item['type']) ?> alert-dismissible fade show mb-3 sp-alert-soft" role="alert">
        <i class="<?= esc($item['icon']) ?> sp-alert-soft__icon"></i>
        <div class="sp-alert-soft__body">
            <strong class="sp-alert-soft__title fw-semibold"><?= esc($item['title']) ?></strong>
                <?php if ($message !== ''): ?>
                    <div><?= esc($message) ?></div>
                <?php endif; ?>
                <?php if (! empty($errors)): ?>
                    <ul class="mb-0 mt-2 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endforeach; ?>
