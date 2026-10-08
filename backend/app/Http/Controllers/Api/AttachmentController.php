<?php

namespace App\Http\Controllers\Api;

use App\Models\SupplierBillAttachment;
use App\Models\Purchase;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;

class AttachmentController
{
    public function index()
    {
        $user = AuthMiddleware::authorize('purchases', 'view');

        $attachments = SupplierBillAttachment::orderBy('id', 'desc')->get();

        return response_json([
            'status' => 'success',
            'data' => $attachments
        ]);
    }

    public function store()
    {
        $user = AuthMiddleware::authorize('purchases', 'create');
        $companyId = $user->current_company_id;

        // Fetch purchase invoice ID from request parameters/post fields
        $purchaseId = intval($_POST['purchase_id'] ?? 0);
        $purchase = Purchase::find($purchaseId);

        if (!$purchase) {
            return response_json(['status' => 'error', 'message' => 'Linked purchase invoice is required.'], 422);
        }

        if (empty($_FILES['file'])) {
            return response_json(['status' => 'error', 'message' => 'No file uploaded.'], 422);
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return response_json(['status' => 'error', 'message' => 'File upload error code: ' . $file['error']], 422);
        }

        $uploadDir = __DIR__ . '/../../../../storage/uploads/attachments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . basename($file['name']);
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $attachment = SupplierBillAttachment::create([
                'company_id' => $companyId,
                'purchase_id' => $purchase->id,
                'file_name' => $file['name'],
                'file_type' => $file['type'],
                'file_path' => 'storage/uploads/attachments/' . $fileName,
                'uploaded_by' => $user->id,
            ]);

            AuditLogService::log($companyId, $user->name, 'ATTACHMENT_UPLOAD', 'SupplierBillAttachment', $attachment->id, "Uploaded bill attachment '{$file['name']}' for Purchase #{$purchase->purchase_number}");

            return response_json([
                'status' => 'success',
                'message' => 'Bill attachment uploaded successfully.',
                'data' => $attachment
            ], 201);
        } else {
            return response_json(['status' => 'error', 'message' => 'Failed to save uploaded file.'], 500);
        }
    }
}
