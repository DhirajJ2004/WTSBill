<?php

namespace App\Http\Controllers\Api;

use App\Models\AutomationRule;
use App\Models\AutomationEvent;
use App\Models\AutomationExecutionLog;
use App\Automation\Events\EventEngine;
use App\Automation\Services\AutomationAnalyticsService;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationException;
use Exception;
use Throwable;

class AutomationController
{
    /**
     * Get Automation Dashboard metrics
     */
    public function getMetrics(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'view');
            $companyId = AuthMiddleware::getTenantId();
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $metrics = AutomationAnalyticsService::getDashboardMetrics($companyId);
            response_json(['success' => true, 'data' => $metrics]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Automation Rules
     */
    public function getRules(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'view');
            $companyId = AuthMiddleware::getTenantId();
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $rules = AutomationRule::where('company_id', $companyId)
                ->orderBy('priority', 'desc')
                ->get();

            response_json(['success' => true, 'data' => $rules]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Automation Rule
     */
    public function createRule(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $branchId = isset($input['branch_id']) ? AuthMiddleware::validateBranchAccess((int)$input['branch_id']) : AuthMiddleware::getBranchId();

            $rule = AutomationRule::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $input['name'] ?? 'Untitled Rule',
                'description' => $input['description'] ?? null,
                'event_type' => $input['event_type'] ?? 'InvoiceCreated',
                'conditions_json' => $input['conditions'] ?? $input['conditions_json'] ?? [],
                'actions_json' => $input['actions'] ?? $input['actions_json'] ?? [],
                'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : true,
                'priority' => intval($input['priority'] ?? 10),
                'created_by' => $user->name ?? ($input['user_name'] ?? 'Admin'),
            ]);

            response_json(['success' => true, 'data' => $rule], 201);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update Automation Rule
     */
    public function updateRule(int $id): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $rule = AutomationRule::where('company_id', $companyId)->findOrFail($id);

            if (isset($input['branch_id'])) {
                $input['branch_id'] = AuthMiddleware::validateBranchAccess((int)$input['branch_id']);
            }

            $rule->update([
                'branch_id' => array_key_exists('branch_id', $input) ? $input['branch_id'] : $rule->branch_id,
                'name' => $input['name'] ?? $rule->name,
                'description' => $input['description'] ?? $rule->description,
                'event_type' => $input['event_type'] ?? $rule->event_type,
                'conditions_json' => $input['conditions'] ?? $input['conditions_json'] ?? $rule->conditions_json,
                'actions_json' => $input['actions'] ?? $input['actions_json'] ?? $rule->actions_json,
                'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : $rule->is_active,
                'priority' => isset($input['priority']) ? intval($input['priority']) : $rule->priority,
            ]);

            response_json(['success' => true, 'data' => $rule]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Publish Event
     */
    public function publishEvent(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'manage');
            $companyId = AuthMiddleware::getTenantId();
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $event = EventEngine::publish(
                companyId: $companyId,
                eventType: $input['event_type'] ?? 'CustomEvent',
                entityType: $input['entity_type'] ?? 'MANUAL',
                entityId: intval($input['entity_id'] ?? 0),
                metadata: $input['metadata'] ?? [],
                triggeredBy: $user->name ?? ($input['user_name'] ?? 'User')
            );

            response_json(['success' => true, 'data' => $event]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get Execution Logs
     */
    public function getLogs(): void
    {
        try {
            $user = AuthMiddleware::authorize('settings', 'view');
            $companyId = AuthMiddleware::getTenantId();
            if (isset($_GET['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$_GET['company_id']);
            }

            $logs = AutomationExecutionLog::where('company_id', $companyId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            response_json(['success' => true, 'data' => $logs]);
        } catch (AuthorizationException $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], $e->statusCode);
        } catch (Throwable $e) {
            response_json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
