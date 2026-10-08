<?php
/**
 * WTSBill ERP - Reusable Form Group Component
 *
 * Parameters:
 * - $label (string|null): Form label
 * - $name (string): Field name attribute
 * - $id (string|null): Field ID (defaults to $name)
 * - $type (string): 'text'|'number'|'email'|'password'|'date'|'select'|'textarea'|'checkbox'
 * - $value (mixed): Field current value
 * - $options (array): For select dropdown [['value' => '1', 'label' => 'Option 1'], ...]
 * - $placeholder (string|null): Input placeholder
 * - $required (bool): Is required
 * - $disabled (bool): Is disabled
 * - $readonly (bool): Is read-only
 * - $icon (string|null): Leading icon class
 * - $helpText (string|null): Helper hint text
 * - $error (string|null): Validation error message
 * - $class (string|null): Additional container CSS class
 * - $rows (int): Textarea rows
 * - $step (string|null): Number input step e.g. '0.01'
 */

$label = $label ?? null;
$name = $name ?? 'field_' . uniqid();
$id = $id ?? $name;
$type = $type ?? 'text';
$value = $value ?? (function_exists('old') ? old($name) : ($_POST[$name] ?? ''));
$options = $options ?? [];
$placeholder = $placeholder ?? '';
$required = !empty($required);
$disabled = !empty($disabled);
$readonly = !empty($readonly);
$icon = $icon ?? null;
$helpText = $helpText ?? null;
$error = $error ?? (function_exists('validation_error') ? validation_error($name) : null);
$customClass = $class ?? '';
$rows = $rows ?? 3;
$step = $step ?? null;

$containerClass = 'form-group ' . $customClass;
if (!empty($error)) $containerClass .= ' has-error';
?>
<div class="<?= htmlspecialchars($containerClass) ?>">
    <?php if ($type === 'checkbox'): ?>
        <label class="checkbox-label" for="<?= htmlspecialchars($id) ?>">
            <input type="checkbox" 
                   name="<?= htmlspecialchars($name) ?>" 
                   id="<?= htmlspecialchars($id) ?>" 
                   value="1" 
                   <?= $value ? 'checked' : '' ?> 
                   <?= $required ? 'required' : '' ?> 
                   <?= $disabled ? 'disabled' : '' ?>>
            <span><?= htmlspecialchars($label ?? '') ?></span>
        </label>
    <?php else: ?>
        <?php if (!empty($label)): ?>
            <label class="form-label" for="<?= htmlspecialchars($id) ?>">
                <?= htmlspecialchars($label) ?>
                <?php if ($required): ?>
                    <span class="text-danger">*</span>
                <?php endif; ?>
            </label>
        <?php endif; ?>

        <div class="input-wrapper <?= $icon ? 'has-icon' : '' ?>">
            <?php if (!empty($icon)): ?>
                <i class="<?= htmlspecialchars($icon) ?> input-icon"></i>
            <?php endif; ?>

            <?php if ($type === 'select'): ?>
                <select name="<?= htmlspecialchars($name) ?>" 
                        id="<?= htmlspecialchars($id) ?>" 
                        class="form-select <?= $error ? 'is-invalid' : '' ?>" 
                        <?= $required ? 'required' : '' ?> 
                        <?= $disabled ? 'disabled' : '' ?>>
                    <?php if (!empty($placeholder)): ?>
                        <option value=""><?= htmlspecialchars($placeholder) ?></option>
                    <?php endif; ?>
                    <?php foreach ($options as $optKey => $optVal): 
                        $optValue = is_array($optVal) ? ($optVal['value'] ?? $optKey) : $optKey;
                        $optLabel = is_array($optVal) ? ($optVal['label'] ?? $optVal['name'] ?? $optVal) : $optVal;
                        $isSelected = ((string)$optValue === (string)$value);
                    ?>
                        <option value="<?= htmlspecialchars((string)$optValue) ?>" <?= $isSelected ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$optLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'textarea'): ?>
                <textarea name="<?= htmlspecialchars($name) ?>" 
                          id="<?= htmlspecialchars($id) ?>" 
                          rows="<?= (int)$rows ?>" 
                          class="form-textarea <?= $error ? 'is-invalid' : '' ?>" 
                          placeholder="<?= htmlspecialchars($placeholder) ?>" 
                          <?= $required ? 'required' : '' ?> 
                          <?= $readonly ? 'readonly' : '' ?> 
                          <?= $disabled ? 'disabled' : '' ?>><?= htmlspecialchars((string)$value) ?></textarea>
            <?php else: ?>
                <input type="<?= htmlspecialchars($type) ?>" 
                       name="<?= htmlspecialchars($name) ?>" 
                       id="<?= htmlspecialchars($id) ?>" 
                       value="<?= htmlspecialchars((string)$value) ?>" 
                       class="form-input <?= $error ? 'is-invalid' : '' ?>" 
                       placeholder="<?= htmlspecialchars($placeholder) ?>" 
                       <?= $step !== null ? 'step="' . htmlspecialchars($step) . '"' : '' ?> 
                       <?= $required ? 'required' : '' ?> 
                       <?= $readonly ? 'readonly' : '' ?> 
                       <?= $disabled ? 'disabled' : '' ?>>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($helpText)): ?>
        <div class="form-help"><?= htmlspecialchars($helpText) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
</div>
