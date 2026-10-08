<?php

namespace App\Http\Controllers\Api;

use App\Models\Supplier;
use App\Models\SupplierAddress;
use App\Models\SupplierContact;
use App\Models\SupplierBankAccount;
use App\Models\SupplierGroup;
use App\Models\Purchase;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class SupplierController
{
    /**
     * List suppliers.
     */
    public function index()
    {
        $user = AuthMiddleware::authorize('suppliers', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $query = Supplier::with(['addresses', 'contacts', 'bankAccounts', 'group']);

        // 1. Search Query
        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        // 2. Status Filter
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $query->where('status', $_GET['status']);
        }

        // 3. Tax Type Filter
        if (!empty($_GET['tax_type']) && $_GET['tax_type'] !== 'all') {
            $query->where('tax_type', $_GET['tax_type']);
        }

        // 4. Group Filter
        if (!empty($_GET['supplier_group_id']) && $_GET['supplier_group_id'] !== 'all') {
            $query->where('supplier_group_id', intval($_GET['supplier_group_id']));
        }

        // 5. Sorting
        $sortBy = $_GET['sort_by'] ?? 'name';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'created_at', 'current_balance'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('name', 'asc');
        }

        // 6. Pagination
        $perPage = max(1, min(100, intval($_GET['per_page'] ?? 50)));
        $page = max(1, intval($_GET['page'] ?? 1));
        $total = $query->count();
        $suppliers = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response_json([
            'status' => 'success',
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
            'data' => $suppliers,
        ]);
    }

    /**
     * Show single supplier details.
     */
    public function show($id)
    {
        $user = AuthMiddleware::authorize('suppliers', 'view');
        $companyId = AuthMiddleware::getTenantId();
        
        $raw = Supplier::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Supplier not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $supplier = Supplier::with(['addresses', 'contacts', 'bankAccounts', 'group'])->find($id);

        // Aggregated transaction totals
        $purchaseStats = DB::table('purchases')
            ->where('supplier_id', $supplier->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_purchases, COALESCE(SUM(amount_due), 0) as outstanding, COALESCE(SUM(amount_paid), 0) as paid')
            ->first();

        $lastPurchase = DB::table('purchases')
            ->where('supplier_id', $supplier->id)
            ->whereNull('deleted_at')
            ->orderBy('purchase_date', 'desc')
            ->first();

        return response_json([
            'status' => 'success',
            'data' => $supplier,
            'summary' => [
                'total_purchases' => $purchaseStats ? floatval($purchaseStats->total_purchases) : 0,
                'outstanding' => $purchaseStats ? floatval($purchaseStats->outstanding) : 0,
                'payments_made' => $purchaseStats ? floatval($purchaseStats->paid) : 0,
                'last_transaction' => $lastPurchase ? [
                    'id' => $lastPurchase->id,
                    'number' => $lastPurchase->purchase_number,
                    'date' => $lastPurchase->purchase_date,
                    'amount' => floatval($lastPurchase->grand_total),
                ] : null,
            ]
        ]);
    }

    /**
     * Create supplier.
     */
    public function store()
    {
        $user = AuthMiddleware::authorize('suppliers', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        // 1. Validation
        $name = trim($input['name'] ?? '');
        if (empty($name) || strlen($name) < 2) {
            return response_json(['status' => 'error', 'message' => 'Supplier Name is required and must be at least 2 characters.'], 422);
        }

        $phone = trim($input['phone'] ?? '');
        if (!empty($phone)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 13) {
                return response_json(['status' => 'error', 'message' => 'Invalid phone number format. Must be at least 10 digits.'], 422);
            }
        }

        $email = trim($input['email'] ?? '');
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response_json(['status' => 'error', 'message' => 'Invalid email address format.'], 422);
        }

        $gstin = strtoupper(trim($input['gstin'] ?? ''));
        if (!empty($gstin)) {
            $gstinPattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
            if (!preg_match($gstinPattern, $gstin)) {
                return response_json(['status' => 'error', 'message' => 'Invalid GSTIN format. Must be a valid 15-character Indian GSTIN.'], 422);
            }

            // Duplicate Check scoped to authorized company
            $existing = Supplier::where('company_id', $companyId)->where('gstin', $gstin)->where('status', 'ACTIVE')->first();
            if ($existing) {
                return response_json(['status' => 'error', 'message' => "An active supplier with GSTIN '{$gstin}' already exists in your company directory."], 422);
            }
        }

        $pan = strtoupper(trim($input['pan'] ?? ''));
        if (empty($pan) && strlen($gstin) >= 10) {
            $pan = substr($gstin, 2, 10);
        }
        if (!empty($pan)) {
            $panPattern = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';
            if (!preg_match($panPattern, $pan)) {
                return response_json(['status' => 'error', 'message' => 'Invalid PAN format. Must be a valid 10-character PAN (e.g. ABCDE1234F).'], 422);
            }
        }

        $address = trim($input['address_line1'] ?? ($input['address'] ?? ''));
        $city = trim($input['city'] ?? '');
        $state = trim($input['state'] ?? 'Maharashtra');
        $stateCode = trim($input['state_code'] ?? (!empty($gstin) ? substr($gstin, 0, 2) : '27'));

        $supplier = DB::transaction(function () use ($input, $name, $gstin, $pan, $phone, $email, $address, $city, $state, $stateCode, $companyId, $user) {
            $supplier = Supplier::create([
                'company_id' => $companyId,
                'supplier_group_id' => !empty($input['supplier_group_id']) ? intval($input['supplier_group_id']) : null,
                'name' => $name,
                'company_name' => $input['company_name'] ?? $name,
                'gstin' => $gstin,
                'pan' => $pan,
                'email' => $email,
                'phone' => $phone,
                'address_line1' => $address,
                'city' => $city,
                'state' => $state,
                'state_code' => $stateCode,
                'pincode' => trim($input['pincode'] ?? ''),
                'opening_balance' => floatval($input['opening_balance'] ?? 0),
                'current_balance' => floatval($input['opening_balance'] ?? 0),
                'is_active' => true,
                'alt_phone' => trim($input['alt_phone'] ?? ''),
                'tax_type' => $input['tax_type'] ?? 'Unregistered',
                'place_of_supply' => trim($input['place_of_supply'] ?? $input['state_code'] ?? '27'),
                'contact_person' => trim($input['contact_person'] ?? ''),
                'notes' => trim($input['notes'] ?? ''),
                'status' => 'ACTIVE',
                'credit_period' => intval($input['credit_period'] ?? 30),
                'default_payment_mode' => $input['default_payment_mode'] ?? 'Bank Transfer',
                'created_by' => $user->id,
            ]);

            // Save addresses
            $addresses = $input['addresses'] ?? $input['shipping_addresses'] ?? [];
            if (!empty($addresses) && is_array($addresses)) {
                foreach ($addresses as $addr) {
                    SupplierAddress::create([
                        'company_id' => $companyId,
                        'supplier_id' => $supplier->id,
                        'type' => $addr['type'] ?? 'OFFICE',
                        'address_line1' => $addr['address_line1'] ?? $addr['address'] ?? '',
                        'address_line2' => $addr['address_line2'] ?? '',
                        'city' => $addr['city'] ?? '',
                        'state' => $addr['state'] ?? '',
                        'state_code' => $addr['state_code'] ?? '27',
                        'pincode' => $addr['pincode'] ?? '',
                    ]);
                }
            } else {
                // Add default address
                SupplierAddress::create([
                    'company_id' => $companyId,
                    'supplier_id' => $supplier->id,
                    'type' => 'OFFICE',
                    'address_line1' => $supplier->address_line1,
                    'city' => $supplier->city,
                    'state' => $supplier->state,
                    'state_code' => $supplier->state_code,
                    'pincode' => $supplier->pincode,
                ]);
            }

            // Save contact persons
            $contacts = $input['contacts'] ?? $input['additional_contacts'] ?? [];
            if (!empty($contacts) && is_array($contacts)) {
                foreach ($contacts as $contact) {
                    SupplierContact::create([
                        'company_id' => $companyId,
                        'supplier_id' => $supplier->id,
                        'name' => $contact['name'] ?? '',
                        'phone' => $contact['phone'] ?? '',
                        'email' => $contact['email'] ?? '',
                    ]);
                }
            }

            // Save bank accounts
            if (!empty($input['bank_accounts']) && is_array($input['bank_accounts'])) {
                foreach ($input['bank_accounts'] as $bank) {
                    SupplierBankAccount::create([
                        'company_id' => $companyId,
                        'supplier_id' => $supplier->id,
                        'bank_name' => $bank['bank_name'] ?? '',
                        'account_name' => $bank['account_name'] ?? '',
                        'account_number' => $bank['account_number'] ?? '',
                        'ifsc_code' => $bank['ifsc_code'] ?? '',
                        'branch_name' => $bank['branch_name'] ?? '',
                    ]);
                }
            }

            AuditLogService::log(
                $companyId,
                $user->name,
                'SUPPLIER_CREATE',
                'Supplier',
                $supplier->id,
                "Created Supplier '{$supplier->name}'"
            );

            return $supplier;
        });

        return response_json([
            'status' => 'success',
            'message' => 'Supplier created successfully.',
            'data' => $supplier->load(['addresses', 'contacts', 'bankAccounts']),
            'supplier' => $supplier,
        ], 201);
    }

    /**
     * Update supplier.
     */
    public function update($id)
    {
        $user = AuthMiddleware::authorize('suppliers', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $raw = Supplier::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Supplier not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $supplier = Supplier::find($id);

        // Validation
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Supplier Name is required.'], 422);
        }

        $gstin = trim($input['gstin'] ?? '');
        if (!empty($gstin)) {
            $gstinPattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
            if (!preg_match($gstinPattern, $gstin)) {
                return response_json(['status' => 'error', 'message' => 'Invalid GSTIN format.'], 422);
            }

            // Duplicate Check
            $existing = Supplier::where('gstin', $gstin)
                ->where('id', '!=', $supplier->id)
                ->where('status', 'ACTIVE')
                ->first();
            if ($existing) {
                return response_json(['status' => 'error', 'message' => "Another active supplier with GSTIN '{$gstin}' already exists."], 422);
            }
        }

        $pan = trim($input['pan'] ?? '');
        if (empty($pan) && strlen($gstin) >= 10) {
            $pan = substr($gstin, 2, 10);
        }

        return DB::transaction(function () use ($supplier, $input, $name, $gstin, $pan, $companyId, $user) {
            $supplier->update([
                'supplier_group_id' => !empty($input['supplier_group_id']) ? intval($input['supplier_group_id']) : null,
                'name' => $name,
                'company_name' => $input['company_name'] ?? $name,
                'gstin' => $gstin,
                'pan' => $pan,
                'email' => trim($input['email'] ?? ''),
                'phone' => trim($input['phone'] ?? ''),
                'address_line1' => trim($input['address_line1'] ?? ''),
                'city' => trim($input['city'] ?? ''),
                'state' => trim($input['state'] ?? ''),
                'state_code' => trim($input['state_code'] ?? '27'),
                'pincode' => trim($input['pincode'] ?? ''),
                'alt_phone' => trim($input['alt_phone'] ?? ''),
                'tax_type' => $input['tax_type'] ?? 'Unregistered',
                'place_of_supply' => trim($input['place_of_supply'] ?? $input['state_code'] ?? '27'),
                'contact_person' => trim($input['contact_person'] ?? ''),
                'notes' => trim($input['notes'] ?? ''),
                'status' => $input['status'] ?? 'ACTIVE',
                'credit_period' => intval($input['credit_period'] ?? 30),
                'default_payment_mode' => $input['default_payment_mode'] ?? 'Bank Transfer',
                'updated_by' => $user->id,
            ]);

            // Re-sync addresses
            $addresses = $input['addresses'] ?? $input['shipping_addresses'] ?? null;
            if (is_array($addresses)) {
                SupplierAddress::where('supplier_id', $supplier->id)->delete();
                foreach ($addresses as $addr) {
                    SupplierAddress::create([
                        'company_id' => $companyId,
                        'supplier_id' => $supplier->id,
                        'type' => $addr['type'] ?? 'OFFICE',
                        'address_line1' => $addr['address_line1'] ?? $addr['address'] ?? '',
                        'address_line2' => $addr['address_line2'] ?? '',
                        'city' => $addr['city'] ?? '',
                        'state' => $addr['state'] ?? '',
                        'state_code' => $addr['state_code'] ?? '27',
                        'pincode' => $addr['pincode'] ?? '',
                    ]);
                }
            }

            // Re-sync contacts
            $contacts = $input['contacts'] ?? $input['additional_contacts'] ?? null;
            if (is_array($contacts)) {
                SupplierContact::where('supplier_id', $supplier->id)->delete();
                foreach ($contacts as $contact) {
                    SupplierContact::create([
                        'company_id' => $companyId,
                        'supplier_id' => $supplier->id,
                        'name' => $contact['name'] ?? '',
                        'phone' => $contact['phone'] ?? '',
                        'email' => $contact['email'] ?? '',
                    ]);
                }
            }

            // Re-sync bank accounts
            if (isset($input['bank_accounts']) && is_array($input['bank_accounts'])) {
                SupplierBankAccount::where('supplier_id', $supplier->id)->delete();
                foreach ($input['bank_accounts'] as $bank) {
                    SupplierBankAccount::create([
                        'company_id' => $companyId,
                        'supplier_id' => $supplier->id,
                        'bank_name' => $bank['bank_name'] ?? '',
                        'account_name' => $bank['account_name'] ?? '',
                        'account_number' => $bank['account_number'] ?? '',
                        'ifsc_code' => $bank['ifsc_code'] ?? '',
                        'branch_name' => $bank['branch_name'] ?? '',
                    ]);
                }
            }

            AuditLogService::log(
                $companyId,
                $user->name,
                'SUPPLIER_UPDATE',
                'Supplier',
                $supplier->id,
                "Updated Supplier '{$supplier->name}'"
            );

            return response_json([
                'status' => 'success',
                'message' => 'Supplier updated successfully.',
                'data' => $supplier->load(['addresses', 'contacts', 'bankAccounts']),
            ]);
        });
    }

    /**
     * Soft delete/deactivate supplier.
     */
    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('suppliers', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Supplier::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Supplier not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $supplier = Supplier::find($id);

        // Check if referenced by purchases
        $hasPurchases = Purchase::where('supplier_id', $supplier->id)->exists();
        if ($hasPurchases) {
            $supplier->update(['status' => 'INACTIVE', 'is_active' => false]);
            AuditLogService::log(
                $companyId,
                $user->name,
                'SUPPLIER_DEACTIVATE',
                'Supplier',
                $supplier->id,
                "Deactivated Supplier '{$supplier->name}' due to transaction history"
            );
            return response_json(['status' => 'success', 'message' => 'Supplier has purchase history and was deactivated instead of deleted.']);
        }

        $supplier->delete();
        AuditLogService::log(
            $companyId,
            $user->name,
            'SUPPLIER_DELETE',
            'Supplier',
            $supplier->id,
            "Deleted Supplier '{$supplier->name}'"
        );

        return response_json(['status' => 'success', 'message' => 'Supplier deleted successfully.']);
    }
}
