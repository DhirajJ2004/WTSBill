<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../db_helper.php';

use App\Database\Database;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$error = '';
$success = '';
$tokenValid = false;

$email = trim($_REQUEST['email'] ?? '');
$rawToken = trim($_REQUEST['token'] ?? '');

Database::init();

if (empty($email) || empty($rawToken)) {
    $error = 'Missing password reset token or email. Please request a new password reset link.';
} else {
    $hashedToken = hash('sha256', $rawToken);
    $resetRecord = DB::table('password_resets')
        ->where('email', $email)
        ->where('token', $hashedToken)
        ->first();

    if (!$resetRecord) {
        $error = 'This password reset link is invalid or has already been used. Please request a new one.';
    } else {
        $createdAt = strtotime($resetRecord->created_at);
        if (!$createdAt || (time() - $createdAt) > 3600) {
            DB::table('password_resets')->where('email', $email)->delete();
            $error = 'This password reset link has expired (valid for 60 minutes). Please request a new link.';
        } else {
            $tokenValid = true;
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    if (isset($_POST['csrf_token']) && !verify_csrf_token()) {
        $error = 'Security validation failed: Invalid or expired CSRF token. Please refresh and try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirmation'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters in length.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match. Please re-enter your new password.';
        } else {
            try {
                $user = User::where('email', $email)->first();
                if (!$user) {
                    $error = 'User account could not be found.';
                } else {
                    // Update password with secure bcrypt hash
                    $user->update([
                        'password' => password_hash($password, PASSWORD_BCRYPT)
                    ]);

                    // Invalidate the single-use reset token
                    DB::table('password_resets')->where('email', $email)->delete();

                    // Invalidate active personal access tokens for this user
                    DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

                    // Audit Log
                    AuditLogService::log(
                        (int)($user->current_company_id ?: 0),
                        $user->name,
                        'PASSWORD_RESET_COMPLETED',
                        'User',
                        (int)$user->id,
                        "Password reset completed successfully for {$email}."
                    );

                    // Redirect to login with success flag
                    header('Location: ' . url('/login?reset=1'));
                    exit();
                }
            } catch (\Throwable $e) {
                error_log("[RESET PASSWORD ERROR] " . $e->getMessage());
                $error = 'An error occurred while resetting your password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password &bull; WTS LEDGER PRO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --danger: #ef4444;
            --success: #10b981;
            --radius-lg: 16px;
            --radius-md: 10px;
            --shadow-card: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-page);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: var(--text-main);
        }

        .auth-container {
            width: 100%;
            max-width: 460px;
        }

        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            padding: 36px 32px;
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-icon-box {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary), #1e40af);
            color: #ffffff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            box-shadow: 0 8px 16px -4px rgba(37, 99, 235, 0.35);
            margin-bottom: 12px;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
        }

        .brand-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .alert {
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.4;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 11px 14px 11px 40px;
            font-size: 14px;
            color: var(--text-main);
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            transition: all 0.2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
        }

        .footer-links {
            margin-top: 20px;
            text-align: center;
            font-size: 13px;
        }

        .link-text {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .link-text:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="brand-icon-box"><i class="fa-solid fa-lock"></i></div>
                <h1 class="brand-title">Create New Password</h1>
                <p class="brand-subtitle">Enter your new secure password for <strong><?= htmlspecialchars($email) ?></strong></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation" style="margin-top: 2px;"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($tokenValid): ?>
                <form action="<?= url('/reset-password') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">

                    <div class="form-group">
                        <label class="form-label" for="password">New Password (min. 8 characters)</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Enter new password" required minlength="8" autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirmation">Confirm New Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-shield-halved input-icon"></i>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-input"
                                placeholder="Re-enter new password" required minlength="8">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <span>Save New Password & Continue</span>
                        <i class="fa-solid fa-check"></i>
                    </button>
                </form>
            <?php else: ?>
                <div class="footer-links">
                    <a href="<?= url('/forgot-password') ?>" class="link-text">
                        <i class="fa-solid fa-arrow-rotate-left"></i> Request a new password reset link
                    </a>
                </div>
            <?php endif; ?>

            <div class="footer-links" style="margin-top: 16px;">
                <a href="<?= url('/login') ?>" class="link-text">
                    <i class="fa-solid fa-arrow-left"></i> Return to Login
                </a>
            </div>
        </div>
    </div>
</body>

</html>
