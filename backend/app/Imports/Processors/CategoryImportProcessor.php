<?php

namespace App\Imports\Processors;

use App\Models\Category;

class CategoryImportProcessor
{
    public static function process(int $companyId, array $parsedRows, string $duplicateAction = 'SKIP'): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($parsedRows as $item) {
            $data = $item['parsed'];
            $isDuplicate = $item['is_duplicate'] ?? false;
            $existingId = $item['existing_id'] ?? null;

            if ($isDuplicate && $duplicateAction === 'SKIP') {
                $skipped++;
                continue;
            }

            if ($isDuplicate && $duplicateAction === 'UPDATE' && $existingId) {
                $category = Category::where('company_id', $companyId)->find($existingId);
                if ($category) {
                    $category->update(array_filter([
                        'description' => $data['description'] ?: $category->description,
                    ]));
                    $updated++;
                    continue;
                }
            }

            Category::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }
}
