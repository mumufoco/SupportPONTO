<?php
/**
 * Reusable Table Component
 * 
 * Usage:
 * <?= view('components/table', [
 *     'headers' => ['Nome', 'Email', 'Status', 'Ações'],
 *     'rows' => $dataRows, // Array of arrays
 *     'classes' => 'table-hover',
 *     'striped' => true,
 *     'responsive' => true
 * ]) ?>
 */

$headers = $headers ?? [];
$rows = $rows ?? [];
$classes = $classes ?? '';
$striped = $striped ?? true;
$responsive = $responsive ?? true;
$hover = $hover ?? true;

$tableClasses = 'table';
$columnCount = max(1, count($headers));
if ($striped) $tableClasses .= ' table-striped';
if ($hover) $tableClasses .= ' table-hover';
if ($classes) $tableClasses .= ' ' . $classes;
?>

<?php if ($responsive): ?>
<div class="table-responsive">
<?php endif; ?>
    <table class="<?= esc($tableClasses) ?>">
        <?php if (!empty($headers)): ?>
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                    <th><?= is_array($header) ? esc($header['label'] ?? '') : esc($header) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <?php endif; ?>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= (int) $columnCount ?>" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 sp-icon-faded"></i>
                        <p class="mb-0"><?= esc($emptyMessage ?? lang('AppUi.table.empty')) ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= $cell ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
<?php if ($responsive): ?>
</div>
<?php endif; ?>
