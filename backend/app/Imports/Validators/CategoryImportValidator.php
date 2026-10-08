<?php

namespace App\Imports\Validators;

use App\Models\Category;

class CategoryImportValidator extends BaseImportValidator
{
    public function validateRow(int $companyId, array $row, int $rowIndex): array
    {
        $issues = [];
        $this->validateRequired($row, 'name', 'Category Name', $issues);

        $name = trim((string)($row['name'] ?? ''));
        $parent = trim((string)($row['parent_category'] ?? ''));

        $isDuplicate = false;
        $existing = Category::where('company_id', $companyId)->where('name', $name)->first();
        if ($existing) {
            $isDuplicate = true;
            $issues[] = [
                'type' => 'WARNING',
                'field' => 'name',
                'message' => "Category '{$name}' already exists (#{$existing->id}).",
                'suggested_fix' => 'Skip or Update.',
            ];
        }

        $hasErrors = count(array_filter($issues, fn($i) => $i['type'] === 'ERROR')) > 0;
        $status = $hasErrors ? 'ERROR' : ($isDuplicate ? 'DUPLICATE' : 'VALID');

        return [
            'status' => $status,
            'issues' => $issues,
            'is_duplicate' => $isDuplicate,
            'existing_id' => $existing?->id,
            'parsed' => [
                'name' => $name,
                'parent_category' => $parent,
                'description' => $row['description'] ?? null,
            ],
        ];
    }
}
