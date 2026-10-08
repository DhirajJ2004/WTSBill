<?php

namespace App\Imports\Processors;

use App\Models\Supplier;

class SupplierImportProcessor
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
                $supplier = Supplier::where('company_id', $companyId)->find($existingId);
                if ($supplier) {
                    $supplier->update(array_filter([
                        'phone' => $data['phone'] ?: $supplier->phone,
                        'email' => $data['email'] ?: $supplier->email,
                        'gstin' => $data['gstin'] ?: $supplier->gstin,
                        'pan' => $data['pan'] ?: $supplier->pan,
                        'state' => $data['state'] ?: $supplier->state,
                        'address' => $data['address'] ?: $supplier->address,
                        'bank_name' => $data['bank_name'] ?: $supplier->bank_name,
                        'bank_account_number' => $data['bank_account_number'] ?: $supplier->bank_account_number,
                        'ifsc_code' => $data['ifsc_code'] ?: $supplier->ifsc_code,
                    ]));
                    $updated++;
                    continue;
                }
            }

            Supplier::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'gstin' => $data['gstin'] ?: null,
                'pan' => $data['pan'] ?: null,
                'state' => $data['state'] ?? 'Maharashtra',
                'address' => $data['address'] ?? null,
                'opening_balance' => $data['opening_balance'] ?? 0.00,
                'current_balance' => $data['opening_balance'] ?? 0.00,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'ifsc_code' => $data['ifsc_code'] ?? null,
                'is_active' => true,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }
}
