<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;
use WTSBill\Repositories\SupplierRepository;

/**
 * Supplier Business Service.
 * Implements GSTIN/PAN validation, state mapping, and multi-tenant persistence.
 */
class SupplierService
{
    protected SupplierRepository $supplierRepo;

    public function __construct(?SupplierRepository $supplierRepo = null)
    {
        $this->supplierRepo = $supplierRepo ?? new SupplierRepository();
    }

    /**
     * Retrieve all suppliers for listing.
     */
    public function getSuppliers(string $search = ''): array
    {
        return $this->supplierRepo->search($search);
    }

    /**
     * Get single supplier by ID.
     */
    public function getSupplier(int $id): ?array
    {
        return $this->supplierRepo->find($id);
    }

    /**
     * Register a new supplier with validation and transaction safety.
     */
    public function createSupplier(array $data): array
    {
        $validated = $this->validateSupplierData($data);
        $companyId = Session::getCompanyId() ?? 1;

        $insertData = [
            'company_id' => $companyId,
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'gstin' => $validated['gstin'] ?: null,
            'pan' => $validated['pan'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address_line1' => $validated['address_line1'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'state_code' => $validated['state_code'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'opening_balance' => (float)($validated['opening_balance'] ?? 0),
            'current_balance' => (float)($validated['opening_balance'] ?? 0),
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = (int)$this->supplierRepo->create($insertData);
        return $this->supplierRepo->find($id);
    }

    /**
     * Update an existing supplier record.
     */
    public function updateSupplier(int $id, array $data): array
    {
        $existing = $this->supplierRepo->find($id);
        if (!$existing) {
            throw new ValidationException(['id' => 'Supplier record not found.']);
        }

        $validated = $this->validateSupplierData($data, $id);

        $updateData = [
            'name' => $validated['name'],
            'company_name' => $validated['company_name'] ?? null,
            'gstin' => $validated['gstin'] ?: null,
            'pan' => $validated['pan'] ?: null,
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'address_line1' => $validated['address_line1'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'state_code' => $validated['state_code'] ?? null,
            'pincode' => $validated['pincode'] ?? null,
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->supplierRepo->update($id, $updateData);
        return $this->supplierRepo->find($id);
    }

    /**
     * Validate payload fields for supplier registration.
     */
    protected function validateSupplierData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            $errors['name'] = 'Supplier / Vendor name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Supplier name must be at least 2 characters.';
        }

        $gstin = strtoupper(trim((string)($data['gstin'] ?? '')));
        $pan = strtoupper(trim((string)($data['pan'] ?? '')));

        if (!empty($gstin)) {
            if (strlen($gstin) !== 15) {
                $errors['gstin'] = 'GSTIN must be exactly 15 alphanumeric characters.';
            } else {
                $existing = $this->supplierRepo->findByGstin($gstin, $excludeId);
                if ($existing) {
                    $errors['gstin'] = "A supplier with GSTIN '{$gstin}' already exists.";
                }
                if (empty($pan)) {
                    $pan = substr($gstin, 2, 10);
                }
            }
        }

        $state = trim((string)($data['state'] ?? ''));
        $stateCode = trim((string)($data['state_code'] ?? ''));
        if (!empty($gstin) && empty($stateCode)) {
            $stateCode = substr($gstin, 0, 2);
        }

        $email = trim((string)($data['email'] ?? ''));
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return array_merge($data, [
            'name' => $name,
            'company_name' => trim((string)($data['company_name'] ?? '')),
            'gstin' => $gstin,
            'pan' => $pan,
            'email' => $email,
            'phone' => trim((string)($data['phone'] ?? '')),
            'address_line1' => trim((string)($data['address_line1'] ?? '')),
            'city' => trim((string)($data['city'] ?? '')),
            'state' => $state,
            'state_code' => $stateCode,
            'pincode' => trim((string)($data['pincode'] ?? '')),
            'opening_balance' => (float)($data['opening_balance'] ?? 0),
            'payment_terms_days' => (int)($data['payment_terms_days'] ?? 30),
        ]);
    }
}
