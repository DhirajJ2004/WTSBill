<?php

use WTSBill\Core\Config;
use WTSBill\Services\CompanyBrandingService;

$appName = Config::get('app.name', 'WTSBill ERP');
$pageTitle = 'Login — ' . $appName;
$companyLogoUrl = CompanyBrandingService::getCompanyLogo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= e($companyLogoUrl) ?>">
    <link rel="stylesheet" href="/assets/css/login.css">
    <style>
        /* Embedded fallback styles matching the mandatory 6-color green fintech palette */
        /* 1: #DAF1DE, 2: #8EB69B, 3: #235347, 4: #163832, 5: #0B2B26, 6: #051F20 */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #DAF1DE;
            color: #051F20;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }
        .bg-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(70px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.55;
        }
        .bg-blob-1 {
            width: 520px; height: 520px;
            top: -140px; left: -140px;
            background: radial-gradient(circle, #8EB69B 0%, #DAF1DE 70%, rgba(218, 241, 222, 0) 100%);
        }
        .bg-blob-2 {
            width: 580px; height: 580px;
            bottom: -160px; right: -150px;
            background: radial-gradient(circle, #8EB69B 0%, #DAF1DE 70%, rgba(218, 241, 222, 0) 100%);
            opacity: 0.45;
        }
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1060px;
            min-height: 590px;
            background: #FFFFFF;
            border: 2px solid #8EB69B;
            border-radius: 25px;
            box-shadow: 0 20px 45px -10px rgba(11, 43, 38, 0.12), 0 10px 25px -5px rgba(5, 31, 32, 0.05);
            display: flex;
            overflow: hidden;
        }
        .login-col-form {
            flex: 1 1 45%;
            max-width: 480px;
            padding: 3rem 3rem 2.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .login-col-illustration {
            flex: 1 1 55%;
            background: linear-gradient(135deg, #F4FAF6 0%, #DAF1DE 100%);
            border-left: 1px solid #8EB69B;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        .brand-header {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 2rem;
        }
        .brand-icon {
            width: 36px; height: 36px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }
        .brand-title {
            font-size: 0.88rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #163832;
        }
        .login-heading {
            font-size: 2.15rem;
            font-weight: 800;
            color: #051F20;
            line-height: 1.15;
            margin-bottom: 0.45rem;
            letter-spacing: -0.02em;
        }
        .login-subtitle {
            font-size: 0.95rem;
            font-weight: 500;
            color: #163832;
            line-height: 1.45;
            margin-bottom: 1.75rem;
        }
        .alert {
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            line-height: 1.4;
        }
        .alert-error {
            background-color: #FFF5F5;
            border: 1.5px solid #8EB69B;
            color: #051F20;
        }
        .alert-success {
            background-color: #DAF1DE;
            border: 1.5px solid #235347;
            color: #051F20;
        }
        .alert-warning {
            background-color: #DAF1DE;
            border: 1.5px solid #8EB69B;
            color: #163832;
        }
        .form-group {
            margin-bottom: 1.55rem;
            position: relative;
        }
        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #163832;
            margin-bottom: 0.45rem;
        }
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 0.95rem;
            background: #FFFFFF;
            border: 1.5px solid #8EB69B;
            border-radius: 10px;
            color: #051F20;
            font-family: inherit;
            font-size: 0.98rem;
            outline: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-input::placeholder { color: #8EB69B; font-size: 0.92rem; }
        .form-input:focus {
            border-color: #235347;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(35, 83, 71, 0.15);
        }
        .form-group:focus-within .form-label {
            color: #235347;
        }
        .btn-toggle-password {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #235347;
            padding: 0.45rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
            border-radius: 6px;
            z-index: 2;
        }
        .btn-toggle-password:hover { color: #163832; }
        .btn-toggle-password svg { pointer-events: none; }
        #password { padding-right: 2.75rem; }
        .form-row-aux {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: -0.25rem;
            margin-bottom: 1.75rem;
            font-size: 0.86rem;
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            color: #163832;
            user-select: none;
            font-weight: 500;
        }
        .checkbox-container input[type="checkbox"] {
            accent-color: #235347;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        .forgot-link {
            color: #235347;
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover {
            color: #163832;
            text-decoration: underline;
        }
        .btn-login {
            width: 100%;
            padding: 0.9rem 1.5rem;
            background: #235347;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.98rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 8px 20px -4px rgba(35, 83, 71, 0.4);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
        }
        .btn-login:hover {
            background: #163832;
            box-shadow: 0 10px 24px -4px rgba(22, 56, 50, 0.5);
            transform: translateY(-1px);
        }
        .btn-login:active {
            background: #0B2B26;
            transform: translateY(0);
        }
        .login-footer {
            margin-top: 1.75rem;
            font-size: 0.78rem;
            color: #163832;
            text-align: center;
            opacity: 0.85;
        }
        .illustration-container {
            width: 100%;
            max-width: 530px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .illustration-img {
            width: 100%;
            height: auto;
            max-height: 480px;
            object-fit: contain;
            filter: drop-shadow(0 15px 30px rgba(11, 43, 38, 0.1));
        }
        @media (max-width: 900px) {
            .login-container { flex-direction: column; max-width: 580px; min-height: auto; }
            .login-col-form { flex: 1 1 100%; max-width: 100%; padding: 2.5rem 2.25rem 2rem 2.25rem; }
            .login-col-illustration { flex: 1 1 100%; border-left: none; border-top: 1px solid #8EB69B; padding: 2rem; }
            .illustration-img { max-height: 320px; }
        }
        @media (max-width: 480px) {
            body { padding: 0.85rem; }
            .login-container { border-radius: 20px; }
            .login-col-form { padding: 2rem 1.4rem; }
            .login-heading { font-size: 1.75rem; }
            .form-row-aux { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .login-col-illustration { padding: 1.5rem 1rem; }
            .illustration-img { max-height: 240px; }
        }
    </style>
</head>
<body>
    <!-- Background Abstract Organic Shapes using #8EB69B and #DAF1DE -->
    <div class="bg-blob bg-blob-1" aria-hidden="true"></div>
    <div class="bg-blob bg-blob-2" aria-hidden="true"></div>

    <!-- Main Centered Container with #8EB69B Border & 25px Radius -->
    <div class="login-container">
        <!-- LEFT COLUMN: Form -->
        <div class="login-col-form">
            <!-- Brand Badge -->
            <div class="brand-header">
                <div class="brand-icon">
                    <img src="<?= e($companyLogoUrl) ?>" alt="WTS Logo" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='/assets/images/logo.png';">
                </div>
                <div class="brand-title"><?= e($appName) ?></div>
            </div>

            <div class="form-content">
                <h1 class="login-heading">Welcome Back</h1>
                <p class="login-subtitle">Manage your business with confidence.</p>

                <!-- Session Expiration Alert -->
                <?php if (isset($_GET['session_expired'])): ?>
                    <div class="alert alert-warning" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>Your session has expired. Please sign in again.</span>
                    </div>
                <?php endif; ?>

                <!-- Server Error Alert -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Server Success Alert -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        <span><?= e($success) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Authentication Form -->
                <form id="login-form" action="/login" method="POST" autocomplete="on">
                    <input type="hidden" name="_csrf_token" value="<?= e($csrf_token ?? '') ?>">

                    <!-- Email / Username Field -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address / Username</label>
                        <div class="input-wrapper">
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                placeholder="name@company.in" 
                                value="<?= e($_POST['email'] ?? '') ?>" 
                                required 
                                autofocus
                            >
                        </div>
                    </div>

                    <!-- Password Field with Visibility Toggle -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="••••••••" 
                                required
                            >
                            <button 
                                type="button" 
                                id="btn-toggle-password" 
                                class="btn-toggle-password" 
                                aria-label="Show password" 
                                title="Show password"
                                onclick="window.togglePasswordVisibility(event)"
                            >
                                <!-- Eye Open Icon -->
                                <svg class="icon-eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <!-- Eye Closed Icon (Initially Hidden) -->
                                <svg class="icon-eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Auxiliary Controls: Remember Me & Forgot Password -->
                    <div class="form-row-aux">
                        <label class="checkbox-container">
                            <input type="checkbox" name="remember" value="1">
                            <span>Remember me</span>
                        </label>
                        <a href="/forgot-password" class="forgot-link">Forgot Password?</a>
                    </div>

                    <!-- Primary Submit Button -->
                    <button type="submit" id="btn-login-submit" class="btn-login">
                        <span>Sign In</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </form>
            </div>

            <!-- Footer Compliance Indicators -->
            <div class="login-footer">
                <span>🔒 Enterprise SSL Encrypted &bull; ISO 27001 Certified</span>
            </div>
        </div>

        <!-- RIGHT COLUMN: Business Analytics Illustration -->
        <div class="login-col-illustration">
            <div class="illustration-container">
                <img 
                    src="/assets/images/login-illustration.svg" 
                    alt="Financial Analytics Dashboard Illustration" 
                    class="illustration-img"
                    loading="eager"
                >
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Global resilient password toggle handler
        window.togglePasswordVisibility = function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            var pwd = document.getElementById('password');
            var btn = document.getElementById('btn-toggle-password');
            if (!pwd) return;

            var isPass = pwd.getAttribute('type') === 'password';
            pwd.setAttribute('type', isPass ? 'text' : 'password');

            if (btn) {
                var openIcon = btn.querySelector('.icon-eye-open');
                var closeIcon = btn.querySelector('.icon-eye-closed');
                if (openIcon && closeIcon) {
                    openIcon.style.display = isPass ? 'none' : 'block';
                    closeIcon.style.display = isPass ? 'block' : 'none';
                }
                btn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
                btn.setAttribute('title', isPass ? 'Hide password' : 'Show password');
            }
            try { pwd.focus(); } catch (err) {}
        };
    </script>
    <script src="/assets/js/login.js"></script>
</body>
</html>
