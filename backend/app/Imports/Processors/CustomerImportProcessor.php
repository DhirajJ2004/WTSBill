<?php

namespace App\Imports\Processors;

use App\Models\Customer;

class CustomerImportProcessor
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
                $customer = Customer::where('company_id', $companyId)->find($existingId);
                if ($customer) {
                    $customer->update(array_filter([
                        'phone' => $data['phone'] ?: $customer->phone,
                        'email' => $data['email'] ?: $customer->email,
                        'gstin' => $data['gstin'] ?: $customer->gstin,
                        'pan' => $data['pan'] ?: $customer->pan,
                        'tax_type' => $data['tax_type'] ?: $customer->tax_type,
                        'state' => $data['state'] ?: $customer->state,
                        'billing_address' => $data['billing_address'] ?: $customer->billing_address,
                        'shipping_address' => $data['shipping_address'] ?: $customer->shipping_address,
                        'credit_limit' => $data['credit_limit'] ?: $customer->credit_limit,
                        'credit_period_days' => $data['credit_period_days'] ?: $customer->credit_period_days,
                    ]));
                    $updated++;
                    continue;
                }
            }

            Customer::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'gstin' => $data['gstin'] ?: null,
                'pan' => $data['pan'] ?: null,
                'tax_type' => $data['tax_type'] ?? 'UNREGISTERED',
                'state' => $data['state'] ?? 'Maharashtra',
                'billing_address' => $data['billing_address'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null,
                'opening_balance' => $data['opening_balance'] ?? 0.00,
                'current_balance' => $data['opening_balance'] ?? 0.00,
                'credit_limit' => $data['credit_limit'] ?? 0.00,
                'credit_period_days' => $data['credit_period_days'] ?? 0,
                'is_active' => true,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped];
    }
}
