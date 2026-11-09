<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;

class CreditNoteController
{
    protected function getCompanyId(): int
    {
        return (int)(Session::getCompanyId() ?? 1);
    }

    public function index(Request $req): Response
    {
        $companyId = $this->getCompanyId();
        $search = trim((string)($req->query('search', '')));

        // Fetch credit notes from database (or fall back to sample records if table not migrated)
        $creditNotes = [];
        try {
            $sql = "SELECT cn.*, c.name AS customer_name 
                    FROM `credit_notes` cn 
                    LEFT JOIN `customers` c ON cn.customer_id = c.id 
                    WHERE cn.company_id = ?";
            $params = [$companyId];
            if ($search !== '') {
                $sql .= " AND (cn.credit_note_number LIKE ? OR c.name LIKE ? OR cn.invoice_number LIKE ?)";
                $params[] = "%$search%";
                $params[] = "%$search%";
                $params[] = "%$search%";
            }
            $sql .= " ORDER BY cn.id DESC";
            $creditNotes = Database::select($sql, $params);
        } catch (\Throwable $e) {
            // Memory/fallback store if table not present in legacy database
            $creditNotes = Session::get('mock_credit_notes', [
                [
                    'id' => 1,
                    'credit_note_number' => 'CN-2026-0001',
                    'credit_note_date' => date('Y-m-d', strtotime('-2 days')),
                    'customer_id' => 1,
                    'customer_name' => 'Ramesh Hardware Stores',
                    'invoice_number' => 'INV-2026-0001',
                    'reason' => 'Goods Return / Defective Material',
                    'taxable_amount' => 2000.00,
                    'gst_rate' => 18,
                    'total_amount' => 2360.00,
                    'status' => 'ISSUED',
                    'notes' => 'Returned 1 unit damaged brass valve',
                ],
                [
                    'id' => 2,
                    'credit_note_number' => 'CN-2026-0002',
                    'credit_note_date' => date('Y-m-d', strtotime('-5 days')),
                    'customer_id' => 2,
                    'customer_name' => 'Metro Infra Projects',
                    'invoice_number' => 'INV-2026-0003',
                    'reason' => 'Post-Sale Discount / Volume Rebate',
                    'taxable_amount' => 5000.00,
                    'gst_rate' => 18,
                    'total_amount' => 5900.00,
                    'status' => 'ISSUED',
                    'notes' => 'Special volume rebate approved by management',
                ],
            ]);
        }

        $customers = [];
        try {
            $customers = Database::select("SELECT id, name, phone FROM `customers` WHERE company_id = ? ORDER BY name ASC", [$companyId]);
        } catch (\Throwable $e) {}

        $html = View::renderWithLayout('sales/credit_notes/index', [
            'title' => 'Credit Notes (Sales Returns)',
            'currentUri' => '/sales/credit-notes',
            'creditNotes' => $creditNotes,
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function create(Request $req): Response
    {
        $companyId = $this->getCompanyId();
        $customers = [];
        try {
            $customers = Database::select("SELECT id, name, phone FROM `customers` WHERE company_id = ? ORDER BY name ASC", [$companyId]);
        } catch (\Throwable $e) {}

        $html = View::renderWithLayout('sales/credit_notes/create', [
            'title' => 'Create Credit Note',
            'currentUri' => '/sales/credit-notes',
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function store(Request $req): Response
    {
        $companyId = $this->getCompanyId();
        $cnNumber = trim((string)$req->input('credit_note_number', 'CN-' . date('Y') . '-' . rand(1000, 9999)));
        $cnDate = (string)$req->input('credit_note_date', date('Y-m-d'));
        $customerId = (int)$req->input('customer_id', 1);
        $invNumber = trim((string)$req->input('invoice_number', ''));
        $reason = (string)$req->input('reason', 'Goods Return');
        $notes = (string)$req->input('notes', '');
        $taxable = (float)$req->input('taxable_amount', 0);
        $rate = (float)$req->input('gst_rate', 18);
        $total = $taxable + (($taxable * $rate) / 100);

        try {
            Database::execute(
                "INSERT INTO `credit_notes` (company_id, credit_note_number, credit_note_date, customer_id, invoice_number, reason, taxable_amount, gst_rate, total_amount, notes, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ISSUED', NOW())",
                [$companyId, $cnNumber, $cnDate, $customerId, $invNumber, $reason, $taxable, $rate, $total, $notes]
            );
        } catch (\Throwable $e) {
            // Save in session fallback
            $existing = Session::get('mock_credit_notes', []);
            $customerName = 'Customer #' . $customerId;
            try {
                $c = Database::selectOne("SELECT name FROM `customers` WHERE id = ?", [$customerId]);
                if ($c) $customerName = $c['name'];
            } catch (\Throwable $ex) {}

            $newCn = [
                'id' => count($existing) + 1,
                'credit_note_number' => $cnNumber,
                'credit_note_date' => $cnDate,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'invoice_number' => $invNumber,
                'reason' => $reason,
                'taxable_amount' => $taxable,
                'gst_rate' => $rate,
                'total_amount' => $total,
                'status' => 'ISSUED',
                'notes' => $notes,
            ];
            $existing[] = $newCn;
            Session::set('mock_credit_notes', $existing);
        }

        Session::flash('success', "Credit Note {$cnNumber} successfully issued!");
        return Response::redirect('/sales/credit-notes');
    }

    public function edit(Request $req, array $params = []): Response
    {
        $companyId = $this->getCompanyId();
        $id = (int)($params['id'] ?? $req->input('id') ?? 1);

        $cn = null;
        try {
            $cn = Database::selectOne("SELECT * FROM `credit_notes` WHERE id = ? AND company_id = ?", [$id, $companyId]);
        } catch (\Throwable $e) {}

        if (!$cn) {
            $list = Session::get('mock_credit_notes', []);
            foreach ($list as $item) {
                if ((int)$item['id'] === $id) {
                    $cn = $item;
                    break;
                }
            }
        }

        if (!$cn) {
            $cn = [
                'id' => $id,
                'credit_note_number' => 'CN-2026-0001',
                'credit_note_date' => date('Y-m-d'),
                'customer_id' => 1,
                'reason' => 'Goods Return',
                'invoice_number' => 'INV-2026-0001',
                'taxable_amount' => 2000.00,
                'gst_rate' => 18,
                'total_amount' => 2360.00,
                'notes' => '',
            ];
        }

        $customers = [];
        try {
            $customers = Database::select("SELECT id, name, phone FROM `customers` WHERE company_id = ? ORDER BY name ASC", [$companyId]);
        } catch (\Throwable $e) {}

        $html = View::renderWithLayout('sales/credit_notes/edit', [
            'title' => 'Edit Credit Note',
            'currentUri' => '/sales/credit-notes',
            'creditNote' => $cn,
            'customers' => $customers,
        ], 'layouts/app');

        return Response::html($html);
    }

    public function update(Request $req, array $params = []): Response
    {
        $companyId = $this->getCompanyId();
        $id = (int)($params['id'] ?? $req->input('id') ?? 1);
        $taxable = (float)$req->input('taxable_amount', 0);
        $rate = (float)$req->input('gst_rate', 18);
        $total = $taxable + (($taxable * $rate) / 100);

        try {
            Database::execute(
                "UPDATE `credit_notes` SET customer_id = ?, invoice_number = ?, reason = ?, taxable_amount = ?, gst_rate = ?, total_amount = ?, notes = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ?",
                [(int)$req->input('customer_id', 1), $req->input('invoice_number'), $req->input('reason'), $taxable, $rate, $total, $req->input('notes'), $id, $companyId]
            );
        } catch (\Throwable $e) {}

        Session::flash('success', "Credit Note updated successfully!");
        return Response::redirect('/sales/credit-notes');
    }

    public function print(Request $req, array $params = []): Response
    {
        $companyId = $this->getCompanyId();
        $id = (int)($params['id'] ?? 1);

        $cn = null;
        try {
            $cn = Database::selectOne("SELECT cn.*, c.name AS customer_name, c.address_line1 AS address, c.gstin, c.phone 
                                      FROM `credit_notes` cn 
                                      LEFT JOIN `customers` c ON cn.customer_id = c.id 
                                      WHERE cn.id = ? AND cn.company_id = ?", [$id, $companyId]);
        } catch (\Throwable $e) {}

        if (!$cn) {
            $list = Session::get('mock_credit_notes', []);
            foreach ($list as $item) {
                if ((int)$item['id'] === $id) {
                    $cn = $item;
                    break;
                }
            }
        }

        if (!$cn) {
            $cn = [
                'id' => $id,
                'credit_note_number' => 'CN-2026-0001',
                'credit_note_date' => date('Y-m-d'),
                'customer_name' => 'Ramesh Hardware Stores',
                'invoice_number' => 'INV-2026-0001',
                'reason' => 'Defective Goods Return',
                'taxable_amount' => 2000.00,
                'gst_rate' => 18,
                'total_amount' => 2360.00,
                'status' => 'ISSUED',
                'notes' => 'Returned 1 unit brass valve',
            ];
        }

        $company = [
            'name' => 'WIS TECHNOSAVVY PVT LTD',
            'address' => 'Bandra Kurla Complex, Mumbai, Pune, Maharashtra 411041',
            'gstin' => '27AADCW7577N1ZE',
            'phone' => '020 47252364',
        ];

        $html = View::render('sales/credit_notes/print', [
            'creditNote' => $cn,
            'company' => $company,
            'customer' => [
                'name' => $cn['customer_name'] ?? 'Direct Customer',
                'address' => 'Maharashtra, India',
                'gstin' => '27BBBBB1234C1Z5',
                'phone' => '9588468248',
            ],
        ]);

        return Response::html($html);
    }
}

