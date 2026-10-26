<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use WTSBill\Core\Exceptions\ValidationException;
use WTSBill\Core\Session;
use WTSBill\Repositories\ProductRepository;

/**
 * Product & Inventory Management Domain Service.
 * Implements business validation, SKU uniqueness enforcement, GST compliance,
 * and stock balance reconciliation.
 */
class ProductService
{
    protected ProductRepository $productRepo;

    public function __construct(?ProductRepository $productRepo = null)
    {
        $this->productRepo = $productRepo ?? new ProductRepository();
    }

    /**
     * Retrieve catalog listing with search and category relationships.
     */
    public function getCatalog(string $search = ''): array
    {
        return $this->productRepo->getCatalog($search);
    }

    /**
     * Find a product by ID.
     */
    public function getProduct(int $id): ?array
    {
        return $this->productRepo->find($id);
    }

    /**
     * Validate and create a new inventory product.
     *
     * @throws ValidationException
     */
    public function createProduct(array $data): array
    {
        $errors = $this->validateProductData($data);
        if (!empty($errors)) {
            throw new ValidationException($errors, 'Product validation failed.');
        }

        $companyId = Session::getCompanyId() ?? 1;
        $sku = strtoupper(trim((string)($data['sku'] ?? '')));

        // Enforce SKU uniqueness within the company
        $existing = $this->productRepo->findBySku($sku);
        if ($existing !== null) {
            throw new ValidationException([
                'sku' => "SKU '{$sku}' is already assigned to product '{$existing['name']}'."
            ], 'Product code / SKU already exists.');
        }

        $salesPrice = round((float)($data['sales_price'] ?? ($data['selling_price'] ?? 0)), 2);
        $purchasePrice = round((float)($data['purchase_price'] ?? 0), 2);
        $taxRate = (float)($data['tax_rate'] ?? ($data['gst_rate'] ?? 18.0));
        $openingStock = (float)($data['opening_stock'] ?? ($data['current_stock'] ?? 0));
        $minStock = (float)($data['min_stock_alert'] ?? 10.0);

        // Normalize payload to fit database schema
        $insertData = [
            'company_id' => $companyId,
            'name' => trim((string)$data['name']),
            'sku' => $sku,
            'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'unit' => !empty($data['unit']) ? trim((string)$data['unit']) : 'Pcs',
            'hsn_sac' => !empty($data['hsn_sac']) ? trim((string)$data['hsn_sac']) : (!empty($data['hsn_code']) ? trim((string)$data['hsn_code']) : null),
            'sales_price' => $salesPrice,
            'selling_price' => $salesPrice,
            'purchase_price' => $purchasePrice,
            'tax_rate' => $taxRate,
            'gst_rate' => $taxRate,
            'current_stock' => $openingStock,
            'opening_stock' => $openingStock,
            'min_stock_alert' => $minStock,
            'barcode' => !empty($data['barcode']) ? trim((string)$data['barcode']) : null,
            'description' => !empty($data['description']) ? trim((string)$data['description']) : null,
            'product_type' => !empty($data['product_type']) ? trim((string)$data['product_type']) : 'Goods',
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Database::beginTransaction();
        try {
            $productId = (int)$this->productRepo->create($insertData);

            // If opening stock exists and warehouse table is present, initialize stock balance
            if ($openingStock > 0) {
                $defaultWarehouse = Database::selectOne(
                    "SELECT id FROM warehouses WHERE company_id = ? ORDER BY id ASC LIMIT 1",
                    [$companyId]
                );
                $warehouseId = $defaultWarehouse['id'] ?? 1;

                Database::insert('stock_balances', [
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => $openingStock,
                    'reserved_quantity' => 0.000,
                    'available_quantity' => $openingStock,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Database::commit();

            return array_merge($insertData, ['id' => $productId]);
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Validate and update an existing product.
     *
     * @throws ValidationException
     */
    public function updateProduct(int $id, array $data): array
    {
        $existing = $this->productRepo->find($id);
        if ($existing === null) {
            throw new ValidationException(['id' => 'Product does not exist.'], 'Product not found.');
        }

        $errors = $this->validateProductData($data, true);
        if (!empty($errors)) {
            throw new ValidationException($errors, 'Product validation failed.');
        }

        $sku = strtoupper(trim((string)($data['sku'] ?? $existing['sku'])));

        // Enforce SKU uniqueness excluding current product
        $duplicate = $this->productRepo->findBySku($sku, $id);
        if ($duplicate !== null) {
            throw new ValidationException([
                'sku' => "SKU '{$sku}' is already assigned to product '{$duplicate['name']}'."
            ], 'Product code / SKU already exists.');
        }

        $salesPrice = round((float)($data['sales_price'] ?? ($data['selling_price'] ?? $existing['sales_price'])), 2);
        $purchasePrice = round((float)($data['purchase_price'] ?? $existing['purchase_price']), 2);
        $taxRate = (float)($data['tax_rate'] ?? ($data['gst_rate'] ?? $existing['tax_rate']));

        $updateData = [
            'name' => trim((string)($data['name'] ?? $existing['name'])),
            'sku' => $sku,
            'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : $existing['category_id'],
            'unit' => !empty($data['unit']) ? trim((string)$data['unit']) : $existing['unit'],
            'hsn_sac' => isset($data['hsn_sac']) ? trim((string)$data['hsn_sac']) : $existing['hsn_sac'],
            'sales_price' => $salesPrice,
            'selling_price' => $salesPrice,
            'purchase_price' => $purchasePrice,
            'tax_rate' => $taxRate,
            'gst_rate' => $taxRate,
            'min_stock_alert' => isset($data['min_stock_alert']) ? (float)$data['min_stock_alert'] : (float)$existing['min_stock_alert'],
            'barcode' => isset($data['barcode']) ? trim((string)$data['barcode']) : $existing['barcode'],
            'description' => isset($data['description']) ? trim((string)$data['description']) : $existing['description'],
            'is_active' => isset($data['is_active']) ? (int)(bool)$data['is_active'] : (int)$existing['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->productRepo->update($id, $updateData);

        return array_merge($existing, $updateData);
    }

    /**
     * Delete or deactivate a product.
     */
    public function deleteProduct(int $id): bool
    {
        $existing = $this->productRepo->find($id);
        if ($existing === null) {
            return false;
        }

        // Soft-deactivate by setting is_active = 0
        $this->productRepo->update($id, [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    /**
     * Validate product input fields.
     */
    protected function validateProductData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (!$isUpdate || isset($data['name'])) {
            $name = trim((string)($data['name'] ?? ''));
            if (empty($name)) {
                $errors['name'] = 'Product name is required.';
            } elseif (strlen($name) < 2) {
                $errors['name'] = 'Product name must be at least 2 characters.';
            } elseif (strlen($name) > 255) {
                $errors['name'] = 'Product name cannot exceed 255 characters.';
            }
        }

        if (!$isUpdate || isset($data['sku'])) {
            $sku = trim((string)($data['sku'] ?? ''));
            if (empty($sku)) {
                $errors['sku'] = 'Product code / SKU is required.';
            } elseif (strlen($sku) < 2) {
                $errors['sku'] = 'SKU must be at least 2 characters.';
            } elseif (strlen($sku) > 100) {
                $errors['sku'] = 'SKU cannot exceed 100 characters.';
            }
        }

        if (isset($data['sales_price'])) {
            if (!is_numeric($data['sales_price']) || (float)$data['sales_price'] < 0) {
                $errors['sales_price'] = 'Sales price must be a non-negative number.';
            }
        }

        if (isset($data['purchase_price'])) {
            if (!is_numeric($data['purchase_price']) || (float)$data['purchase_price'] < 0) {
                $errors['purchase_price'] = 'Purchase price must be a non-negative number.';
            }
        }

        if (isset($data['tax_rate'])) {
            if (!is_numeric($data['tax_rate']) || (float)$data['tax_rate'] < 0 || (float)$data['tax_rate'] > 100) {
                $errors['tax_rate'] = 'GST tax rate must be between 0% and 100%.';
            }
        }

        return $errors;
    }
}
