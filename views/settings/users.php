<?php
$pageTitle = 'Users & Role Permissions - WTSBill ERP';
$pageHeader = 'User Management & Access Control';
$currentRoute = 'users';

require_once __DIR__ . '/../db_helper.php';

use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$submitError = null;
$submitSuccess = null;

if (isset($_GET['created'])) {
    $submitSuccess = "User " . htmlspecialchars($_GET['created']) . " registered successfully!";
}
if (isset($_GET['updated'])) {
    $submitSuccess = "User permissions updated successfully!";
}

// -------------------------------------------------------------
// Handle POST for Users
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    $action = $input['action'] ?? 'create_user';

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $currentUserName = $_SESSION['user']['name'] ?? 'Admin';

            if ($action === 'create_user') {
                $name = trim($input['name'] ?? '');
                $email = strtolower(trim($input['email'] ?? ''));
                $role = strtoupper(trim($input['role'] ?? 'STAFF'));
                $password = $input['password'] ?? '';

                if (empty($name) || empty($email) || empty($password)) {
                    throw new \InvalidArgumentException('Name, email, and password are required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Invalid email address format.');
                }

                $user = User::where('email', $email)->first();
                if (!$user) {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'is_active' => true,
                    ]);
                }

                // Associate role with company
                $userRole = UserRole::firstOrNew([
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                ]);
                $userRole->role = $role;
                $userRole->save();

                AuditLogService::log(
                    $companyId,
                    $currentUserName,
                    'USER_CREATE',
                    'User',
                    $user->id,
                    "Created user '{$user->name}' with role '{$role}'"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'message' => "User {$name} created successfully", 'data' => $user], 201);
                } else {
                    $targetUrl = url('/users?created=' . urlencode($name));
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            } elseif ($action === 'update_role') {
                $userId = intval($input['user_id'] ?? 0);
                $role = strtoupper(trim($input['role'] ?? 'STAFF'));
                $status = !empty($input['is_active']) ? 1 : 0;

                $user = User::findOrFail($userId);
                $user->is_active = $status;
                $user->save();

                $userRole = UserRole::firstOrNew([
                    'user_id' => $userId,
                    'company_id' => $companyId,
                ]);
                $userRole->role = $role;
                $userRole->save();

                AuditLogService::log(
                    $companyId,
                    $currentUserName,
                    'USER_UPDATE_ROLE',
                    'User',
                    $userId,
                    "Updated role to '{$role}' and status for user '{$user->name}'"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'message' => "Permissions updated for {$user->name}"]);
                } else {
                    $targetUrl = url('/users?updated=1');
                    if (!headers_sent()) {
                        header("Location: " . $targetUrl);
                        exit;
                    }
                    echo "<script>window.location.href=" . json_encode($targetUrl) . ";</script>";
                    exit;
                }
            }
        } catch (\Throwable $e) {
            if ($isJson) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
            } else {
                $submitError = $e->getMessage();
            }
        }
    }
}

$users = get_users();

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <?php if ($submitSuccess): ?>
            <div class="alert alert-success" style="padding: 14px 18px; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; color: #065f46; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-circle-check text-success" style="font-size: 18px;"></i>
                <span><?= $submitSuccess ?></span>
            </div>
        <?php endif; ?>

        <?php if ($submitError): ?>
            <div class="alert alert-danger" style="padding: 14px 18px; margin-bottom: 20px; background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; color: #991b1b; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size: 18px;"></i>
                <span><?= htmlspecialchars($submitError) ?></span>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-user-shield text-primary"></i> User Accounts &amp; Granular Access Control</div>
                <button class="btn btn-primary" onclick="openModal('addUserModal')">
                    <i class="fa-solid fa-user-plus"></i> + Add User
                </button>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>System Role</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php 
                            $r = strtolower($u['role'] ?? 'staff');
                            $badge = 'info';
                            if ($r === 'admin') $badge = 'danger';
                            elseif ($r === 'accountant') $badge = 'success';
                            elseif ($r === 'sales') $badge = 'warning';
                            $uJson = htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8');
                            ?>
                            <tr>
                                <td><strong>USR-<?= sprintf('%03d', $u['id']) ?></strong></td>
                                <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $badge ?>"><?= strtoupper(htmlspecialchars($u['role'] ?? 'STAFF')) ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?= ($u['status'] ?? 'active') === 'active' ? 'success' : 'secondary' ?>">
                                        <?= strtoupper(htmlspecialchars($u['status'] ?? 'active')) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($u['last_login'] ?? 'Active Recently') ?></td>
                                <td class="text-right">
                                    <button class="btn btn-outline btn-sm" onclick='openEditUserModal(<?= $uJson ?>)'><i class="fa-solid fa-pen"></i> Edit Role</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Permissions Matrix Card -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-key text-primary"></i> Module Access Control Matrix</div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module / Feature</th>
                            <th>Admin</th>
                            <th>Accountant</th>
                            <th>Sales</th>
                            <th>Staff / Read-Only</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Dashboard &amp; Analytics</td>
                            <td><i class="fa-solid fa-check text-success"></i> Full</td>
                            <td><i class="fa-solid fa-check text-success"></i> Full</td>
                            <td><i class="fa-solid fa-check text-success"></i> Limited</td>
                            <td><i class="fa-solid fa-check text-success"></i> Read-Only</td>
                        </tr>
                        <tr>
                            <td>Invoices &amp; Billing</td>
                            <td><i class="fa-solid fa-check text-success"></i> Create/Edit/Delete</td>
                            <td><i class="fa-solid fa-check text-success"></i> Create/Edit</td>
                            <td><i class="fa-solid fa-check text-success"></i> Create Only</td>
                            <td><i class="fa-solid fa-eye text-info"></i> View Only</td>
                        </tr>
                        <tr>
                            <td>Payments &amp; Receipts</td>
                            <td><i class="fa-solid fa-check text-success"></i> Full</td>
                            <td><i class="fa-solid fa-check text-success"></i> Full Record</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i> No Access</td>
                            <td><i class="fa-solid fa-eye text-info"></i> View Receipts</td>
                        </tr>
                        <tr>
                            <td>Settings &amp; System Backup</td>
                            <td><i class="fa-solid fa-check text-success"></i> Full Control</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i> Restricted</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i> Restricted</td>
                            <td><i class="fa-solid fa-xmark text-danger"></i> Restricted</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Add User -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-user-plus text-primary"></i> Create New User Account</div>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/users') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_user">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="john@wtsbill.com" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assign Role *</label>
                        <select name="role" class="form-control" required>
                            <option value="admin">Admin</option>
                            <option value="accountant">Accountant</option>
                            <option value="sales">Sales</option>
                            <option value="staff">Staff / Read-Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Create User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-user-pen text-primary"></i> Edit User Role &amp; Permissions</div>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/users') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">User Name</label>
                    <input type="text" id="editUserName" class="form-control" readonly style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="editUserEmail" class="form-control" readonly style="background: #f1f5f9;">
                </div>
                <div class="form-group">
                    <label class="form-label">System Role *</label>
                    <select name="role" id="editUserRole" class="form-control" required>
                        <option value="ADMIN">Admin (Full Control)</option>
                        <option value="ACCOUNTANT">Accountant (Ledger &amp; Reports)</option>
                        <option value="SALES">Sales Rep (Invoicing &amp; Quotes)</option>
                        <option value="STAFF">Staff (Read-Only)</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: 12px;">
                    <input type="checkbox" name="is_active" id="editUserActive" value="1">
                    <label for="editUserActive" class="form-label" style="margin: 0;">Account Active &amp; Allowed to Log In</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Permissions</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(u) {
    if (!u) return;
    document.getElementById('editUserId').value = u.id;
    document.getElementById('editUserName').value = u.name || '';
    document.getElementById('editUserEmail').value = u.email || '';
    document.getElementById('editUserRole').value = (u.role || 'STAFF').toUpperCase();
    document.getElementById('editUserActive').checked = (u.status !== 'inactive');
    openModal('editUserModal');
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
