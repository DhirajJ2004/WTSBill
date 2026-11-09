<?php
$companyLogoUrl = \WTSBill\Services\CompanyBrandingService::getCompanyLogo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password &mdash; WTSBill ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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
            overflow: hidden;
        }
        .bg-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(70px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }
        .bg-blob-1 {
            width: 450px; height: 450px;
            top: -100px; left: -100px;
            background: radial-gradient(circle, #8EB69B 0%, #DAF1DE 70%, rgba(218, 241, 222, 0) 100%);
        }
        .bg-blob-2 {
            width: 500px; height: 500px;
            bottom: -120px; right: -120px;
            background: radial-gradient(circle, #8EB69B 0%, #DAF1DE 70%, rgba(218, 241, 222, 0) 100%);
        }
        .card {
            position: relative;
            z-index: 1;
            background: #FFFFFF;
            border: 2px solid #8EB69B;
            border-radius: 24px;
            width: 100%;
            max-width: 460px;
            padding: 2.75rem 2.5rem;
            box-shadow: 0 20px 45px -10px rgba(11, 43, 38, 0.12), 0 10px 25px -5px rgba(5, 31, 32, 0.05);
        }
        .header { text-align: center; margin-bottom: 2rem; }
        .brand-badge {
            width: 44px; height: 44px;
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
        }
        h1 { font-size: 1.75rem; font-weight: 800; color: #051F20; margin-bottom: 0.45rem; letter-spacing: -0.02em; }
        .subtitle { font-size: 0.9rem; color: #163832; line-height: 1.45; }
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            line-height: 1.4;
        }
        .alert-error { background: #FFF5F5; border: 1.5px solid #8EB69B; color: #051F20; }
        .alert-success { background: #DAF1DE; border: 1.5px solid #235347; color: #051F20; }
        .form-group { margin-bottom: 1.35rem; }
        label { display: block; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #163832; margin-bottom: 0.45rem; }
        input[type="email"], input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.95rem;
            background: #FFFFFF;
            border: 1.5px solid #8EB69B;
            border-radius: 10px;
            color: #051F20;
            font-family: inherit;
            font-size: 0.98rem;
            outline: none;
            transition: all 0.2s ease;
        }
        input:focus {
            border-color: #235347;
            box-shadow: 0 0 0 3px rgba(35, 83, 71, 0.15);
        }
        .btn-submit {
            width: 100%;
            padding: 0.9rem;
            background: #235347;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 8px 20px -4px rgba(35, 83, 71, 0.4);
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }
        .btn-submit:hover {
            background: #163832;
            transform: translateY(-1px);
        }
        .links {
            margin-top: 1.75rem;
            text-align: center;
            font-size: 0.88rem;
        }
        .links a { color: #235347; text-decoration: none; font-weight: 600; }
        .links a:hover { color: #163832; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="bg-blob bg-blob-1" aria-hidden="true"></div>
    <div class="bg-blob bg-blob-2" aria-hidden="true"></div>

    <div class="card">
        <div class="header">
            <div class="brand-badge"><img src="<?= e($companyLogoUrl) ?>" alt="WTS Logo" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.onerror=null; this.src='/assets/images/logo.png';"></div>
            <h1>Set New Password</h1>
            <p class="subtitle">Enter your security token and configure a new password.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <form action="/reset-password" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= e($csrf_token ?? '') ?>">

            <div class="form-group">
                <label for="token">Reset Token</label>
                <input type="text" id="token" name="token" value="<?= e($token ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters" required>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Repeat password" required>
            </div>

            <button type="submit" class="btn-submit">Update Password &rarr;</button>

            <div class="links">
                <a href="/login">&larr; Return to Sign In</a>
            </div>
        </form>
    </div>
</body>
</html>
