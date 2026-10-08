<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as DB;

class DocumentPrintService
{
    /**
     * Verify authentication and tenant authorization for printing documents.
     */
    public static function authorizeAndFetch(string $table, $id, string $docTypeName, string $returnUrl)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 1. Authentication check
        if (empty($_SESSION['user'])) {
            self::renderErrorPage(
                'Authentication Required',
                'You must be signed in to view or print official ERP documents.',
                url('/login'),
                'Sign In to WTSBill',
                401
            );
        }

        $companyId = get_current_company_id();
        $numericId = is_numeric($id) ? (int)$id : 0;

        // 2. Validate ID and tenant ownership
        if ($numericId <= 0) {
            self::renderErrorPage(
                "$docTypeName Not Found",
                "A valid $docTypeName ID was not provided in the request.",
                $returnUrl,
                "Return to " . ucfirst(trim(explode('/', $returnUrl)[1] ?? 'Dashboard')),
                404
            );
        }

        $document = null;
        try {
            $document = DB::table($table)
                ->where('id', $numericId)
                ->where('company_id', $companyId)
                ->first();
        } catch (\Throwable $e) {
            error_log("[PRINT AUTH ERROR] " . $e->getMessage());
        }

        if (!$document) {
            self::renderErrorPage(
                "$docTypeName Not Found or Unauthorized",
                "The requested $docTypeName #$numericId was not found, has been removed, or does not belong to your active company context.",
                $returnUrl,
                "Return to " . ucfirst(trim(explode('/', $returnUrl)[1] ?? 'Dashboard')),
                404
            );
        }

        // Fetch company details
        $company = DB::table('companies')->where('id', $companyId)->first();
        $bank = DB::table('bank_accounts')->where('company_id', $companyId)->first();

        // Company Logo
        $logoPath = __DIR__ . '/../../../public/assets/img/logo.png';
        $logoUri = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : url('/assets/img/logo.png');

        return [
            'document' => $document,
            'company' => $company,
            'bank' => $bank,
            'logoUri' => $logoUri,
            'companyDetails' => [
                'name' => $company->name ?? 'Wis Technosavvy Pvt Ltd',
                'legal_name' => $company->legal_name ?? ($company->name ?? 'Wis Technosavvy Pvt Ltd'),
                'address1' => $company->address_line1 ?? 'Office No-B-7, 2nd floor, Shreya Business Hub,',
                'address2' => $company->address_line2 ?? 'Pari chowk, Opp CNG Pump, Narhe,',
                'city' => trim(($company->city ?? 'Pune') . ', ' . ($company->state ?? 'Maharashtra') . ' ' . ($company->pincode ?? '411041')),
                'phone' => $company->phone ?? '020 47252364',
                'email' => $company->email ?? 'info@wtsindia.co.in',
                'website' => $company->website ?? 'http://www.wtsindia.co.in',
                'gstin' => $company->gstin ?? '27AADCW7577N1ZE',
                'msme' => $company->msme_registration_no ?? 'UDYAM-MH-26-0734598',
                'pan' => $company->pan ?? 'AADCW7577N'
            ],
            'bankDetails' => [
                'bank_name' => $bank->bank_name ?? 'HDFC Bank Ltd',
                'account_name' => $bank->account_name ?? ($company->name ?? 'Wis Technosavvy Pvt Ltd'),
                'account_number' => $bank->account_number ?? '50200064819203',
                'ifsc_code' => $bank->ifsc_code ?? 'HDFC0001824',
                'branch_name' => $bank->branch_name ?? 'Narhe Branch'
            ]
        ];
    }

    /**
     * Render a clean, stylized error page and stop execution.
     */
    public static function renderErrorPage(string $title, string $message, string $buttonUrl, string $buttonLabel, int $httpCode = 404)
    {
        if (!headers_sent()) {
            http_response_code($httpCode);
        }

        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - WTSBill ERP</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background: #f8fafc; color: #0f172a; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .error-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 500px; width: 100%; padding: 32px; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
        .error-icon { width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin: 0 auto 16px; }
        .error-title { font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        .error-desc { font-size: 13.5px; color: #64748b; line-height: 1.5; margin-bottom: 24px; }
        .btn-action { display: inline-flex; align-items: center; gap: 8px; background: #0f172a; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: background 0.15s; }
        .btn-action:hover { background: #1e293b; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">&times;</div>
        <h2 class="error-title">' . htmlspecialchars($title) . '</h2>
        <p class="error-desc">' . htmlspecialchars($message) . '</p>
        <a href="' . htmlspecialchars($buttonUrl) . '" class="btn-action">' . htmlspecialchars($buttonLabel) . '</a>
    </div>
</body>
</html>';
        exit;
    }
}
