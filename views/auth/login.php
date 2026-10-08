<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../db_helper.php';

$error = '';
$success = '';

// Handle query flag messages
if (isset($_GET['logout'])) {
    $success = 'You have been successfully logged out.';
} elseif (isset($_GET['reset'])) {
    $success = 'Your password has been reset successfully. Please sign in with your new password.';
} elseif (isset($_GET['expired'])) {
    $error = 'Your session has expired due to inactivity. Please sign in again.';
}

// Handle POST Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && !verify_csrf_token()) {
        $error = 'Invalid or expired security token. Please refresh the page and try again.';
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter both your email / username and password.';
        } else {
            $authenticated = false;
            $userObj = null;
            $dbUser = null;

            try {
                $dbUser = \Illuminate\Database\Capsule\Manager::table('users')
                    ->where('email', $identifier)
                    ->first();

                if ($dbUser && password_verify($password, $dbUser->password)) {
                    if (isset($dbUser->status) && $dbUser->status !== 'ACTIVE' && isset($dbUser->is_active) && !$dbUser->is_active) {
                        $error = 'Your account has been deactivated. Please contact your administrator.';
                    } else {
                        $authenticated = true;
                        $userObj = [
                            'id' => (int) $dbUser->id,
                            'name' => $dbUser->name,
                            'email' => $dbUser->email,
                            'role' => $dbUser->role ?? 'ADMIN',
                            'avatar' => strtoupper(substr($dbUser->name, 0, 1) . substr(strrchr($dbUser->name, ' ') ?: $dbUser->name, 1, 1))
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log("[LOGIN DB ERROR] " . $e->getMessage());
            }

            if ($authenticated && $userObj && $dbUser) {
                // Session regeneration to prevent session fixation attacks
                session_regenerate_id(true);

                $_SESSION['user'] = $userObj;
                $_SESSION['user_id'] = (int) $dbUser->id;
                $_SESSION['authenticated_at'] = time();
                $_SESSION['last_activity'] = time();

                // Remember-device support
                if (!empty($_POST['remember'])) {
                    $rememberToken = bin2hex(random_bytes(32));
                    setcookie('wts_remember', $dbUser->id . ':' . $rememberToken, time() + (86400 * 30), '/', '', false, true);
                } else {
                    setcookie('wts_remember', '', time() - 3600, '/');
                }

                // Update last login timestamp in database
                try {
                    \Illuminate\Database\Capsule\Manager::table('users')
                        ->where('id', $dbUser->id)
                        ->update(['last_login_at' => date('Y-m-d H:i:s')]);
                } catch (\Throwable $e) {}

                // Redirect to company selection
                header('Location: ' . url('/select-company'));
                exit();
            } else {
                if (empty($error)) {
                    // Do NOT expose whether an email exists - use uniform message
                    $error = 'Invalid email or password.';
                }
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
    <title>Login &bull; WTS LEDGER PRO</title>
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
            --danger: #ef4444;
            --radius-lg: 16px;
            --radius-md: 10px;
            --radius-sm: 6px;
            --shadow-card: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            --shadow-input: 0 1px 2px 0 rgba(15, 23, 42, 0.05);
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
                radial-gradient(at 90% 80%, rgba(16, 185, 129, 0.05) 0px, transparent 50%),
                linear-gradient(to right, #f1f5f9 1px, transparent 1px),
                linear-gradient(to bottom, #f1f5f9 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 32px 32px, 32px 32px;
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

        /* Flow Stepper Header */
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

        .flow-step.active .flow-step-circle {
            background: var(--primary);
            color: #ffffff;
        }

        .flow-arrow {
            color: #94a3b8;
            font-size: 11px;
        }

        /* Auth Card */
        .auth-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            padding: 36px 32px;
            position: relative;
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-logo-img {
            max-height: 52px;
            width: auto;
            object-fit: contain;
            margin-bottom: 12px;
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
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-main);
        }

        .brand-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Alert message */
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

        /* Form elements */
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
            transition: color 0.2s;
        }

        .form-input {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 11px 14px 11px 40px;
            font-size: 14px;
            color: var(--text-main);
            box-shadow: var(--shadow-input);
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-input:focus+.input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--primary);
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            font-size: 14px;
            border-radius: var(--radius-sm);
            transition: color 0.2s;
        }

        .password-toggle-btn:hover {
            color: var(--text-main);
        }

        .form-row-between {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            font-size: 13px;
        }

        .remember-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .remember-checkbox input {
            cursor: pointer;
            width: 15px;
            height: 15px;
            accent-color: var(--primary);
        }

        .link-text {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .link-text:hover {
            text-decoration: underline;
            color: var(--primary-hover);
        }

        /* Buttons */
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

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Demo accounts panel */
        .demo-panel {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .demo-header {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .demo-accounts-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .demo-pill {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 9px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.15s ease;
            text-align: left;
            width: 100%;
        }

        .demo-pill:hover {
            background: var(--primary-light);
            border-color: var(--primary-border);
            transform: translateX(2px);
        }

        .demo-user-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .demo-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        .demo-pill:hover .demo-avatar {
            background: var(--primary);
            color: #ffffff;
        }

        .demo-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
        }

        .demo-email {
            font-size: 11px;
            color: var(--text-muted);
        }

        .demo-role-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 9999px;
            background: #e2e8f0;
            color: #475569;
        }

        .demo-pill:hover .demo-role-badge {
            background: #dbeafe;
            color: #1e40af;
        }

        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>

<body>

    <div class="auth-container">
        <!-- Flow Stepper -->
        <div class="flow-stepper">
            <div class="flow-step active">
                <span class="flow-step-circle">1</span>
                <span>Login</span>
            </div>
            <i class="fa-solid fa-arrow-right flow-arrow"></i>
            <div class="flow-step">
                <span class="flow-step-circle">2</span>
                <span>Select Company</span>
            </div>
            <i class="fa-solid fa-arrow-right flow-arrow"></i>
            <div class="flow-step">
                <span class="flow-step-circle">3</span>
                <span>Dashboard</span>
            </div>
        </div>

        <!-- Main Card -->
        <div class="auth-card">
            <div class="auth-brand">
                <?php
                $logoPath = __DIR__ . '/../../public/assets/img/logo.png';
                if (file_exists($logoPath)):
                    ?>
                    <img src="<?= url('/assets/img/logo.png') ?>" alt="Wis Technosavvy Pvt Ltd" class="brand-logo-img">
                <?php else: ?>
                    <div class="brand-icon-box">W</div>
                <?php endif; ?>
                <h1 class="brand-title">Wis Technosavvy ERP</h1>
                <p class="brand-subtitle">GST Invoicing, Multi-Company & Accounting Portal</p>
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
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= url('/login') ?>" method="POST" id="loginForm">
                <?= csrf_field() ?>
                <!-- Email / Username -->
                <div class="form-group">
                    <label class="form-label" for="identifier">Email / Username</label>
                    <div class="input-wrapper">
                        <i class="fa-regular fa-envelope input-icon"></i>
                        <input type="text" id="identifier" name="identifier" class="form-input"
                            placeholder="Enter your email or username"
                            value="<?= htmlspecialchars($_POST['identifier'] ?? 'anil.d@wtsbill.in') ?>" required
                            autocomplete="username" autofocus>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        <a href="<?= url('/forgot-password') ?>" class="link-text" style="font-size: 12px;">Forgot password?</a>
                    </div>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-input"
                            placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility()"
                            title="Show/Hide Password">
                            <i class="fa-regular fa-eye" id="passwordEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-row-between">
                    <label class="remember-checkbox">
                        <input type="checkbox" name="remember" checked>
                        <span>Remember this device</span>
                    </label>
                    <span
                        style="font-size: 12px; color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fa-solid fa-shield-halved"></i> 256-Bit SSL
                    </span>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span>Sign In & Continue</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

        </div>

        <div class="footer-note">
            &copy; <?= date('Y') ?> Wis Technosavvy Pvt Ltd &bull; Next-Gen ERP Billing System
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('passwordEyeIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

    </script>

</body>

</html>