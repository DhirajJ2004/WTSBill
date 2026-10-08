<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../db_helper.php';

use App\Database\Database;
use App\Models\User;
use App\Automation\Email\EmailService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$error = '';
$success = '';
$devResetUrl = '';
$isAppDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN) || (defined('APP_ENV') && APP_ENV === 'development');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && !verify_csrf_token()) {
        $error = 'Invalid or expired security token. Please refresh the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            try {
                Database::init();
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Generate 32-byte cryptographically secure token
                    $rawToken = bin2hex(random_bytes(32));
                    $hashedToken = hash('sha256', $rawToken);

                    // Store in password_resets table with current timestamp
                    DB::table('password_resets')->where('email', $email)->delete();
                    DB::table('password_resets')->insert([
                        'email' => $email,
                        'token' => $hashedToken,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);

                    $resetUrl = url('/reset-password?token=' . urlencode($rawToken) . '&email=' . urlencode($email));

                    // Check email provider configuration
                    $emailProvider = EmailService::getProvider();
                    $isConfigured = $emailProvider->isConfigured();

                    if ($isConfigured) {
                        try {
                            $subject = "Password Reset Request - WTSBill ERP";
                            $body = "Hello {$user->name},\n\nA request was made to reset the password for your WTSBill ERP account.\n\nClick the link below to set a new password:\n{$resetUrl}\n\nThis link will expire in 60 minutes.\nIf you did not request this reset, please ignore this email.";
                            
                            $msg = EmailService::queueEmail(
                                (int)($user->current_company_id ?: 1),
                                $email,
                                'PASSWORD_RESET',
                                ['user_name' => $user->name, 'reset_url' => $resetUrl],
                                'USER',
                                (int)$user->id,
                                $subject,
                                $body
                            );
                            EmailService::processSend($msg);
                            $success = 'A password reset link has been dispatched to your email address.';
                        } catch (\Throwable $mailErr) {
                            error_log("[MAIL ERROR] Failed to send password reset: " . $mailErr->getMessage());
                            $error = 'Unable to send email. Please contact your system administrator.';
                        }
                    } else {
                        // Email infrastructure is not configured
                        error_log("[PASSWORD RESET DEV] Email provider not configured. Reset link for {$email}: {$resetUrl}");
                        
                        if ($isAppDebug || php_sapi_name() === 'cli') {
                            $devResetUrl = $resetUrl;
                            $success = 'Email infrastructure is not configured on this server. In development mode, click the link below to proceed with password reset:';
                        } else {
                            $error = 'Email service is not currently configured on this system. Please contact your system administrator to reset your password.';
                        }
                    }

                    AuditLogService::log(
                        (int)($user->current_company_id ?: 0),
                        $user->name,
                        'PASSWORD_RESET_REQUESTED',
                        'User',
                        (int)$user->id,
                        "Password reset initiated for {$email}."
                    );
                } else {
                    // Prevent user enumeration: generic success response
                    $success = 'If an account exists for this email, password reset instructions have been processed.';
                }
            } catch (\Throwable $e) {
                error_log("[PASSWORD RESET ERROR] " . $e->getMessage());
                $error = 'An error occurred while processing your request. Please try again.';
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
    <title>Forgot Password &bull; WTS LEDGER PRO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --text-main: #0f172a;
            --text-secondary: #475569;
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
            background-image:
                radial-gradient(at 10% 20%, rgba(37, 99, 235, 0.06) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(16, 185, 129, 0.05) 0px, transparent 50%);
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

        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .dev-link-box {
            margin-top: 10px;
            padding: 10px 14px;
            background: #ffffff;
            border: 1px dashed var(--primary);
            border-radius: 8px;
            word-break: break-all;
            font-size: 13px;
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
                <div class="brand-icon-box"><i class="fa-solid fa-key"></i></div>
                <h1 class="brand-title">Reset Your Password</h1>
                <p class="brand-subtitle">Enter your registered email address to receive password reset instructions.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation" style="margin-top: 2px;"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check" style="margin-top: 2px;"></i>
                    <div>
                        <?= htmlspecialchars($success) ?>
                        <?php if (!empty($devResetUrl)): ?>
                            <div class="dev-link-box">
                                <a href="<?= $devResetUrl ?>" class="link-text" style="font-weight: 700;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Password Reset Page
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= url('/forgot-password') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="email">Registered Email</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="form-input"
                            placeholder="e.g. anil.d@wtsbill.in"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Send Reset Instructions</span>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>

            <div class="footer-links">
                <a href="<?= url('/login') ?>" class="link-text">
                    <i class="fa-solid fa-arrow-left"></i> Return to Login
                </a>
            </div>
        </div>
    </div>
</body>

</html>
