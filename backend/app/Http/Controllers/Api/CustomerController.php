<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class CustomerController
{
    /**
     * List customers with pagination, search, sorting and filtering.
     */
    public function index()
    {
        $user = AuthMiddleware::authorize('customers', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $query = Customer::with(['addresses', 'contacts', 'group']);

        // 1. Search Query
        if (!empty($_GET['search'])) {
            $search = trim($_GET['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        // 2. Status Filter
        if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
            $query->where('status', $_GET['status']);
        }

        // 3. Customer Type Filter (Individual / Business)
        if (!empty($_GET['customer_type']) && $_GET['customer_type'] !== 'all') {
            $query->where('customer_type', $_GET['customer_type']);
        }

        // 4. Tax Type Filter
        if (!empty($_GET['tax_type']) && $_GET['tax_type'] !== 'all') {
            $query->where('tax_type', $_GET['tax_type']);
        }

        // 5. Group Filter
        if (!empty($_GET['customer_group_id']) && $_GET['customer_group_id'] !== 'all') {
            $query->where('customer_group_id', intval($_GET['customer_group_id']));
        }

        // 6. Sorting
        $sortBy = $_GET['sort_by'] ?? 'name';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'created_at', 'current_balance'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $order);
        } else {
            $query->orderBy('name', 'asc');
        }

        // 7. Pagination
        $perPage = max(1, min(100, intval($_GET['per_page'] ?? 50)));
        $page = max(1, intval($_GET['page'] ?? 1));
        $total = $query->count();
        $customers = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response_json([
            'status' => 'success',
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
            'data' => $customers,
        ]);
    }

    public function show($id)
    {
        $user = AuthMiddleware::authorize('customers', 'view');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Customer::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Customer not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $customer = Customer::with(['addresses', 'contacts', 'group'])->find($id);

        // Aggregate summaries (from actual transaction models where available)
        $invoiceStats = DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_sales, COALESCE(SUM(amount_due), 0) as outstanding, COALESCE(SUM(amount_paid), 0) as paid')
            ->first();

        $lastInvoice = DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->whereNull('deleted_at')
            ->orderBy('invoice_date', 'desc')
            ->first();

        return response_json([
            'status' => 'success',
            'data' => $customer,
            'summary' => [
                'total_sales' => $invoiceStats ? floatval($invoiceStats->total_sales) : 0,
                'outstanding' => $invoiceStats ? floatval($invoiceStats->outstanding) : 0,
                'payments_received' => $invoiceStats ? floatval($invoiceStats->paid) : 0,
                'last_transaction' => $lastInvoice ? [
                    'id' => $lastInvoice->id,
                    'number' => $lastInvoice->invoice_number,
                    'date' => $lastInvoice->invoice_date,
                    'amount' => floatval($lastInvoice->grand_total),
                ] : null,
            ]
        ]);
    }

    /**
     * Create customer.
     */
    public function store()
    {
        $user = AuthMiddleware::authorize('customers', 'create');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        // 1. Validation
        $name = trim($input['name'] ?? '');
        if (empty($name) || strlen($name) < 2) {
            return response_json(['status' => 'error', 'message' => 'Customer Name is required and must be at least 2 characters.'], 422);
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
            $existing = Customer::where('company_id', $companyId)->where('gstin', $gstin)->where('status', 'ACTIVE')->first();
            if ($existing) {
                return response_json(['status' => 'error', 'message' => "An active customer with GSTIN '{$gstin}' already exists in your company directory."], 422);
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

        $customer = DB::transaction(function () use ($input, $name, $gstin, $pan, $phone, $email, $address, $city, $state, $stateCode, $companyId, $user) {
            $customer = Customer::create([
                'company_id' => $companyId,
                'customer_group_id' => !empty($input['customer_group_id']) ? intval($input['customer_group_id']) : null,
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
                'credit_limit' => floatval($input['credit_limit'] ?? 100000),
                'payment_terms_days' => intval($input['payment_terms_days'] ?? 30),
                'customer_type' => $input['customer_type'] ?? 'Business',
                'alt_phone' => trim($input['alt_phone'] ?? ''),
                'tax_type' => $input['tax_type'] ?? 'Unregistered',
                'place_of_supply' => trim($input['place_of_supply'] ?? $input['state_code'] ?? '27'),
                'status' => 'ACTIVE',
                'notes' => trim($input['notes'] ?? ''),
                'credit_period' => intval($input['credit_period'] ?? 30),
                'opening_balance_type' => $input['opening_balance_type'] ?? 'Debit',
                'default_price_list' => $input['default_price_list'] ?? 'Retail',
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            // Save multiple addresses
            $addresses = $input['addresses'] ?? $input['shipping_addresses'] ?? [];
            if (!empty($addresses) && is_array($addresses)) {
                foreach ($addresses as $addr) {
                    CustomerAddress::create([
                        'company_id' => $companyId,
                        'customer_id' => $customer->id,
                        'type' => $addr['type'] ?? 'BILLING',
                        'address_line1' => $addr['address_line1'] ?? $addr['address'] ?? '',
                        'address_line2' => $addr['address_line2'] ?? '',
                        'city' => $addr['city'] ?? '',
                        'state' => $addr['state'] ?? '',
                        'state_code' => $addr['state_code'] ?? '27',
                        'pincode' => $addr['pincode'] ?? '',
                        'country' => $addr['country'] ?? 'India',
                        'landmark' => $addr['landmark'] ?? '',
                        'is_default' => !empty($addr['is_default']) || !empty($addr['isDefault']),
                    ]);
                }
            } else {
                // Seed default billing address from main input
                CustomerAddress::create([
                    'company_id' => $companyId,
                    'customer_id' => $customer->id,
                    'type' => 'BILLING',
                    'address_line1' => $customer->address_line1,
                    'city' => $customer->city,
                    'state' => $customer->state,
                    'state_code' => $customer->state_code,
                    'pincode' => $customer->pincode,
                    'country' => 'India',
                    'is_default' => true,
                ]);
            }

            // Save contact persons
            $contacts = $input['contacts'] ?? $input['additional_contacts'] ?? [];
            if (!empty($contacts) && is_array($contacts)) {
                foreach ($contacts as $contact) {
                    CustomerContact::create([
                        'company_id' => $companyId,
                        'customer_id' => $customer->id,
                        'name' => $contact['name'] ?? '',
                        'designation' => $contact['designation'] ?? $contact['desig'] ?? '',
                        'email' => $contact['email'] ?? '',
                        'phone' => $contact['phone'] ?? '',
                        'is_primary' => !empty($contact['is_primary']) || !empty($contact['isPrimary']),
                    ]);
                }
            }

            AuditLogService::log(
                $companyId,
                $user->name,
                'CUSTOMER_CREATE',
                'Customer',
                $customer->id,
                "Created Customer '{$customer->name}'"
            );

            return $customer;
        });

        return response_json([
            'status' => 'success',
            'message' => 'Customer created successfully.',
            'data' => $customer->load(['addresses', 'contacts']),
            'customer' => $customer,
        ], 201);
    }

    /**
     * Update customer.
     */
    public function update($id)
    {
        $user = AuthMiddleware::authorize('customers', 'edit');
        $companyId = AuthMiddleware::getTenantId();
        $input = get_json_input();

        $raw = Customer::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Customer not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $customer = Customer::find($id);

        // Validation
        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            return response_json(['status' => 'error', 'message' => 'Customer Name is required.'], 422);
        }

        $gstin = trim($input['gstin'] ?? '');
        if (!empty($gstin)) {
            $gstinPattern = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';
            if (!preg_match($gstinPattern, $gstin)) {
                return response_json(['status' => 'error', 'message' => 'Invalid GSTIN format.'], 422);
            }

            // Duplicate Check
            $existing = Customer::where('gstin', $gstin)
                ->where('id', '!=', $customer->id)
                ->where('status', 'ACTIVE')
                ->first();
            if ($existing) {
                return response_json(['status' => 'error', 'message' => "Another active customer with GSTIN '{$gstin}' already exists."], 422);
            }
        }

        $pan = trim($input['pan'] ?? '');
        if (empty($pan) && strlen($gstin) >= 10) {
            $pan = substr($gstin, 2, 10);
        }

        return DB::transaction(function () use ($customer, $input, $name, $gstin, $pan, $companyId, $user) {
            $customer->update([
                'customer_group_id' => !empty($input['customer_group_id']) ? intval($input['customer_group_id']) : null,
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
                'credit_limit' => floatval($input['credit_limit'] ?? 100000),
                'payment_terms_days' => intval($input['payment_terms_days'] ?? 30),
                'customer_type' => $input['customer_type'] ?? 'Business',
                'alt_phone' => trim($input['alt_phone'] ?? ''),
                'tax_type' => $input['tax_type'] ?? 'Unregistered',
                'place_of_supply' => trim($input['place_of_supply'] ?? $input['state_code'] ?? '27'),
                'status' => $input['status'] ?? 'ACTIVE',
                'notes' => trim($input['notes'] ?? ''),
                'credit_period' => intval($input['credit_period'] ?? 30),
                'opening_balance_type' => $input['opening_balance_type'] ?? 'Debit',
                'default_price_list' => $input['default_price_list'] ?? 'Retail',
                'updated_by' => $user->id,
            ]);

            // Re-sync addresses if provided
            $addresses = $input['addresses'] ?? $input['shipping_addresses'] ?? null;
            if (is_array($addresses)) {
                CustomerAddress::where('customer_id', $customer->id)->delete();
                foreach ($addresses as $addr) {
                    CustomerAddress::create([
                        'company_id' => $companyId,
                        'customer_id' => $customer->id,
                        'type' => $addr['type'] ?? 'BILLING',
                        'address_line1' => $addr['address_line1'] ?? $addr['address'] ?? '',
                        'address_line2' => $addr['address_line2'] ?? '',
                        'city' => $addr['city'] ?? '',
                        'state' => $addr['state'] ?? '',
                        'state_code' => $addr['state_code'] ?? '27',
                        'pincode' => $addr['pincode'] ?? '',
                        'country' => $addr['country'] ?? 'India',
                        'landmark' => $addr['landmark'] ?? '',
                        'is_default' => !empty($addr['is_default']) || !empty($addr['isDefault']),
                    ]);
                }
            }

            // Re-sync contacts if provided
            $contacts = $input['contacts'] ?? $input['additional_contacts'] ?? null;
            if (is_array($contacts)) {
                CustomerContact::where('customer_id', $customer->id)->delete();
                foreach ($contacts as $contact) {
                    CustomerContact::create([
                        'company_id' => $companyId,
                        'customer_id' => $customer->id,
                        'name' => $contact['name'] ?? '',
                        'designation' => $contact['designation'] ?? $contact['desig'] ?? '',
                        'email' => $contact['email'] ?? '',
                        'phone' => $contact['phone'] ?? '',
                        'is_primary' => !empty($contact['is_primary']) || !empty($contact['isPrimary']),
                    ]);
                }
            }

            AuditLogService::log(
                $companyId,
                $user->name,
                'CUSTOMER_UPDATE',
                'Customer',
                $customer->id,
                "Updated Customer '{$customer->name}'"
            );

            return response_json([
                'status' => 'success',
                'message' => 'Customer updated successfully.',
                'data' => $customer->load(['addresses', 'contacts']),
            ]);
        });
    }

    /**
     * Soft delete/deactivate customer.
     */
    public function destroy($id)
    {
        $user = AuthMiddleware::authorize('customers', 'delete');
        $companyId = AuthMiddleware::getTenantId();

        $raw = Customer::withoutGlobalScopes()->find($id);
        if (!$raw) {
            return response_json(['status' => 'error', 'message' => 'Customer not found.'], 404);
        }
        if ((int)$raw->company_id !== (int)$companyId) {
            return response_json(['status' => 'error', 'message' => 'Forbidden: You do not have permission to access resources belonging to another company.'], 403);
        }

        $customer = Customer::find($id);

        // Check if referenced by invoices
        $hasInvoices = Invoice::where('customer_id', $customer->id)->exists();
        if ($hasInvoices) {
            // Cannot delete physically. Deactivate instead.
            $customer->update(['status' => 'INACTIVE', 'is_active' => false]);
            AuditLogService::log(
                $companyId,
                $user->name,
                'CUSTOMER_DEACTIVATE',
                'Customer',
                $customer->id,
                "Deactivated Customer '{$customer->name}' due to transaction history"
            );
            return response_json(['status' => 'success', 'message' => 'Customer has sales history and was deactivated instead of deleted.']);
        }

        // Perform physical soft delete
        $customer->delete();
        AuditLogService::log(
            $companyId,
            $user->name,
            'CUSTOMER_DELETE',
            'Customer',
            $customer->id,
            "Deleted Customer '{$customer->name}'"
        );

        return response_json(['status' => 'success', 'message' => 'Customer deleted successfully.']);
    }
}
