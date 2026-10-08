<?php

namespace App\Traits;

use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToBranchAndFY
{
    use BelongsToTenant;

    public static function bootBelongsToBranchAndFY(): void
    {
        static::bootBelongsToTenant();

        static::addGlobalScope('branch_fy_isolation', function (Builder $builder) {
            $branchId = AuthMiddleware::getBranchId();
            $model = $builder->getModel();
            
            if ($branchId !== null && $branchId > 0 && $model->hasBranchColumn()) {
                $builder->where($model->getTable() . '.branch_id', $branchId);
            }

            $fyRange = AuthMiddleware::getFinancialYearRange();
            if ($fyRange !== null) {
                $dateCol = $model->getDateColumnName();
                if ($dateCol) {
                    $builder->whereBetween($model->getTable() . '.' . $dateCol, [$fyRange['start'], $fyRange['end']]);
                }
            }
        });

        static::creating(function ($model) {
            $branchId = AuthMiddleware::getBranchId();
            if ($branchId !== null && $branchId > 0 && $model->hasBranchColumn()) {
                $model->branch_id = $branchId;
            }
        });
    }

    public function hasBranchColumn(): bool
    {
        $table = $this->getTable();
        return in_array($table, [
            'invoices', 'purchases', 'expenses', 'warehouses', 'user_branches',
            'quotations', 'sales_orders', 'delivery_challans', 'credit_notes', 'sales_returns', 'recurring_invoices',
            'purchase_orders', 'goods_receipts', 'debit_notes', 'purchase_returns'
        ]);
    }

    public function getDateColumnName(): ?string
    {
        $table = $this->getTable();
        if ($table === 'invoices') return 'invoice_date';
        if ($table === 'purchases') return 'purchase_date';
        if ($table === 'expenses') return 'expense_date';
        if ($table === 'stock_adjustments') return 'adjustment_date';
        if ($table === 'stock_transfers') return 'transfer_date';
        if ($table === 'stock_movements') return 'created_at';
        if ($table === 'quotations') return 'quotation_date';
        if ($table === 'sales_orders') return 'order_date';
        if ($table === 'delivery_challans') return 'challan_date';
        if ($table === 'credit_notes') return 'credit_note_date';
        if ($table === 'sales_returns') return 'return_date';
        if ($table === 'recurring_invoices') return 'start_date';
        if ($table === 'purchase_orders') return 'po_date';
        if ($table === 'goods_receipts') return 'grn_date';
        if ($table === 'debit_notes') return 'debit_note_date';
        if ($table === 'purchase_returns') return 'return_date';
        return null;
    }
}
