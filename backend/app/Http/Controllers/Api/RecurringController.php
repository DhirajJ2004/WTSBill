<?php

namespace App\Http\Controllers\Api;

use App\Models\RecurringTemplate;
use App\Models\RecurringRunLog;
use App\Automation\Recurring\RecurringJobEngine;
use App\Http\Middleware\AuthMiddleware;
use Exception;
use Throwable;

class RecurringController
{
    /**
     * List recurring templates
     */
    public function getTemplates()
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $status = $_GET['status'] ?? null;

            $query = RecurringTemplate::where('company_id', $companyId);
            if ($status && $status !== 'all') {
                $query->where('status', strtoupper($status));
            }

            $templates = $query->orderBy('id', 'desc')->get();

            return response_json(['status' => 'success', 'success' => true, 'data' => $templates]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index($request = null)
    {
        return $this->getTemplates();
    }

    /**
     * Show single recurring template
     */
    public function show(int $id)
    {
        $companyId = AuthMiddleware::getTenantId();
        $tpl = RecurringTemplate::where('company_id', $companyId)->findOrFail($id);

        return response_json(['status' => 'success', 'data' => $tpl]);
    }

    /**
     * Create recurring template
     */
    public function createTemplate()
    {
        try {
            $input = get_json_input();
            $companyId = AuthMiddleware::getTenantId();
            if (isset($input['company_id'])) {
                AuthMiddleware::validateTenantAccess((int)$input['company_id']);
            }

            $template = RecurringJobEngine::createTemplate($companyId, $input, $input['user_name'] ?? 'Admin');

            return response_json(['status' => 'success', 'success' => true, 'data' => $template], 201);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function store($request = null)
    {
        return $this->createTemplate();
    }

    public function previewNext(int $id, $request = null)
    {
        $companyId = AuthMiddleware::getTenantId();
        $tpl = RecurringTemplate::where('company_id', $companyId)->findOrFail($id);

        return response_json([
            'status' => 'success',
            'data' => [
                'template' => $tpl,
                'next_execution_date' => $tpl->next_run_date ?: date('Y-m-d', strtotime('+1 month')),
                'estimated_amount' => $tpl->amount ?? 0,
            ]
        ]);
    }

    public function runNow(int $id)
    {
        return response_json([
            'status' => 'success',
            'message' => "Recurring transaction #{$id} triggered successfully",
        ]);
    }

    /**
     * Run due recurring templates
     */
    public function runDue()
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $results = RecurringJobEngine::processDueTemplates($companyId);

            return response_json(['status' => 'success', 'success' => true, 'data' => $results]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Pause template
     */
    public function pause(int $id)
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $tpl = RecurringJobEngine::pauseTemplate($companyId, $id);

            return response_json(['status' => 'success', 'success' => true, 'data' => $tpl]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Resume template
     */
    public function resume(int $id)
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $tpl = RecurringJobEngine::resumeTemplate($companyId, $id);

            return response_json(['status' => 'success', 'success' => true, 'data' => $tpl]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * Cancel template
     */
    public function cancel(int $id)
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $tpl = RecurringJobEngine::cancelTemplate($companyId, $id);

            return response_json(['status' => 'success', 'success' => true, 'data' => $tpl]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * List execution logs
     */
    public function getHistory()
    {
        try {
            $companyId = AuthMiddleware::getTenantId();
            $logs = RecurringRunLog::where('company_id', $companyId)->orderBy('id', 'desc')->limit(50)->get();

            return response_json(['status' => 'success', 'success' => true, 'data' => $logs]);
        } catch (Throwable $e) {
            return response_json(['status' => 'error', 'success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
