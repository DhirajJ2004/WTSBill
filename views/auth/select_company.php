<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../db_helper.php';

if (empty($_SESSION['user'])) {
    header('Location: ' . url('/login'));
    exit();
}

$currentUser = get_current_user_info();
$message = '';
$messageType = '';

// Protect state-changing POST requests against CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && !verify_csrf_token()) {
    $message = 'Security validation failed: Invalid or expired CSRF token. Please try again.';
    $messageType = 'danger';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_company') {
    $compName = trim($_POST['company_name'] ?? '');
    if (!empty($compName)) {
        try {
            $created = create_new_company([
                'name' => $compName,
                'company_name' => $compName,
                'legal_name' => trim($_POST['legal_name'] ?? $compName),
                'gstin' => trim($_POST['gstin'] ?? ''),
                'pan' => trim($_POST['pan'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'address' => trim($_POST['address'] ?? ''),
                'city' => trim($_POST['city'] ?? ''),
                'state' => trim($_POST['state'] ?? ''),
                'business_type' => trim($_POST['business_type'] ?? '')
            ]);
            $message = 'Company "' . htmlspecialchars($compName) . '" provisioned successfully!';
            $messageType = 'success';
            // Auto select newly created company
            if (!empty($created['id'])) {
                $_SESSION['selected_company_id'] = $created['id'];
            }
        } catch (\Throwable $e) {
            $message = 'Failed to provision company: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $message = 'Company name cannot be blank.';
        $messageType = 'danger';
    }
}

// Handle Select Company, Branch, and FY submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_workspace') {
    $selectedCompanyId = (int) ($_POST['company_id'] ?? 0);
    $selectedBranchId = (int) ($_POST['branch_id'] ?? 0);
    $selectedFY = trim($_POST['financial_year'] ?? '');
    $userId = (int)($currentUser['id'] ?? 0);

    if (!\App\Auth\WorkspaceContext::verifyCompanyMembership($userId, $selectedCompanyId)) {
        $message = 'Access denied: You are not authorized to access the selected company.';
        $messageType = 'danger';
    } else {
        $branches = \App\Auth\WorkspaceContext::getAuthorizedBranches($userId, $selectedCompanyId);
        if ($selectedBranchId > 0 && !\App\Auth\WorkspaceContext::verifyBranchAccess($userId, $selectedCompanyId, $selectedBranchId)) {
            $message = 'Access denied: You are not authorized to access the selected branch.';
            $messageType = 'danger';
        } else {
            if ($selectedBranchId <= 0 && !empty($branches)) {
                $selectedBranchId = (int)$branches[0]['id'];
            } elseif ($selectedBranchId <= 0) {
                $selectedBranchId = null;
            }

        try {
            $userRole = \App\Auth\WorkspaceContext::getUserRoleForCompany($userId, $selectedCompanyId);
            set_active_workspace($selectedCompanyId, $selectedFY, $userRole, $selectedBranchId);
            header('Location: ' . url('/dashboard'));
            exit();
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }
}
}



$allCompanies = get_all_companies();
$financialYears = get_financial_years();
$activeCompanyId = $_SESSION['selected_company_id'] ?? get_current_company_id();
$activeBranchId = get_current_branch_id();
$activeFY = get_current_financial_year();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Company &bull; WTS LEDGER PRO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --primary-border: #bfdbfe;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --border-focus: #3b82f6;
            --text-main: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --success: #10b981;
            --emerald-bg: #ecfdf5;
            --emerald-border: #a7f3d0;
            --emerald-text: #065f46;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --shadow-card: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            --shadow-float: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-page);
            background-image:
                radial-gradient(at 10% 20%, rgba(37, 99, 235, 0.05) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(16, 185, 129, 0.05) 0px, transparent 50%),
                linear-gradient(to right, #f1f5f9 1px, transparent 1px),
                linear-gradient(to bottom, #f1f5f9 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 20px 48px;
            color: var(--text-main);
        }

        .workspace-container {
            width: 100%;
            max-width: 960px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Top Bar */
        .top-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .user-greeting {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 9999px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .user-avatar-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        .user-name-role {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
        }

        .user-badge {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .btn-logout-link {
            font-size: 13px;
            font-weight: 600;
            color: #ef4444;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 9999px;
            transition: all 0.15s ease;
        }

        .btn-logout-link:hover {
            background: #fef2f2;
            border-color: #fecaca;
        }

        /* Stepper */
        .flow-stepper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .flow-step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 6px 14px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .flow-step.completed {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #16a34a;
        }

        .flow-step.active {
            background: var(--primary-light);
            border-color: var(--primary-border);
            color: var(--primary);
        }

        .flow-step-circle {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
        }

        .flow-step.completed .flow-step-circle {
            background: #16a34a;
            color: #ffffff;
        }

        .flow-step.active .flow-step-circle {
            background: var(--primary);
            color: #ffffff;
        }

        .flow-arrow {
            color: #94a3b8;
            font-size: 11px;
        }

        /* Header titles */
        .header-title-box {
            text-align: center;
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: var(--emerald-bg);
            border: 1px solid var(--emerald-border);
            color: var(--emerald-text);
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* Section Title */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-subtitle {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Companies Grid */
        .companies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .company-card {
            background: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
        }

        .company-card:hover {
            border-color: #93c5fd;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(37, 99, 235, 0.08);
        }

        .company-card.selected {
            border-color: var(--primary);
            background: #f8faff;
            box-shadow: 0 10px 20px -3px rgba(37, 99, 235, 0.12);
        }

        .company-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .company-icon-circle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .company-select-indicator {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .company-card.selected .company-select-indicator {
            border-color: var(--primary);
            background: var(--primary);
            color: #ffffff;
            font-size: 11px;
        }

        .company-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
            margin-bottom: 6px;
        }

        .company-meta-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .company-gstin-badge {
            display: inline-block;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            background: #f1f5f9;
            color: #334155;
            padding: 3px 8px;
            border-radius: 6px;
            margin-top: 6px;
            letter-spacing: 0.03em;
        }

        .company-card.selected .company-gstin-badge {
            background: #dbeafe;
            color: #1e40af;
        }

        /* + Add Company Card */
        .add-company-card {
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-lg);
            padding: 24px 20px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all 0.2s ease;
            min-height: 180px;
        }

        .add-company-card:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .add-company-icon-btn {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #f1f5f9;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }

        .add-company-card:hover .add-company-icon-btn {
            background: var(--primary);
            color: #ffffff;
            transform: scale(1.08);
        }

        .add-company-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .add-company-subtitle {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Financial Year Selector */
        .fy-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-card);
        }

        .fy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .fy-card {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.15s ease;
            background: #ffffff;
        }

        .fy-card:hover {
            border-color: #93c5fd;
            background: #f8fafc;
        }

        .fy-card.selected {
            border-color: var(--primary);
            background: #eff6ff;
        }

        .fy-info {
            display: flex;
            flex-direction: column;
        }

        .fy-code {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
        }

        .fy-dates {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .fy-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 9999px;
            text-transform: uppercase;
        }

        .badge-current {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .badge-audited {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .badge-upcoming {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        /* "After Selecting" Live Summary Card */
        .selection-summary-card {
            background: #ffffff;
            border: 2px solid #bfdbfe;
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.08);
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .selection-summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb, #10b981);
        }

        .summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
        }

        .summary-item-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .summary-item-value {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .summary-item-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Permissions chips */
        .permissions-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }

        .permission-chip {
            font-size: 11px;
            font-weight: 600;
            background: #f1f5f9;
            color: #334155;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid #e2e8f0;
        }

        .permission-chip i {
            color: #10b981;
            font-size: 10px;
        }

        /* Submit Action Row */
        .action-submit-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
        }

        .btn-proceed {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
            transition: all 0.2s ease;
        }

        .btn-proceed:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }

        /* Modal for Add Company */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 560px;
            box-shadow: var(--shadow-float);
            border: 1px solid var(--border-color);
            animation: modalPop 0.25s ease-out;
            overflow: hidden;
        }

        @keyframes modalPop {
            from {
                transform: scale(0.95);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 16px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .btn-close-modal:hover {
            background: #f1f5f9;
            color: var(--text-main);
        }

        .modal-body {
            padding: 24px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .modal-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .col-span-2 {
            grid-column: span 2;
        }

        .modal-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .modal-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
        }

        .modal-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 9px 12px;
            font-size: 13px;
            color: var(--text-main);
            transition: all 0.15s ease;
        }

        .modal-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-cancel {
            background: #ffffff;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            border-radius: var(--radius-sm);
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-modal-cancel:hover {
            background: #f1f5f9;
        }

        .btn-modal-save {
            background: var(--primary);
            border: none;
            color: #ffffff;
            border-radius: var(--radius-sm);
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-modal-save:hover {
            background: var(--primary-hover);
        }

        /* Responsive Breakpoints for Company Selection */
        @media (max-width: 768px) {
            body {
                padding: 16px 12px 32px;
            }
            .top-nav {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }
            .user-greeting {
                justify-content: flex-start;
                border-radius: var(--radius-md);
            }
            .btn-logout-link {
                justify-content: center;
                border-radius: var(--radius-md);
            }
            .flow-stepper {
                flex-wrap: wrap;
                gap: 6px;
            }
            .flow-step {
                padding: 4px 10px;
                font-size: 11px;
            }
            .companies-grid {
                grid-template-columns: 1fr !important;
                gap: 12px;
            }
            .fy-grid {
                grid-template-columns: 1fr !important;
            }
            .summary-grid {
                grid-template-columns: 1fr !important;
                gap: 14px;
            }
            .modal-form-grid {
                grid-template-columns: 1fr !important;
                gap: 12px;
            }
            .col-span-2 {
                grid-column: span 1 !important;
            }
            .action-submit-row {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-proceed {
                width: 100%;
                justify-content: center;
            }
            .modal-overlay {
                padding: 12px;
            }
            .modal-card {
                max-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 20px;
            }
            .page-subtitle {
                font-size: 12px;
            }
            .company-card {
                padding: 14px;
            }
            .fy-card {
                padding: 10px 12px;
            }
        }
    </style>
</head>

<body>

    <div class="workspace-container">
        <!-- Top Nav -->
        <div class="top-nav">
            <div class="user-greeting">
                <div class="user-avatar-badge"><?= htmlspecialchars($currentUser['avatar'] ?? 'AD') ?></div>
                <div class="user-name-role">
                    <span class="user-name"><?= htmlspecialchars($currentUser['name'] ?? 'Anil Desai') ?></span>
                    <span class="user-badge"><?= htmlspecialchars($currentUser['email'] ?? 'anil.d@wtsbill.in') ?>
                        &bull; <?= htmlspecialchars($currentUser['role'] ?? 'Administrator') ?></span>
                </div>
            </div>

            <a href="<?= url('/logout') ?>" class="btn-logout-link" title="Sign out of current account">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Log Out</span>
            </a>
        </div>

        <!-- Stepper Indicator -->
        <div class="flow-stepper">
            <div class="flow-step completed">
                <span class="flow-step-circle"><i class="fa-solid fa-check"></i></span>
                <span>Login & Authenticated</span>
            </div>
            <i class="fa-solid fa-arrow-right flow-arrow"></i>
            <div class="flow-step active">
                <span class="flow-step-circle">2</span>
                <span>Select Company & FY</span>
            </div>
            <i class="fa-solid fa-arrow-right flow-arrow"></i>
            <div class="flow-step">
                <span class="flow-step-circle">3</span>
                <span>Dashboard</span>
            </div>
        </div>

        <!-- Header Titles -->
        <div class="header-title-box">
            <h1 class="page-title">Select Company & Financial Year</h1>
            <p class="page-subtitle">Choose the business entity you want to manage for this billing & accounting session
            </p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fa-solid <?= $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <!-- Main Workspace Selection Form -->
        <form action="<?= url('/select-company') ?>" method="POST" id="workspaceForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="select_workspace">
            <input type="hidden" name="company_id" id="selectedCompanyInput" value="<?= $activeCompanyId ?>">
            <input type="hidden" name="branch_id" id="selectedBranchInput" value="<?= $activeBranchId ?>">
            <input type="hidden" name="financial_year" id="selectedFYInput" value="<?= htmlspecialchars($activeFY) ?>">
            <input type="hidden" name="user_role" id="selectedUserRoleInput"
                value="<?= htmlspecialchars($currentUser['role'] ?? '') ?>">

            <!-- Section 1: Companies Grid -->
            <div class="section-header">
                <div class="section-title">
                    <i class="fa-solid fa-buildings text-primary" style="color: var(--primary);"></i>
                    <span>Your Companies (<?= count($allCompanies) ?> Available)</span>
                </div>
                <div class="section-subtitle">Switch company anytime from top navigation</div>
            </div>

            <div class="companies-grid">
                <?php
                $colors = [
                    ['#2563eb', '#1d4ed8'],
                    ['#059669', '#047857'],
                    ['#7c3aed', '#6d28d9'],
                    ['#ea580c', '#c2410c'],
                    ['#0891b2', '#0e7490']
                ];
                foreach ($allCompanies as $index => $comp):
                    $isSelected = ($comp['id'] == $activeCompanyId);
                    $grad = $colors[$index % count($colors)];
                    $initials = strtoupper(substr($comp['name'], 0, 1));
                    if (strpos($comp['name'], ' ') !== false) {
                        $parts = explode(' ', $comp['name']);
                        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1] ?? '', 0, 1));
                    }
                    ?>
                    <div class="company-card <?= $isSelected ? 'selected' : '' ?>" id="company-card-<?= $comp['id'] ?>"
                        onclick="selectCompanyCard(<?= $comp['id'] ?>, <?= htmlspecialchars(json_encode($comp), ENT_QUOTES, 'UTF-8') ?>)">
                        <div>
                            <div class="company-card-top">
                                <div class="company-icon-circle"
                                    style="background: linear-gradient(135deg, <?= $grad[0] ?>, <?= $grad[1] ?>);">
                                    <?= $initials ?>
                                </div>
                                <div class="company-select-indicator">
                                    <i class="fa-solid fa-check <?= $isSelected ? '' : 'fa-hidden' ?>"
                                        id="check-icon-<?= $comp['id'] ?>"
                                        style="<?= $isSelected ? '' : 'display:none;' ?>"></i>
                                </div>
                            </div>
                            <h3 class="company-name"><?= htmlspecialchars($comp['name']) ?></h3>
                            <div class="company-meta-row">
                                <i class="fa-solid fa-location-dot"></i>
                                <span><?= htmlspecialchars(trim(($comp['city'] ?? '') . (empty($comp['state']) ? '' : ', ' . $comp['state']))) ?></span>
                            </div>
                            <div class="company-meta-row">
                                <i class="fa-solid fa-briefcase"></i>
                                <span><?= htmlspecialchars($comp['business_type'] ?? '') ?></span>
                            </div>
                        </div>
                        <div>
                            <?php if (!empty($comp['gstin'])): ?><span class="company-gstin-badge">GSTIN:
                                    <?= htmlspecialchars($comp['gstin']) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$allCompanies): ?>
                    <p class="section-subtitle">No companies found in the database.</p><?php endif; ?>

                <!-- + Add / Create Company Card -->
                <div class="add-company-card" onclick="openCreateCompanyModal()">
                    <div class="add-company-icon-btn">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <h4 class="add-company-title">+ Add / Create Company</h4>
                    <p class="add-company-subtitle">Register a new sister firm or separate GSTIN</p>
                </div>
            </div>

            <!-- Section 2: Financial Year Selection -->
            <div class="fy-section">
                <div class="section-title">
                    <i class="fa-regular fa-calendar-days text-primary" style="color: var(--primary);"></i>
                    <span>Select Financial Year</span>
                </div>
                <div class="fy-grid">
                    <?php foreach ($financialYears as $fy):
                        $isFySelected = ($fy['code'] === $activeFY);
                        $badgeClass = ($fy['badge'] === 'CURRENT') ? 'badge-current' : (($fy['badge'] === 'AUDITED') ? 'badge-audited' : 'badge-upcoming');
                        ?>
                        <div class="fy-card <?= $isFySelected ? 'selected' : '' ?>"
                            id="fy-card-<?= str_replace([' ', '-'], '_', $fy['code']) ?>"
                            onclick="selectFinancialYear('<?= htmlspecialchars($fy['code']) ?>', '<?= htmlspecialchars($fy['dates']) ?>')">
                            <div class="fy-info">
                                <span class="fy-code"><?= htmlspecialchars($fy['title']) ?></span>
                                <span class="fy-dates"><?= htmlspecialchars($fy['dates']) ?></span>
                            </div>
                            <span class="fy-badge <?= $badgeClass ?>"><?= htmlspecialchars($fy['badge']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$financialYears): ?>
                        <p class="section-subtitle">No financial years found for this company.</p><?php endif; ?>
                </div>
            </div>

            <!-- Section 3: "After Selecting" Live Summary Card -->
            <?php
            $currentComp = get_company_by_id($activeCompanyId);
            $perms = $currentComp['permissions'] ?? ['Full ERP Access', 'GST Billing & Invoicing', 'Purchases & Inward', 'Banking & Ledgers', 'Financial Audit Reports'];
            ?>
            <div class="selection-summary-card">
                <div class="summary-header">
                    <div class="summary-title">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <span>Selected Workspace Configuration</span>
                    </div>
                    <span
                        style="font-size: 11px; background: #dcfce7; color: #15803d; font-weight: 700; padding: 4px 10px; border-radius: 9999px;">
                        <i class="fa-solid fa-circle-dot" style="font-size: 8px;"></i> Ready to Launch
                    </span>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <span class="summary-item-label"><i class="fa-solid fa-building"></i> Company</span>
                        <span class="summary-item-value"
                            id="summaryCompanyName"><?= htmlspecialchars($currentComp['name'] ?? 'Wis Technosavvy Pvt Ltd') ?></span>
                        <span class="summary-item-sub" id="summaryCompanyGstin">GSTIN:
                            <?= htmlspecialchars($currentComp['gstin'] ?? '27AADCW7577N1ZE') ?></span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-item-label"><i class="fa-regular fa-calendar"></i> Financial Year</span>
                        <span class="summary-item-value" id="summaryFY"><?= htmlspecialchars($activeFY) ?></span>
                        <span class="summary-item-sub" id="summaryFYDates">01-Apr-2025 to 31-Mar-2026</span>
                    </div>

                    <div class="summary-item">
                        <span class="summary-item-label"><i class="fa-solid fa-shield-halved"></i> User Role</span>
                        <span class="summary-item-value"
                            id="summaryRole"><?= htmlspecialchars($currentUser['role'] ?? 'Administrator') ?></span>
                        <span class="summary-item-sub">Assigned to
                            <?= htmlspecialchars($currentUser['name'] ?? 'Anil Desai') ?></span>
                    </div>
                </div>

                <div class="summary-item" style="border-top: 1px dashed var(--border-color); padding-top: 14px;">
                    <span class="summary-item-label"><i class="fa-solid fa-key"></i> Active Role Permissions</span>
                    <div class="permissions-chips" id="summaryPermissions">
                        <?php foreach ($perms as $p): ?>
                            <span class="permission-chip"><i class="fa-solid fa-check"></i>
                                <?= htmlspecialchars($p) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Submit Button Row -->
            <div class="action-submit-row">
                <span style="font-size: 13px; color: var(--text-muted);">
                    <i class="fa-solid fa-lock" style="color: #10b981;"></i> All operations will be strictly scoped to
                    this company
                </span>
                <button type="submit" class="btn-proceed" id="enterDashboardBtn">
                    <span>Enter Workspace Dashboard</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Add / Create Company Modal -->
    <div class="modal-overlay" id="addCompanyModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-building-circle-arrow-right text-primary" style="color: var(--primary);"></i>
                    <span>Register New Company</span>
                </h3>
                <button type="button" class="btn-close-modal" onclick="closeCreateCompanyModal()">&times;</button>
            </div>
            <form action="<?= url('/select-company') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_company">
                <div class="modal-body">
                    <div class="modal-form-grid">
                        <div class="modal-form-group col-span-2">
                            <label class="modal-label">Company Brand Name *</label>
                            <input type="text" name="company_name" class="modal-input"
                                placeholder="e.g. Wis Cloud Technologies Pvt Ltd" required>
                        </div>

                        <div class="modal-form-group col-span-2">
                            <label class="modal-label">Legal Name (As on Certificate of Incorporation)</label>
                            <input type="text" name="legal_name" class="modal-input"
                                placeholder="Legal registered entity name">
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-label">GSTIN Number *</label>
                            <input type="text" name="gstin" class="modal-input" placeholder="27XXXXX1234X1ZX"
                                maxlength="15" style="text-transform: uppercase;">
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-label">PAN Number</label>
                            <input type="text" name="pan" class="modal-input" placeholder="ABCDE1234F" maxlength="10"
                                style="text-transform: uppercase;">
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-label">Official Email</label>
                            <input type="email" name="email" class="modal-input" placeholder="finance@company.com">
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-label">Phone / Mobile</label>
                            <input type="text" name="phone" class="modal-input" placeholder="+91 98000 00000">
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-label">City</label>
                            <input type="text" name="city" class="modal-input" value="Pune" placeholder="City">
                        </div>

                        <div class="modal-form-group">
                            <label class="modal-label">State</label>
                            <input type="text" name="state" class="modal-input" value="Maharashtra (27)"
                                placeholder="State & Code">
                        </div>

                        <div class="modal-form-group col-span-2">
                            <label class="modal-label">Registered Office Address</label>
                            <input type="text" name="address" class="modal-input"
                                placeholder="Premises, Street, Landmark">
                        </div>

                        <div class="modal-form-group col-span-2">
                            <label class="modal-label">Business Nature</label>
                            <input type="text" name="business_type" class="modal-input"
                                value="IT Software & Hardware Services"
                                placeholder="e.g. Services, Manufacturing, Retail">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeCreateCompanyModal()">Cancel</button>
                    <button type="submit" class="btn-modal-save">
                        <i class="fa-solid fa-check"></i>
                        <span>Save & Select Company</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const allCompaniesData = <?= json_encode($allCompanies, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function selectCompanyCard(id, compData) {
            // Deselect all
            document.querySelectorAll('.company-card').forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelectorAll('.company-select-indicator i').forEach(el => {
                el.style.display = 'none';
            });

            // Select target
            const card = document.getElementById('company-card-' + id);
            if (card) {
                card.classList.add('selected');
            }
            const icon = document.getElementById('check-icon-' + id);
            if (icon) {
                icon.style.display = 'inline-block';
            }

            // Set hidden input
            document.getElementById('selectedCompanyInput').value = id;

            // Update Live Summary Box
            if (compData) {
                document.getElementById('summaryCompanyName').textContent = compData.name;
                document.getElementById('summaryCompanyGstin').textContent = 'GSTIN: ' + (compData.gstin || 'N/A');

                // Update permissions chips
                const permsDiv = document.getElementById('summaryPermissions');
                permsDiv.innerHTML = '';
                const perms = compData.permissions || ['Full ERP Access', 'GST Billing & Invoicing', 'Purchases & Inward', 'Banking & Ledgers', 'Financial Audit Reports'];
                perms.forEach(p => {
                    const span = document.createElement('span');
                    span.className = 'permission-chip';
                    span.innerHTML = '<i class="fa-solid fa-check"></i> ' + p;
                    permsDiv.appendChild(span);
                });
            }
        }

        function selectFinancialYear(code, dates) {
            document.querySelectorAll('.fy-card').forEach(el => {
                el.classList.remove('selected');
            });
            const cardId = 'fy-card-' + code.replace(/[\s-]/g, '_');
            const card = document.getElementById(cardId);
            if (card) {
                card.classList.add('selected');
            }
            document.getElementById('selectedFYInput').value = code;

            // Update Live Summary
            document.getElementById('summaryFY').textContent = code;
            document.getElementById('summaryFYDates').textContent = dates;
        }

        function openCreateCompanyModal() {
            document.getElementById('addCompanyModal').classList.add('active');
        }

        function closeCreateCompanyModal() {
            document.getElementById('addCompanyModal').classList.remove('active');
        }

        // Close modal on click outside
        window.addEventListener('click', function (e) {
            const modal = document.getElementById('addCompanyModal');
            if (e.target === modal) {
                closeCreateCompanyModal();
            }
        });
    </script>

</body>

</html>