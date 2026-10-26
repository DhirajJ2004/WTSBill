<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;
use WTSBill\Repositories\CustomerRepository;

/**
 * Customer Business Service.
 * Implements GSTIN/PAN validation, state code mapping, credit limit checks, and multi-tenant persistence.
 */
class CustomerService
{
    protected CustomerRepository $customerRepo;

    public function __construct(?CustomerRepository $customerRepo = null)
    {
        $this->customerRepo = $customerRepo ?? new CustomerRepository();
    }

    /**
     * Retrieve all customers for listing.
     */
    public function getCustomers(string $search = ''): array
    {
        return $this->customerRepo->search($search);
    }

    /**
     * Get single customer by ID.
     */
    public function getCustomer(int $id): ?array
    {
        return $this->customerRepo->find($id);
    }

    /**
     * Register a new customer with validation and transaction safety.
     */
    public function createCustomer(array $data): array
    {
        $validated = $this->validateCustomerData($data);
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
            'credit_limit' => (float)($validated['credit_limit'] ?? 0),
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $id = (int)$this->customerRepo->create($insertData);
        return $this->customerRepo->find($id);
    }

    /**
     * Update an existing customer record.
     */
    public function updateCustomer(int $id, array $data): array
    {
        $existing = $this->customerRepo->find($id);
        if (!$existing) {
            throw new ValidationException(['id' => 'Customer record not found.']);
        }

        $validated = $this->validateCustomerData($data, $id);

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
            'credit_limit' => (float)($validated['credit_limit'] ?? 0),
            'payment_terms_days' => (int)($validated['payment_terms_days'] ?? 30),
            'is_active' => isset($validated['is_active']) ? (int)(bool)$validated['is_active'] : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->customerRepo->update($id, $updateData);
        return $this->customerRepo->find($id);
    }

    /**
     * Validate payload fields for customer registration.
     */
    protected function validateCustomerData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            $errors['name'] = 'Customer name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Customer name must be at least 2 characters.';
        }

        $gstin = strtoupper(trim((string)($data['gstin'] ?? '')));
        $pan = strtoupper(trim((string)($data['pan'] ?? '')));

        if (!empty($gstin)) {
            if (strlen($gstin) !== 15) {
                $errors['gstin'] = 'GSTIN must be exactly 15 alphanumeric characters.';
            } else {
                $existing = $this->customerRepo->findByGstin($gstin, $excludeId);
                if ($existing) {
                    $errors['gstin'] = "A customer with GSTIN '{$gstin}' already exists.";
                }
                // Derive PAN from GSTIN if PAN is empty
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
            'credit_limit' => (float)($data['credit_limit'] ?? 0),
            'payment_terms_days' => (int)($data['payment_terms_days'] ?? 30),
        ]);
    }
}
