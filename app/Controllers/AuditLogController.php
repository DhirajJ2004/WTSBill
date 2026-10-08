<?php

namespace App\Controllers;

use App\Services\AuditLogService;
use App\Http\Request;

class AuditLogController
{
    public function index(Request $request)
    {
        $companyId = (int)($request->get('company_id') ?: ($_SESSION['company_id'] ?? 1));
        $filters = [
            'action'      => $request->get('action'),
            'entity_type' => $request->get('entity_type'),
            'user_name'   => $request->get('user_name'),
            'status'      => $request->get('status'),
            'from_date'   => $request->get('from_date'),
            'to_date'     => $request->get('to_date'),
            'search'      => $request->get('search'),
        ];
        $page = max(1, (int)($request->get('page') ?: 1));
        $perPage = max(10, min(100, (int)($request->get('per_page') ?: 50)));

        $data = AuditLogService::getLogs($companyId, $filters, $page, $perPage);
        return [
            'success' => true,
            'data'    => $data,
        ];
    }
}
