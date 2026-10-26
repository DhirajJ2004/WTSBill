<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * Expense Controller.
 * Manages operational expense vouchers, categories, payment methods, and expenditure logs.
 */
class ExpenseController
{
    /**
     * Expense vouchers log view.
     */
    public function index(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $branchId = Session::getBranchId() ?? 1;

        $expenses = Database::select(
            "SELECT id, expense_number, category, payee, expense_date, amount, tax_amount, payment_mode, description 
             FROM expenses 
             WHERE company_id = ? AND (branch_id = ? OR branch_id IS NULL)
             ORDER BY id DESC",
            [$companyId, $branchId]
        );

        $html = View::renderWithLayout('expenses/index', [
            'title' => 'Direct & Indirect Expenses',
            'currentUri' => '/expenses',
            'expenses' => $expenses,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Store new expense voucher.
     */
    public function store(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $payload = !empty($req->json()) ? $req->json() : $req->post();

        $category = trim((string)($payload['category'] ?? 'Operations & Office'));
        $payee = trim((string)($payload['payee'] ?? ''));
        $amount = (float)($payload['amount'] ?? 0);
        $date = trim((string)($payload['expense_date'] ?? date('Y-m-d')));
        $mode = trim((string)($payload['payment_mode'] ?? 'Bank Transfer'));
        $desc = trim((string)($payload['description'] ?? ''));

        if (empty($payee)) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Payee / Beneficiary name is required.'], 422);
            }
            Session::flash('error', 'Payee / Beneficiary name is required.');
            return Response::redirect('/expenses');
        }

        if ($amount <= 0) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => 'Expense amount must be greater than zero.'], 422);
            }
            Session::flash('error', 'Expense amount must be greater than zero.');
            return Response::redirect('/expenses');
        }

        $voucherNumber = 'EXP-' . date('Y') . '-' . rand(1000, 9999);

        try {
            $id = Database::insert('expenses', [
                'company_id' => $companyId,
                'branch_id' => (int)(Session::getBranchId() ?? 1),
                'expense_number' => $voucherNumber,
                'category' => $category,
                'payee' => $payee,
                'expense_date' => $date,
                'amount' => $amount,
                'tax_amount' => 0.0,
                'payment_mode' => $mode,
                'description' => $desc ?: 'Operational expenditure voucher',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->expectsJson($req)) {
                return Response::json([
                    'success' => true,
                    'message' => "Expense voucher '{$voucherNumber}' recorded successfully.",
                ], 201);
            }

            Session::flash('success', "Expense voucher '{$voucherNumber}' recorded successfully.");
            return Response::redirect('/expenses');
        } catch (\Throwable $e) {
            if ($this->expectsJson($req)) {
                return Response::json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            Session::flash('error', 'Failed to record expense: ' . $e->getMessage());
            return Response::redirect('/expenses');
        }
    }

    protected function expectsJson(Request $req): bool
    {
        $accept = (string)($req->header('accept') ?? '');
        $contentType = (string)($req->header('content-type') ?? '');
        $xRequestedWith = (string)($req->header('x-requested-with') ?? '');

        return str_contains($accept, 'application/json') ||
               str_contains($contentType, 'application/json') ||
               strtolower($xRequestedWith) === 'xmlhttprequest';
    }
}
