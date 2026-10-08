<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Repositories\PartyRepository;
use App\Validators\PartyValidator;
use App\Middleware\AuthorizationException;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

class PartyService
{
    /**
     * Create a new Customer.
     */
    public static function createCustomer(array $data, int $companyId, ?int $branchId = null, string $userName = 'Admin'): array
    {
        $errors = PartyValidator::validate($data, $companyId, 'customer');
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $gstin = strtoupper(trim($data['gstin'] ?? ''));
        $pan = strtoupper(trim($data['pan'] ?? ''));
        if (empty($pan) && !empty($gstin) && strlen($gstin) >= 12) {
            $pan = substr($gstin, 2, 10);
        }

        $stateCode = null;
        if (!empty($gstin) && strlen($gstin) >= 2) {
            $stateCode = substr($gstin, 0, 2);
        }

        $openingBalance = (float)($data['opening_balance'] ?? 0.0);
        $creditLimit = (float)($data['credit_limit'] ?? 0.0);
        $paymentTerms = (int)($data['payment_terms_days'] ?? ($data['credit_period'] ?? 30));

        $customer = Customer::create([
            'company_id' => $companyId,
            'name' => trim($data['name'] ?? ''),
            'company_name' => trim($data['company_name'] ?? ($data['name'] ?? '')),
            'gstin' => $gstin,
            'pan' => $pan,
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'alt_phone' => trim($data['alt_phone'] ?? ''),
            'address_line1' => trim($data['address_line1'] ?? ($data['address'] ?? '')),
            'city' => trim($data['city'] ?? ''),
            'state' => trim($data['state'] ?? 'Maharashtra'),
            'state_code' => $stateCode ?: '27',
            'pincode' => trim($data['pincode'] ?? ''),
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'credit_limit' => $creditLimit,
            'payment_terms_days' => $paymentTerms,
            'credit_period' => $paymentTerms,
            'customer_type' => trim($data['customer_type'] ?? 'Business'),
            'tax_type' => !empty($gstin) ? 'Registered' : 'Unregistered',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Create contact person if provided
        if (!empty($data['contact_person'])) {
            CustomerContact::create([
                'customer_id' => $customer->id,
                'name' => trim($data['contact_person']),
                'phone' => trim($data['phone'] ?? ''),
                'email' => trim($data['email'] ?? ''),
                'is_primary' => true,
            ]);
        }

        AuditLogService::log(
            $companyId,
            $userName,
            'CUSTOMER_CREATE',
            'Customer',
            $customer->id,
            "Created Customer '{$customer->name}' (ID #{$customer->id})"
        );

        return [
            'success' => true,
            'customer' => $customer,
            'customer_id' => $customer->id,
            'message' => "Customer '{$customer->name}' created successfully."
        ];
    }

    /**
     * Update an existing Customer.
     */
    public static function updateCustomer(int $id, array $data, int $companyId, string $userName = 'Admin'): array
    {
        $customer = Customer::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$customer) {
            return ['success' => false, 'message' => "Customer #{$id} not found or unauthorized."];
        }

        $errors = PartyValidator::validate($data, $companyId, 'customer', $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $gstin = strtoupper(trim($data['gstin'] ?? $customer->gstin));
        $pan = strtoupper(trim($data['pan'] ?? $customer->pan));
        if (empty($pan) && !empty($gstin) && strlen($gstin) >= 12) {
            $pan = substr($gstin, 2, 10);
        }

        $customer->update([
            'name' => trim($data['name'] ?? $customer->name),
            'company_name' => trim($data['company_name'] ?? ($data['name'] ?? $customer->company_name)),
            'gstin' => $gstin,
            'pan' => $pan,
            'email' => trim($data['email'] ?? $customer->email),
            'phone' => trim($data['phone'] ?? $customer->phone),
            'address_line1' => trim($data['address_line1'] ?? ($data['address'] ?? $customer->address_line1)),
            'city' => trim($data['city'] ?? $customer->city),
            'state' => trim($data['state'] ?? $customer->state),
            'pincode' => trim($data['pincode'] ?? $customer->pincode),
            'credit_limit' => isset($data['credit_limit']) ? (float)$data['credit_limit'] : $customer->credit_limit,
            'status' => $data['status'] ?? $customer->status,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'CUSTOMER_UPDATE',
            'Customer',
            $customer->id,
            "Updated Customer '{$customer->name}' (ID #{$customer->id})"
        );

        return ['success' => true, 'customer' => $customer, 'customer_id' => $customer->id, 'message' => "Customer '{$customer->name}' updated successfully."];
    }

    /**
     * Delete Customer (Soft-delete with reference protection).
     */
    public static function deleteCustomer(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $customer = Customer::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$customer) {
            return ['success' => false, 'message' => "Customer #{$id} not found or unauthorized."];
        }

        // Check if active uncancelled invoices exist
        $activeInvoicesCount = DB::table('invoices')
            ->where('company_id', $companyId)
            ->where('customer_id', $id)
            ->where('status', '!=', 'CANCELLED')
            ->whereNull('deleted_at')
            ->count();

        if ($activeInvoicesCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete customer: {$activeInvoicesCount} active invoice(s) exist for this customer. Please cancel or archive invoices first."
            ];
        }

        $customer->delete();

        AuditLogService::log(
            $companyId,
            $userName,
            'CUSTOMER_DELETE',
            'Customer',
            $id,
            "Deleted Customer '{$customer->name}' (ID #{$id})"
        );

        return ['success' => true, 'message' => "Customer '{$customer->name}' deleted successfully."];
    }

    /**
     * Create a new Supplier.
     */
    public static function createSupplier(array $data, int $companyId, ?int $branchId = null, string $userName = 'Admin'): array
    {
        $errors = PartyValidator::validate($data, $companyId, 'supplier');
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $gstin = strtoupper(trim($data['gstin'] ?? ''));
        $pan = strtoupper(trim($data['pan'] ?? ''));
        if (empty($pan) && !empty($gstin) && strlen($gstin) >= 12) {
            $pan = substr($gstin, 2, 10);
        }

        $openingBalance = (float)($data['opening_balance'] ?? 0.0);
        $creditPeriod = (int)($data['credit_period'] ?? ($data['payment_terms_days'] ?? 30));

        $supplier = Supplier::create([
            'company_id' => $companyId,
            'name' => trim($data['name'] ?? ''),
            'company_name' => trim($data['company_name'] ?? ($data['name'] ?? '')),
            'gstin' => $gstin,
            'pan' => $pan,
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'address_line1' => trim($data['address_line1'] ?? ($data['address'] ?? '')),
            'city' => trim($data['city'] ?? ''),
            'state' => trim($data['state'] ?? 'Maharashtra'),
            'state_code' => !empty($gstin) ? substr($gstin, 0, 2) : '27',
            'pincode' => trim($data['pincode'] ?? ''),
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'credit_period' => $creditPeriod,
            'tax_type' => !empty($gstin) ? 'Registered' : 'Unregistered',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'SUPPLIER_CREATE',
            'Supplier',
            $supplier->id,
            "Created Supplier '{$supplier->name}' (ID #{$supplier->id})"
        );

        return [
            'success' => true,
            'supplier' => $supplier,
            'supplier_id' => $supplier->id,
            'message' => "Supplier '{$supplier->name}' created successfully."
        ];
    }

    /**
     * Update an existing Supplier.
     */
    public static function updateSupplier(int $id, array $data, int $companyId, string $userName = 'Admin'): array
    {
        $supplier = Supplier::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$supplier) {
            return ['success' => false, 'message' => "Supplier #{$id} not found or unauthorized."];
        }

        $errors = PartyValidator::validate($data, $companyId, 'supplier', $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => reset($errors)];
        }

        $gstin = strtoupper(trim($data['gstin'] ?? $supplier->gstin));
        $pan = strtoupper(trim($data['pan'] ?? $supplier->pan));

        $supplier->update([
            'name' => trim($data['name'] ?? $supplier->name),
            'company_name' => trim($data['company_name'] ?? ($data['name'] ?? $supplier->company_name)),
            'gstin' => $gstin,
            'pan' => $pan,
            'email' => trim($data['email'] ?? $supplier->email),
            'phone' => trim($data['phone'] ?? $supplier->phone),
            'address_line1' => trim($data['address_line1'] ?? ($data['address'] ?? $supplier->address_line1)),
            'city' => trim($data['city'] ?? $supplier->city),
            'state' => trim($data['state'] ?? $supplier->state),
            'pincode' => trim($data['pincode'] ?? $supplier->pincode),
            'status' => $data['status'] ?? $supplier->status,
        ]);

        AuditLogService::log(
            $companyId,
            $userName,
            'SUPPLIER_UPDATE',
            'Supplier',
            $supplier->id,
            "Updated Supplier '{$supplier->name}' (ID #{$supplier->id})"
        );

        return ['success' => true, 'supplier' => $supplier, 'supplier_id' => $supplier->id, 'message' => "Supplier '{$supplier->name}' updated successfully."];
    }

    /**
     * Delete Supplier (Soft-delete with reference protection).
     */
    public static function deleteSupplier(int $id, int $companyId, string $userName = 'Admin'): array
    {
        $supplier = Supplier::withoutGlobalScopes()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        if (!$supplier) {
            return ['success' => false, 'message' => "Supplier #{$id} not found or unauthorized."];
        }

        $activePurchasesCount = DB::table('purchases')
            ->where('company_id', $companyId)
            ->where('supplier_id', $id)
            ->whereNull('deleted_at')
            ->count();

        if ($activePurchasesCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete supplier: {$activePurchasesCount} purchase record(s) reference this supplier."
            ];
        }

        $supplier->delete();

        AuditLogService::log(
            $companyId,
            $userName,
            'SUPPLIER_DELETE',
            'Supplier',
            $id,
            "Deleted Supplier '{$supplier->name}' (ID #{$id})"
        );

        return ['success' => true, 'message' => "Supplier '{$supplier->name}' deleted successfully."];
    }

    /**
     * Get Customer by ID for tenant.
     */
    public static function getCustomer(int $id, int $companyId)
    {
        return PartyRepository::findCustomer($id, $companyId);
    }

    /**
     * Get Supplier by ID for tenant.
     */
    public static function getSupplier(int $id, int $companyId)
    {
        return PartyRepository::findSupplier($id, $companyId);
    }

    /**
     * Get Customer Transaction Ledger.
     */
    public static function getCustomerLedger(int $customerId, int $companyId): array
    {
        return PartyRepository::getCustomerTransactions($customerId, $companyId);
    }

    /**
     * Get Supplier Transaction Ledger.
     */
    public static function getSupplierLedger(int $supplierId, int $companyId): array
    {
        return PartyRepository::getSupplierTransactions($supplierId, $companyId);
    }

    /**
     * Get Party Details with calculated balance and transaction history.
     */
    public static function getPartyDetails(int $id, string $type, int $companyId): array
    {
        if ($type === 'supplier') {
            $party = PartyRepository::findSupplier($id, $companyId);
            if (!$party) {
                return ['success' => false, 'message' => "Supplier #{$id} not found or unauthorized."];
            }
            $history = PartyRepository::getSupplierTransactions($id, $companyId);
            $balance = (float)$party->current_balance;
        } else {
            $party = PartyRepository::findCustomer($id, $companyId);
            if (!$party) {
                return ['success' => false, 'message' => "Customer #{$id} not found or unauthorized."];
            }
            $history = PartyRepository::getCustomerTransactions($id, $companyId);
            $balance = PartyRepository::computeCustomerBalance($id, $companyId);
        }

        return [
            'success' => true,
            'party' => $party,
            'type' => $type,
            'balance' => $balance,
            'history' => $history,
        ];
    }
}
