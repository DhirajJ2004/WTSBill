<?php
/**
 * WTSBill ERP - Reusable Table Component
 *
 * Parameters:
 * - $headers (array): Column definitions [['label' => 'Invoice #', 'align' => 'left', 'width' => '15%'], ...]
 * - $rows (array|null): Row items
 * - $content (string|null): Raw <tbody> content if rendering custom rows
 * - $emptyMessage (string|null): Message to display when empty
 * - $class (string|null): Extra table classes
 * - $id (string|null): Table ID
 * - $hoverable (bool): Enable row hover highlight
 * - $striped (bool): Enable striped rows
 */

$headers = $headers ?? [];
$rows = $rows ?? null;
$content = $content ?? $slot ?? $body ?? null;
$emptyMessage = $emptyMessage ?? 'No records found.';
$customClass = $class ?? '';
$id = $id ?? null;
$hoverable = $hoverable ?? true;
$striped = $striped ?? false;

$tableClasses = ['table'];
if ($hoverable) $tableClasses[] = 'table-hover';
if ($striped) $tableClasses[] = 'table-striped';
if (!empty($customClass)) $tableClasses[] = $customClass;
$classAttr = implode(' ', $tableClasses);
?>
<div class="table-responsive">
    <table class="<?= $classAttr ?>" <?= $id ? 'id="' . htmlspecialchars($id) . '"' : '' ?>>
        <?php if (!empty($headers)): ?>
            <thead>
                <tr>
                    <?php foreach ($headers as $col): 
                        $label = is_array($col) ? ($col['label'] ?? '') : (string)$col;
                        $align = is_array($col) ? ($col['align'] ?? 'left') : 'left';
                        $width = is_array($col) ? ($col['width'] ?? null) : null;
                        $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : '');
                        $styleStr = $width ? 'style="width:' . htmlspecialchars($width) . ';"' : '';
                    ?>
                        <th class="<?= $alignClass ?>" <?= $styleStr ?>>
                            <?= htmlspecialchars($label) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
        <?php endif; ?>

        <tbody>
            <?php if (!empty($content)): ?>
                <?= $content ?>
            <?php elseif (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= $cell ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= count($headers) ?: 1 ?>" class="text-center py-6 text-muted">
                        <div class="table-empty-state">
                            <i class="fa-solid fa-folder-open text-muted" style="font-size: 24px; margin-bottom: 8px;"></i>
                            <p style="margin: 0; font-size: 13px;"><?= htmlspecialchars($emptyMessage) ?></p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
