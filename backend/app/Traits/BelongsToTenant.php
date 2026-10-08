<?php

namespace App\Traits;

use App\Http\Middleware\AuthMiddleware;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Global tenant scope for queries
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            try {
                $tenantId = AuthMiddleware::getTenantId();
                if ($tenantId !== null && $tenantId > 0) {
                    $builder->where($builder->getModel()->getTable() . '.company_id', $tenantId);
                }
            } catch (\Throwable $e) {}
        });

        // Enforce tenant ID on create/save regardless of input
        static::creating(function ($model) {
            try {
                $tenantId = AuthMiddleware::getTenantId();
                if ($tenantId !== null && $tenantId > 0 && empty($model->company_id)) {
                    $model->company_id = $tenantId;
                }
            } catch (\Throwable $e) {}
        });

        static::updating(function ($model) {
            try {
                $tenantId = AuthMiddleware::getTenantId();
                if ($tenantId !== null && $tenantId > 0) {
                    // Prevent updating record to another company_id
                    $model->company_id = $tenantId;
                }
            } catch (\Throwable $e) {}
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id');
    }
}
