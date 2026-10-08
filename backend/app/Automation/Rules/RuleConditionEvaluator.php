<?php

namespace App\Automation\Rules;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Cheque;

class RuleConditionEvaluator
{
    /**
     * Evaluate rule conditions against entity and event metadata.
     */
    public static function evaluate(array $conditions, string $entityType, int $entityId, array $metadata = [], int $companyId = 1): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $operator = strtoupper($conditions['match_type'] ?? ($conditions['operator'] ?? 'AND'));
        $rules = $conditions['rules'] ?? ($conditions['conditions'] ?? []);

        if (empty($rules) && !isset($conditions['field'])) {
            return true;
        }

        // Single condition case
        if (isset($conditions['field'])) {
            $rules = [$conditions];
            $operator = 'AND';
        }

        $entityData = self::resolveEntityData($entityType, $entityId, $metadata, $companyId);

        $results = [];
        foreach ($rules as $rule) {
            $field = $rule['field'] ?? '';
            $op = strtoupper($rule['operator'] ?? 'EQUALS');
            $expectedValue = $rule['value'] ?? null;

            $actualValue = $entityData[$field] ?? ($metadata[$field] ?? null);

            $results[] = self::compare($actualValue, $op, $expectedValue);
        }

        if ($operator === 'OR') {
            return in_array(true, $results, true);
        }

        // Default AND logic
        return !in_array(false, $results, true);
    }

    /**
     * Resolve actual values from database models
     */
    protected static function resolveEntityData(string $entityType, int $entityId, array $metadata, int $companyId): array
    {
        $data = $metadata;

        switch (strtoupper($entityType)) {
            case 'INVOICE':
                $inv = Invoice::where('company_id', $companyId)->find($entityId);
                if ($inv) {
                    $dueDays = 0;
                    if ($inv->due_date && strtotime($inv->due_date) < time()) {
                        $dueDays = (int)floor((time() - strtotime($inv->due_date)) / 86400);
                    }
                    $data = array_merge($data, [
                        'amount' => floatval($inv->grand_total),
                        'grand_total' => floatval($inv->grand_total),
                        'amount_due' => floatval($inv->amount_due ?? 0),
                        'status' => $inv->status,
                        'customer_id' => $inv->customer_id,
                        'due_date' => $inv->due_date,
                        'days_overdue' => $dueDays,
                        'branch_id' => $inv->branch_id,
                    ]);
                }
                break;

            case 'PAYMENT':
                $pay = Payment::where('company_id', $companyId)->find($entityId);
                if ($pay) {
                    $data = array_merge($data, [
                        'amount' => floatval($pay->amount),
                        'payment_mode' => $pay->payment_mode,
                        'payment_type' => $pay->payment_type,
                        'status' => $pay->status,
                        'party_id' => $pay->party_id,
                        'party_type' => $pay->party_type,
                    ]);
                }
                break;

            case 'PRODUCT':
                $prod = Product::where('company_id', $companyId)->find($entityId);
                if ($prod) {
                    $data = array_merge($data, [
                        'stock_quantity' => floatval($prod->stock_quantity ?? 0),
                        'min_stock_level' => floatval($prod->min_stock_level ?? 0),
                        'selling_price' => floatval($prod->selling_price ?? 0),
                        'is_active' => (bool)$prod->is_active,
                    ]);
                }
                break;

            case 'CHEQUE':
                $chq = Cheque::where('company_id', $companyId)->find($entityId);
                if ($chq) {
                    $data = array_merge($data, [
                        'amount' => floatval($chq->amount),
                        'status' => $chq->status,
                        'cheque_type' => $chq->cheque_type,
                        'party_id' => $chq->party_id,
                    ]);
                }
                break;
        }

        return $data;
    }

    /**
     * Compare actual vs expected values based on operator
     */
    protected static function compare($actual, string $op, $expected): bool
    {
        switch ($op) {
            case 'EQUALS':
            case 'EQ':
            case '==':
                return strval($actual) === strval($expected);

            case 'NOT_EQUALS':
            case 'NEQ':
            case '!=':
                return strval($actual) !== strval($expected);

            case 'GREATER_THAN':
            case 'GT':
            case '>':
                return floatval($actual) > floatval($expected);

            case 'GREATER_THAN_OR_EQUAL':
            case 'GTE':
            case '>=':
                return floatval($actual) >= floatval($expected);

            case 'LESS_THAN':
            case 'LT':
            case '<':
                return floatval($actual) < floatval($expected);

            case 'LESS_THAN_OR_EQUAL':
            case 'LTE':
            case '<=':
                return floatval($actual) <= floatval($expected);

            case 'CONTAINS':
                return stripos(strval($actual), strval($expected)) !== false;

            case 'IN':
                $arr = is_array($expected) ? $expected : array_map('trim', explode(',', strval($expected)));
                return in_array(strval($actual), array_map('strval', $arr), true);

            case 'BETWEEN':
                if (is_array($expected) && count($expected) >= 2) {
                    $min = floatval($expected[0]);
                    $max = floatval($expected[1]);
                    $val = floatval($actual);
                    return $val >= $min && $val <= $max;
                }
                return false;

            default:
                return true;
        }
    }
}
