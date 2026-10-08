<?php

namespace App\Http\Controllers\Api;

use App\Models\DebitNote;
use App\Http\Middleware\AuthMiddleware;
use App\Services\DebitNoteService;
use App\Services\AuditLogService;

class DebitNoteController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $notes = DebitNote::with(['supplier', 'purchase', 'items'])
            ->orderBy('id', 'desc')
            ->get();

        return response_json([
            'status' => 'success',
            'data' => $notes
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $note = DebitNote::with(['supplier', 'purchase', 'items.product'])->find($id);

        if (!$note) {
            return response_json(['status' => 'error', 'message' => 'Debit Note not found.'], 404);
        }

        return response_json([
            'status' => 'success',
            'data' => $note
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = $user->current_company_id;

        $input = get_json_input();

        try {
            $note = DebitNoteService::createDebitNote($input);

            AuditLogService::log($companyId, $user->name, 'DEBIT_NOTE_CREATE', 'DebitNote', $note->id, "Created Debit Note #{$note->debit_note_number}");

            return response_json([
                'status' => 'success',
                'message' => "Debit Note #{$note->debit_note_number} generated successfully.",
                'data' => $note->load('items')
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
